<?php

declare(strict_types=1);

$workspace = dirname(__DIR__);

require_once $workspace . '/filzmann_data_protection/tests/bootstrap.php';

$apps = [
    'OCA\\AdRoom\\' => $workspace . '/adroom/lib/',
    'OCA\\FilzmannPermissionMatrix\\' => $workspace . '/filzmann_permission_matrix/lib/',
    'OCA\\AdUrlaub\\' => $workspace . '/adurlaub/lib/',
    'OCA\\AdPlaner\\' => $workspace . '/adplaner/lib/',
    'OCA\\AdCalendar\\' => $workspace . '/adcalendar/lib/',
    'OCA\\Recruitment\\' => $workspace . '/adrecruitment/lib/',
    'OCA\\BrStunden\\' => $workspace . '/brstunden/lib/',
    'OCA\\BrTop\\' => $workspace . '/brtop/lib/',
    'OCA\\AdBqPlanning\\' => $workspace . '/adbqplanung/lib/',
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

use OCA\AdBqPlanning\Privacy\BqPersonalDataProvider;
use OCA\AdCalendar\Privacy\CalendarPersonalDataProvider;
use OCA\AdPlaner\Privacy\PlanerPersonalDataProvider;
use OCA\AdRoom\Privacy\RoomPersonalDataProvider;
use OCA\AdUrlaub\Privacy\VacationPersonalDataProvider;
use OCA\BrStunden\Privacy\BrStundenPersonalDataProvider;
use OCA\BrTop\Privacy\BrTopPersonalDataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\DataSubjectRef;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FilzmannDataProtection\PublicApi\V1\Testing\PersonalDataProviderContractTestKit;
use OCA\FilzmannPermissionMatrix\Db\PersonalDataProjectionRepository;
use OCA\FilzmannPermissionMatrix\Privacy\PermissionMatrixPersonalDataProvider;
use OCA\FilzmannPermissionMatrix\Service\ConfigService;
use OCA\Recruitment\Privacy\RecruitmentPersonalDataProvider;

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Erwartet: ' . var_export($expected, true) . '; erhalten: ' . var_export($actual, true));
    }
};

/** @var array<string, class-string<PersonalDataProvider>> $providerClasses */
$providerClasses = [
    'adroom' => RoomPersonalDataProvider::class,
    'filzmann_permission_matrix' => PermissionMatrixPersonalDataProvider::class,
    'adurlaub' => VacationPersonalDataProvider::class,
    'adplaner' => PlanerPersonalDataProvider::class,
    'adcalendar' => CalendarPersonalDataProvider::class,
    'adrecruitment' => RecruitmentPersonalDataProvider::class,
    'brstunden' => BrStundenPersonalDataProvider::class,
    'brtop' => BrTopPersonalDataProvider::class,
    'adbqplanung' => BqPersonalDataProvider::class,
];

$providers = [];
$unsupportedRequest = new PersonalDataRequest(
    new DataSubjectRef('external-applicant', 'synthetic-subject'),
    'de',
    'access-report',
    25,
    [],
);

foreach ($providerClasses as $expectedAppId => $providerClass) {
    $reflection = new ReflectionClass($providerClass);
    $provider = $reflection->newInstanceWithoutConstructor();
    if (!$provider instanceof PersonalDataProvider) {
        throw new RuntimeException($providerClass . ' implementiert nicht den echten Standalone-V1-Vertrag.');
    }

    $descriptor = $provider->descriptor();
    $assertSame($expectedAppId, $descriptor->appId(), 'Unerwartete Provider-App-ID.');
    $assertSame('1.0', $descriptor->contractVersion(), 'Unerwartete Provider-Vertragsversion.');
    $assertSame(true, $descriptor->supportsSubjectType('nextcloud-user'), 'Nextcloud-Subject-Typ fehlt.');
    $assertSame(true, in_array('personal-data', $descriptor->capabilities(), true), 'Personal-Data-Capability fehlt.');

    $page = $provider->collect($unsupportedRequest);
    $assertSame('not_applicable', $page->status(), 'Nicht unterstützter Subject-Typ ist nicht sicher abgegrenzt.');
    $assertSame([], $page->entries(), 'Nicht unterstützter Subject-Typ liefert Daten.');
    $assertSame(null, $page->nextCursor(), 'Nicht unterstützter Subject-Typ liefert einen Cursor.');

    $providers[$expectedAppId] = $provider;
}

