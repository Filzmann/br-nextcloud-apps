<?php

declare(strict_types=1);

$workspace = dirname(__DIR__);

require_once $workspace . '/filzmann_permission_matrix/tests/bootstrap.php';

$apps = [
    'OCA\\AdCalendar\\' => $workspace . '/adcalendar/lib/',
    'OCA\\AdPlaner\\' => $workspace . '/adplaner/lib/',
    'OCA\\AdUrlaub\\' => $workspace . '/adurlaub/lib/',
    'OCA\\AdRoom\\' => $workspace . '/adroom/lib/',
    'OCA\\BrTop\\' => $workspace . '/brtop/lib/',
    'OCA\\BrStunden\\' => $workspace . '/brstunden/lib/',
];

spl_autoload_register(static function (string $class) use ($apps): void {
    foreach ($apps as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});

use OCA\AdCalendar\Permission\CalendarPermissionProvider;
use OCA\AdCalendar\Permission\CalendarPermissionSourceInterface;
use OCA\AdPlaner\Permission\PlanerPermissionProvider;
use OCA\AdPlaner\Permission\PlanerPermissionSourceInterface;
use OCA\AdRoom\Permission\RoomPermissionProvider;
use OCA\AdUrlaub\Permission\VacationPermissionProvider;
use OCA\AdUrlaub\Permission\VacationPermissionSourceInterface;
use OCA\BrStunden\Permission\BrStundenPermissionProvider;
use OCA\BrStunden\Permission\BrStundenPermissionSourceInterface;
use OCA\BrTop\Permission\BrTopPermissionProvider;
use OCA\BrTop\Permission\BrTopPermissionSourceInterface;
use OCA\FilzmannPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\LocalBase\Organization\AdOrganizationDefinition;

$definition = AdOrganizationDefinition::defaults();
$providers = [
    new CalendarPermissionProvider(new class($definition) implements CalendarPermissionSourceInterface {
        public function __construct(private AdOrganizationDefinition $definition) {}
        public function definition(): AdOrganizationDefinition { return $this->definition; }
        public function peerGroups(): array { return ['ad-EB']; }
    }),
    new PlanerPermissionProvider(new class implements PlanerPermissionSourceInterface {
        public function teamGroupIds(): array { return ['ad-ASN-Test']; }
        public function ebGroupId(): string { return 'ad-EB'; }
    }),
    new VacationPermissionProvider(new class($definition) implements VacationPermissionSourceInterface {
        public function __construct(private AdOrganizationDefinition $definition) {}
        public function definition(): AdOrganizationDefinition { return $this->definition; }
        public function teamGroupIds(): array { return ['ad-ASN-Test']; }
        public function enabledPeerGroups(): array { return []; }
        public function asnPeerGroup(): string { return 'ad-ASN-*'; }
    }),
    new RoomPermissionProvider(),
    new BrTopPermissionProvider(new class implements BrTopPermissionSourceInterface {
        public function memberGroupId(): string { return 'br-members'; }
    }),
    new BrStundenPermissionProvider(new class implements BrStundenPermissionSourceInterface {
        public function memberGroupId(): string { return 'br-members'; }
    }),
];

$verified = [];
foreach ($providers as $provider) {
    if (!$provider instanceof PermissionProvider) {
        throw new RuntimeException('Provider does not implement the V1 interface.');
    }

    $descriptor = $provider->descriptor();
    $result = $provider->collect();
    if ($descriptor->contractVersion() !== '1.0'
        || !in_array('permissions', $descriptor->capabilities(), true)
    ) {
        throw new RuntimeException('Invalid provider descriptor.');
    }
    if ($result->rules() === []) {
        throw new RuntimeException('Provider returned no rules.');
    }
    foreach ($result->rules() as $rule) {
        if ($rule->permission() === '' || $rule->condition()->operator() === '') {
            throw new RuntimeException('Invalid provider rule.');
        }
    }

    $verified[] = $descriptor->appId();
}

echo 'Permission-Provider-V1-Vertrag geprüft: ' . implode(', ', $verified) . PHP_EOL;
