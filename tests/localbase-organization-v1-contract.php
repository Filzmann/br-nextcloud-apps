<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default = ''): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }

    final class Server {
        public static mixed $service = null;
        public static function get(string $name): mixed { return self::$service; }
    }
}

namespace OCP\App {
    interface IAppManager {
        /** @return list<string> */
        public function getEnabledApps(): array;
    }
}

namespace Psr\Log {
    interface LoggerInterface {
        public function emergency($message, array $context = []): void;
        public function alert($message, array $context = []): void;
        public function critical($message, array $context = []): void;
        public function error($message, array $context = []): void;
        public function warning($message, array $context = []): void;
        public function notice($message, array $context = []): void;
        public function info($message, array $context = []): void;
        public function debug($message, array $context = []): void;
        public function log($level, $message, array $context = []): void;
    }
}

namespace OCA\LocalBase\AppInfo {
    final class Application {
        public const APP_ID = 'localbase';
    }
}

namespace {
    use OCA\FilzmannPermissionMatrix\Service\OrganizationSnapshotService as MatrixOrganizationSnapshotService;
    use OCA\LocalBase\Organization\AdOrganizationSettingsService;
    use OCA\LocalBase\Organization\AdOrganizationSnapshotService;
    use OCA\LocalBase\PublicApi\V1\OrganizationSnapshot;
    use OCA\LocalBase\PublicApi\V1\OrganizationSnapshotService;
    use OCP\App\IAppManager;
    use OCP\Server;
    use Psr\Log\LoggerInterface;

    $workspace = dirname(__DIR__);
    $prefixes = [
        'OCA\\LocalBase\\' => $workspace . '/localbase/lib/',
        'OCA\\FilzmannPermissionMatrix\\' => $workspace . '/filzmann_permission_matrix/lib/',
    ];
    spl_autoload_register(static function(string $class) use ($prefixes): void {
        foreach ($prefixes as $prefix => $directory) {
            if (!str_starts_with($class, $prefix)) continue;
            $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) require_once $file;
            return;
        }
    });

    $assertSame = static function(mixed $expected, mixed $actual, string $message): void {
        if ($expected !== $actual) {
            throw new RuntimeException($message . ' Erwartet: ' . var_export($expected, true) . '; erhalten: ' . var_export($actual, true));
        }
    };

    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default = ''): string {
            return $this->values[$appId][$key] ?? $default;
        }
        public function setValueString(string $appId, string $key, string $value): void {
            $this->values[$appId][$key] = $value;
        }
    };
    $apps = new class implements IAppManager {
        public array $enabled = ['localbase'];
        public function getEnabledApps(): array { return $this->enabled; }
    };
    $logger = new class implements LoggerInterface {
        public array $warnings = [];
        public function emergency($message, array $context = []): void {}
        public function alert($message, array $context = []): void {}
        public function critical($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
        public function warning($message, array $context = []): void { $this->warnings[] = [$message, $context]; }
        public function notice($message, array $context = []): void {}
        public function info($message, array $context = []): void {}
        public function debug($message, array $context = []): void {}
        public function log($level, $message, array $context = []): void {}
    };

    $settings = new AdOrganizationSettingsService($config);
    $settings->save($settings->definition()->toArray());
    $provider = new OrganizationSnapshotService(new AdOrganizationSnapshotService($settings));
    $snapshot = $provider->snapshot();
    $assertSame(OrganizationSnapshot::CONTRACT_VERSION, $snapshot->contractVersion(), 'Unerwartete LocalBase-Vertragsversion.');
    $assertSame(true, $snapshot->isValid(), 'Der reale LocalBase-Provider liefert keinen gültigen Snapshot.');
    $assertSame(false, isset($snapshot->toArray()['members']), 'Der öffentliche Snapshot enthält Mitgliederlisten.');

    Server::$service = $provider;
    $consumer = new MatrixOrganizationSnapshotService($apps, $logger);
    $valid = $consumer->snapshot();
    $assertSame('VALID', $valid['status'], 'Der reale Matrix-Consumer akzeptiert den echten LocalBase-V1-Vertrag nicht.');
    $assertSame(OrganizationSnapshot::CONTRACT_VERSION, $valid['contract_version'], 'Provider und Consumer verwenden unterschiedliche Vertragsversionen.');
    $assertSame($snapshot->checksum(), $valid['checksum'], 'Der Consumer bewahrt die Provider-Prüfsumme nicht.');

    $apps->enabled = [];
    $missing = (new MatrixOrganizationSnapshotService($apps, $logger))->snapshot();
    $assertSame('MISSING', $missing['status'], 'Eine fehlende LocalBase-App wird nicht kontrolliert ausgewiesen.');
    $assertSame([], $missing['roles'], 'Eine fehlende LocalBase-App liefert Rollenbedeutungen.');

    $apps->enabled = ['localbase'];
    $incompatible = (new class($apps, $logger) extends MatrixOrganizationSnapshotService {
        protected function readProviderSnapshot(): OrganizationSnapshot {
            throw new UnexpectedValueException('synthetic missing V1 provider');
        }
    })->snapshot();
    $assertSame('INCOMPATIBLE', $incompatible['status'], 'Ein aktivierter Provider ohne V1-Service wird nicht inkompatibel ausgewiesen.');
    $assertSame([], $incompatible['areas'], 'Ein inkompatibler Provider liefert Bereichsbedeutungen.');

    $config->values['localbase']['ad_organization_definition'] = '{invalid';
    $invalid = (new MatrixOrganizationSnapshotService($apps, $logger))->snapshot();
    $assertSame('INVALID', $invalid['status'], 'Beschädigte kanonische Organisationsdaten werden nicht fail-closed ausgewiesen.');
    $assertSame([], $invalid['roles'], 'Beschädigte Organisationsdaten liefern Rollenbedeutungen.');

    $consumerSource = file_get_contents($workspace . '/filzmann_permission_matrix/lib/Service/OrganizationSnapshotService.php');
    if ($consumerSource === false || str_contains($consumerSource, 'OCA\\LocalBase\\Organization\\')) {
        throw new RuntimeException('Der Matrix-Consumer verwendet weiterhin interne LocalBase-Organisationsklassen.');
    }

    echo 'LocalBase-Organisationsprovider-V1-Vertrag geprüft: localbase -> filzmann_permission_matrix' . PHP_EOL;
}
