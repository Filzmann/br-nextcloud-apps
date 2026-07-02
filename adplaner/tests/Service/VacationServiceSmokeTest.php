<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Model/Team.php';
require __DIR__ . '/../../lib/Model/VacationRequest.php';
require __DIR__ . '/../../lib/Repository/VacationRepository.php';
require __DIR__ . '/../../lib/Service/ShiftConfigService.php';
require __DIR__ . '/../../lib/Service/VacationService.php';
require __DIR__ . '/../../lib/Store/VacationStore.php';

use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Repository\VacationRepository;
use OCA\AdPlaner\Service\ShiftConfigService;
use OCA\AdPlaner\Service\VacationService;
use OCA\AdPlaner\Store\VacationStore;

class FakeVacationRepository extends VacationRepository {
    public array $seenAssistantUids = [];
    public array $created = [];

    public function __construct() {
    }

    public function findForUsersInYear(array $assistantUids, int $year): array {
        $this->seenAssistantUids = $assistantUids;

        return [
            [
                'id' => 42,
                'assistant_uid' => 'vac-a',
                'date_from' => $year . '-07-01',
                'date_to' => $year . '-07-02',
                'status' => 'planned',
                'note' => '',
            ],
            [
                'id' => 43,
                'assistant_uid' => 'team-b',
                'date_from' => $year . '-07-03',
                'date_to' => $year . '-07-03',
                'status' => 'approved',
                'note' => '',
            ],
        ];
    }

    public function findCoveringDate(string $assistantUid, string $date): ?array {
        return null;
    }

    public function create(string $assistantUid, string $dateFrom, string $dateTo, string $note, string $status = 'planned', ?string $updatedByUid = null): int {
        $this->created[] = compact('assistantUid', 'dateFrom', 'dateTo', 'note', 'status', 'updatedByUid');

        return 7;
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

$team = new Team(
    'A1',
    'ad-ASN-A1',
    'ad-ASN-A1-Urlaub',
    'Team A1',
    [
        ['uid' => 'team-a', 'displayName' => 'Team A'],
        ['uid' => 'team-b', 'displayName' => 'Team B'],
    ],
    [
        ['uid' => 'vac-a', 'displayName' => 'Vacation A'],
        ['uid' => 'team-b', 'displayName' => 'Team B'],
    ],
    true,
    []
);

$repository = new FakeVacationRepository();
$service = new VacationService(new VacationStore($repository), new ShiftConfigService());
$plan = $service->yearPlan($team, 2026, 'vac-a');

$checkSame(['vac-a', 'team-b'], $repository->seenAssistantUids, 'Vacation plan should query vacation visibility assistants.');
$checkSame(['vac-a', 'team-b'], array_column($plan['assistants'], 'uid'), 'Vacation plan rows should use vacation visibility assistants.');
$rowsByUid = [];
foreach ($plan['assistants'] as $row) {
    $rowsByUid[$row['uid']] = $row;
}
$checkSame('planned', $rowsByUid['vac-a']['days']['2026-07-01']['status'] ?? null, 'Vacation cells should expose planned days.');
$checkSame('approved', $rowsByUid['team-b']['days']['2026-07-03']['status'] ?? null, 'Vacation cells should expose approved days.');
$checkSame('', $rowsByUid['team-b']['days']['2026-07-04']['status'] ?? null, 'Vacation cells should stay empty outside vacation ranges.');

$service->setStatusForDate($team, 'vac-a', '2026-07-03', 'approved', 'eb');
$checkSame('vac-a', $repository->created[0]['assistantUid'] ?? null, 'EB should be able to set status for vacation-visible assistants.');
$checkSame('approved', $repository->created[0]['status'] ?? null, 'Status should be forwarded to created vacation entry.');

echo 'AdPlaner vacation smoke tests passed' . PHP_EOL;
