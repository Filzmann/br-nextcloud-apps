<?php

declare(strict_types=1);

$workspace = dirname(__DIR__);

require_once $workspace . '/flz_permission_matrix/tests/bootstrap.php';
require_once $workspace . '/flz_data_protection/tests/bootstrap.php';

$apps = [
    'OCA\\FlzCalendar\\' => $workspace . '/flzcalendar/lib/',
    'OCA\\FlzPlaner\\' => $workspace . '/flzplaner/lib/',
    'OCA\\FlzUrlaub\\' => $workspace . '/flzurlaub/lib/',
    'OCA\\FlzRoom\\' => $workspace . '/flzroom/lib/',
    'OCA\\BrTop\\' => $workspace . '/brtop/lib/',
    'OCA\\BrStunden\\' => $workspace . '/brstunden/lib/',
    'OCA\\FlzRecruitment\\' => $workspace . '/flzrecruitment/lib/',
    'OCA\\FlzBqPlanning\\' => $workspace . '/flzbqplanung/lib/',
    'OCA\\FlzDataProtection\\' => $workspace . '/flz_data_protection/lib/',
    'OCA\\FlzOrganization\\' => $workspace . '/flz_organization/lib/',
    'OCA\\LocalBase\\' => $workspace . '/localbase/lib/',
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

use OCA\FlzCalendar\Permission\CalendarPermissionProvider;
use OCA\FlzCalendar\Permission\CalendarPermissionSourceInterface;
use OCA\FlzBqPlanning\Permission\BqPermissionProvider;
use OCA\FlzBqPlanning\Permission\BqPermissionSourceInterface;
use OCA\FlzPlaner\Permission\PlanerPermissionProvider;
use OCA\FlzPlaner\Permission\PlanerPermissionSourceInterface;
use OCA\FlzRoom\Permission\RoomPermissionProvider;
use OCA\FlzUrlaub\Permission\VacationPermissionProvider;
use OCA\FlzUrlaub\Permission\VacationPermissionSourceInterface;
use OCA\BrStunden\Permission\BrStundenPermissionProvider;
use OCA\BrStunden\Permission\BrStundenPermissionSourceInterface;
use OCA\BrTop\Permission\BrTopPermissionProvider;
use OCA\BrTop\Permission\BrTopPermissionSourceInterface;
use OCA\FlzDataProtection\Permission\DataProtectionPermissionProvider;
use OCA\FlzDataProtection\Contract\OrganizationSnapshotSource;
use OCA\FlzDataProtection\Service\OrganizationGroupPolicy;
use OCA\FlzDataProtection\Service\PermissionAssignmentsService;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\FlzRecruitment\Organization\OrganizationSnapshot as RecruitmentOrganizationSnapshot;
use OCA\FlzOrganization\Organization\OrganizationDefinition;
use OCA\FlzCalendar\Service\CalendarOrganization;
use OCA\FlzUrlaub\Service\VacationOrganizationDefinition;
use OCA\FlzRecruitment\Permission\RecruitmentPermissionProvider;
use OCA\FlzRecruitment\Permission\RecruitmentPermissionSourceInterface;
use OCP\IAppConfig;
use OCP\IGroupManager;

$definition = OrganizationDefinition::defaults()->toArray();
$config = new class implements IAppConfig {
        public function getValueArray(string $appId, string $key, array $default = [], bool $lazy = false): array {
            return $default;
        }
        public function getValueBool(string $appId, string $key, bool $default = false, bool $lazy = false): bool {
            return $default;
        }
        public function setValueArray(string $appId, string $key, array $value, bool $lazy = false): void {}
        public function setValueBool(string $appId, string $key, bool $value, bool $lazy = false): void {}
    };
$groups = new class implements IGroupManager {
        public function isAdmin(string $uid): bool { return false; }
        public function isInGroup(string $uid, string $gid): bool { return false; }
        public function groupExists(string $gid): bool { return true; }
    };
$organization = new class implements OrganizationSnapshotSource
{
    public function snapshot(): ?array
    {
        return ['contractVersion' => '1.0', 'valid' => true, 'roles' => [], 'areas' => []];
    }
};
$retentionSettings = new PermissionAssignmentsService(
    $config,
    $groups,
    new OrganizationGroupPolicy($organization, $groups),
);
$providers = [
    new CalendarPermissionProvider(new class($definition) implements CalendarPermissionSourceInterface {
        public function __construct(private array $definition) {}
        public function definition(): ?CalendarOrganization { return new CalendarOrganization($this->definition); }
        public function peerGroups(): array { return ['flz-EB']; }
    }),
    new PlanerPermissionProvider(new class implements PlanerPermissionSourceInterface {
        public function teamGroupIds(): array { return ['flz-ASN-Test']; }
        public function ebGroupId(): string { return 'flz-EB'; }
    }),
    new VacationPermissionProvider(new class($definition) implements VacationPermissionSourceInterface {
        public function __construct(private array $definition) {}
        public function definition(): ?VacationOrganizationDefinition { return VacationOrganizationDefinition::fromArray($this->definition); }
        public function teamGroupIds(): array { return ['flz-ASN-Test']; }
        public function enabledPeerEditGroups(): array { return []; }
        public function enabledPeerApprovalGroups(): array { return []; }
        public function asnPeerGroup(): string { return 'flz-ASN-*'; }
        public function authorizationStatus(): string { return 'VALID'; }
    }),
    new RoomPermissionProvider(),
    new BrTopPermissionProvider(new class implements BrTopPermissionSourceInterface {
        public function memberGroupId(): string { return 'br-members'; }
        public function chairGroupId(): string { return 'br-chair'; }
        public function deputyGroupId(): string { return 'br-deputy'; }
    }),
    new BrStundenPermissionProvider(new class implements BrStundenPermissionSourceInterface {
        public function status(): array { return ['complete' => true, 'warning' => null]; }
        public function groupIds(): array { return ['member' => 'br-members', 'chair' => 'br-chair', 'deputy' => 'br-deputy']; }
        public function assignments(): array { return \OCA\BrStunden\Service\CapabilitySettingsService::DEFAULTS; }
    }),
    new RecruitmentPermissionProvider(new class implements RecruitmentPermissionSourceInterface {
        public function organization(): RecruitmentOrganizationSnapshot {
            return RecruitmentOrganizationSnapshot::valid('1.0', 4, 'synthetic-checksum', [
                'staff_hr' => ['groupId' => 'flz-HR', 'label' => 'HR'],
                'payroll' => ['groupId' => 'flz-Payroll', 'label' => 'Lohn'],
                'eb' => ['groupId' => 'flz-EB', 'label' => 'Einsatzbegleitung'],
            ], [
                'north' => ['groupId' => 'flz-Area-North', 'label' => 'Nord'],
            ]);
        }
        public function permissionSettings(): array {
            return ['firstGuideGroupId' => 'flz-first-guides', 'representatives' => []];
        }
    }),
    new BqPermissionProvider(new class implements BqPermissionSourceInterface {
        public function permissionRoleGroups(): array {
            return [
                'planning' => ['bq-planning'],
                'teaching' => ['bq-teaching'],
                'publishing' => ['bq-publishing'],
            ];
        }
        public function complete(): bool { return true; }
        public function warnings(): array { return []; }
    }),
    new DataProtectionPermissionProvider($retentionSettings),
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
