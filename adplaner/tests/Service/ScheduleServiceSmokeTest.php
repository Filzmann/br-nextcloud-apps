<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Model/ShiftCandidate.php';
require __DIR__ . '/../../lib/Model/ShiftSlot.php';
require __DIR__ . '/../../lib/Model/Team.php';
require __DIR__ . '/../../lib/Repository/ShiftPlanRepository.php';
require __DIR__ . '/../../lib/Service/ShiftConfigService.php';
require __DIR__ . '/../../lib/Service/TeamAccessService.php';
require __DIR__ . '/../../lib/Service/ScheduleService.php';
require __DIR__ . '/../../lib/Store/ShiftPlanStore.php';

use OCA\AdPlaner\Model\ShiftCandidate;
use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Service\ScheduleService;
use OCA\AdPlaner\Service\ShiftConfigService;
use OCA\AdPlaner\Service\TeamAccessService;
use OCA\AdPlaner\Store\ShiftPlanStore;

class FakeShiftPlanStoreForSchedule extends ShiftPlanStore {
    public array $added = [];

    public function __construct() {
    }

    public function slotForMonth(int $slotId, string $teamCode, string $month): ?ShiftSlot {
        return new ShiftSlot($slotId, $teamCode, $month, $month . '-01', 'early', 'Frueh', '08:00', '14:00', true);
    }

    public function addCandidate(int $slotId, string $assistantUid, string $createdByUid): void {
        $this->added[] = compact('slotId', 'assistantUid', 'createdByUid');
    }

    public function slotsForMonth(string $teamCode, string $month): array {
        return [
            new ShiftSlot(1, $teamCode, $month, $month . '-01', 'early', 'Frueh', '08:00', '14:00', true),
        ];
    }

    public function candidatesForSlotIds(array $slotIds): array {
        return [
            1 => [
                new ShiftCandidate(1, 1, 'assistant-a', 'test-eb'),
                new ShiftCandidate(2, 1, 'test-eb', 'test-eb'),
            ],
        ];
    }

    public function dayNotesForMonth(string $teamCode, string $month): array {
        return [];
    }

    public function updateSlotDefinition(int $slotId, string $label, string $startsAt, string $endsAt, bool $enabled): void {
    }

    public function insertSlot(
        string $teamCode,
        string $month,
        string $workDate,
        string $segmentKey,
        string $label,
        string $startsAt,
        string $endsAt,
        bool $enabled
    ): int {
        return 99;
    }
}

class FakeTeamAccessServiceForSchedule extends TeamAccessService {
    public function __construct() {
    }
}

$checkSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$checkThrows = static function (callable $callback, string $message): void {
    try {
        $callback();
    } catch (\DomainException) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$assistants = [
    ['uid' => 'assistant-a', 'displayName' => 'Assistant A', 'isEb' => false, 'canReceiveShifts' => true],
    ['uid' => 'test-eb', 'displayName' => 'Test EB', 'isEb' => true, 'canReceiveShifts' => false],
];

$settings = [
    'shifts' => [
        ['key' => 'early', 'label' => 'Frueh', 'startsAt' => '08:00', 'endsAt' => '14:00', 'enabled' => true],
    ],
];

$assistantTeam = new Team('A1', 'ad-ASN-A1', 'ad-ASN-A1-Urlaub', 'Team A1', $assistants, $assistants, false, $settings);
$ebTeam = new Team('A1', 'ad-ASN-A1', 'ad-ASN-A1-Urlaub', 'Team A1', $assistants, $assistants, true, $settings);
$store = new FakeShiftPlanStoreForSchedule();
$service = new ScheduleService($store, new ShiftConfigService(), new FakeTeamAccessServiceForSchedule());

$service->addCandidate($assistantTeam, '2026-07', 9, '', 'assistant-a');
$checkSame('assistant-a', $store->added[0]['assistantUid'] ?? null, 'Assistant should be able to add themself.');

$service->addCandidate($ebTeam, '2026-07', 9, 'assistant-a', 'test-eb');
$checkSame('assistant-a', $store->added[1]['assistantUid'] ?? null, 'EB should be able to assign an assistant.');

$checkThrows(
    static fn() => $service->addCandidate($ebTeam, '2026-07', 9, '', 'test-eb'),
    'EB should not be able to add themself without selecting an assistant.'
);
$checkThrows(
    static fn() => $service->addCandidate($ebTeam, '2026-07', 9, 'test-eb', 'test-eb'),
    'EB accounts should not be assignable to shifts.'
);
$checkThrows(
    static fn() => $service->addCandidate($assistantTeam, '2026-07', 9, 'test-eb', 'assistant-a'),
    'Assistants should not assign other users.'
);

$plan = $service->monthPlan($ebTeam, '2026-07', 'test-eb');
$slotCandidates = $plan['days'][0]['slots'][0]['candidates'] ?? [];
$checkSame(['assistant-a'], array_column($slotCandidates, 'uid'), 'Month plan should hide non-assignable EB candidates.');

echo 'AdPlaner schedule smoke tests passed' . PHP_EOL;
