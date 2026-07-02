<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Service/AgendaTreeService.php';

use OCA\BrTop\Service\AgendaTreeService;

$checkSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$service = new AgendaTreeService();

$tops = [
    ['id' => 1, 'parent_id' => null, 'position' => 1, 'level' => 1],
    ['id' => 2, 'parent_id' => 1, 'position' => 2, 'level' => 2],
    ['id' => 3, 'parent_id' => 2, 'position' => 3, 'level' => 3],
    ['id' => 4, 'parent_id' => null, 'position' => 4, 'level' => 1],
];

$numbered = $service->numberedItems($tops);
$checkSame(['1', '1.1', '1.1.1', '2'], array_column($numbered, 'agenda_number'), 'Agenda numbering should follow the hierarchy.');

$checkSame([1, 2, 3], $service->subtreeIds($tops, 1), 'Subtree IDs should include descendants in order.');
$checkSame(3, $service->maxSubtreeLevel($tops, 1), 'Subtree max level should detect depth 3.');
$checkSame(1, $service->previousSibling($tops, $tops[3])['id'] ?? null, 'Previous root sibling should be found by position.');

$overDeep = [
    ['id' => 1, 'parent_id' => null, 'position' => 1, 'level' => 1],
    ['id' => 2, 'parent_id' => 1, 'position' => 2, 'level' => 2],
    ['id' => 3, 'parent_id' => 2, 'position' => 3, 'level' => 3],
    ['id' => 4, 'parent_id' => 3, 'position' => 4, 'level' => 4],
];

$ordered = $service->orderedTreeItems($overDeep);
$checkSame([1, 2, 3, 4], array_column($ordered, 'id'), 'Ordered items should keep traversal order.');
$checkSame([1, 2, 3, 1], array_column($ordered, 'level'), 'Items deeper than level 3 should be lifted to root level.');
$checkSame(null, $ordered[3]['parent_id'], 'Lifted items should lose their parent relation.');

echo 'AgendaTreeService smoke tests passed' . PHP_EOL;