$registration = new RegisterPersonalDataProvidersEvent();
foreach ($providers as $provider) {
    $registration->register($provider);
}
$assertSame(array_keys($providerClasses), array_keys($registration->providers()), 'Nicht alle realen Provider wurden registriert.');
$assertSame([], $registration->registrationFailures(), 'Kompatible reale Provider erzeugen Registrierungsfehler.');

$duplicateRegistration = new RegisterPersonalDataProvidersEvent();
$duplicateRegistration->register($providers['adroom']);
$duplicateRegistration->register($providers['adroom']);
$assertSame(
    ['adroom' => 'Provider incompatible.'],
    $duplicateRegistration->registrationFailures(),
    'Eine doppelte reale Provider-ID wird nicht kontrolliert abgewiesen.',
);

$projection = new class extends PersonalDataProjectionRepository {
    public function __construct() {
    }

    public function collectForSubject(string $uid, int $limit, string $asOf): array {
        return [
            [
                'source' => 'snapshot',
                'id' => '3',
                'createdAt' => '2026-09-02T12:00:00+00:00',
                'snapshotId' => 'snapshot-3',
                'nextcloudVersion' => '34.0.0',
                'groupCount' => 3,
                'appCount' => 9,
                'objectCount' => 12,
                'complianceStatus' => 'complete',
            ],
            [
                'source' => 'snapshot',
                'id' => '2',
                'createdAt' => '2026-09-02T11:00:00+00:00',
                'snapshotId' => 'snapshot-2',
                'nextcloudVersion' => '34.0.0',
                'groupCount' => 3,
                'appCount' => 9,
                'objectCount' => 11,
                'complianceStatus' => 'complete',
            ],
            [
                'source' => 'snapshot',
                'id' => '1',
                'createdAt' => '2026-09-02T10:00:00+00:00',
                'snapshotId' => 'snapshot-1',
                'nextcloudVersion' => '34.0.0',
                'groupCount' => 3,
                'appCount' => 9,
                'objectCount' => 10,
                'complianceStatus' => 'complete',
            ],
        ];
    }
};
$config = new class extends ConfigService {
    public function __construct() {
    }
};
$pagedProvider = new PermissionMatrixPersonalDataProvider($projection, $config);
$firstPage = PersonalDataProviderContractTestKit::verifyScenario(
    $pagedProvider,
    new PersonalDataRequest(
        new DataSubjectRef('nextcloud-user', 'synthetic-user'),
        'de',
        'access-report',
        1,
        [],
    ),
);
if ($firstPage->nextCursor() === null) {
    throw new RuntimeException('Der reale Paging-Provider liefert trotz Folgeseite keinen Cursor.');
}
$secondPage = PersonalDataProviderContractTestKit::verifyScenario(
    $pagedProvider,
    new PersonalDataRequest(
        new DataSubjectRef('nextcloud-user', 'synthetic-user'),
        'de',
        'access-report',
        1,
        ['filzmann_permission_matrix' => $firstPage->nextCursor()],
    ),
);
if ($secondPage->nextCursor() === $firstPage->nextCursor()) {
    throw new RuntimeException('Der reale Paging-Provider hat seinen Cursor nicht fortgeschrieben.');
}

echo 'Privacy-Provider-V1-Vertrag geprüft: ' . implode(', ', array_keys($providers)) . PHP_EOL;
