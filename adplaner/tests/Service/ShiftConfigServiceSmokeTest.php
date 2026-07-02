<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Service/ShiftConfigService.php';

use OCA\AdPlaner\Service\ShiftConfigService;

$checkSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$service = new ShiftConfigService();
$defaults = $service->defaults();
$defaultSegments = $service->segments($defaults);

$checkSame(['early', 'late', 'night'], array_column($defaultSegments, 'key'), 'Default shifts should use the normal three-shift setup.');
$checkSame('08:00', $defaultSegments[0]['startsAt'], 'Default early shift should start at 08:00.');
$checkSame('14:00', $defaultSegments[0]['endsAt'], 'Default early shift should end at 14:00.');
$checkSame('20:00', $defaultSegments[2]['startsAt'], 'Default night shift should start at 20:00.');
$checkSame('08:00', $defaultSegments[2]['endsAt'], 'Default night shift should end at the next early start.');

$legacySettings = $service->normalize([
    'meetingDay' => '2026-07-15',
    'shiftStarts' => [
        'early' => '07:00',
        'late' => '15:00',
        'night' => '21:30',
    ],
    'enabledSegments' => [
        'before_early' => false,
        'early' => true,
        'late' => true,
        'night' => true,
    ],
]);

$legacySegments = $service->segments($legacySettings);
$customSettings = $service->normalize([
    'meetingDay' => '2026-07-15',
    'shifts' => [
        [
            'key' => 'first',
            'label' => 'Erste',
            'startsAt' => '08:00',
            'endsAt' => '12:00',
            'enabled' => true,
        ],
        [
            'key' => 'overlap',
            'label' => 'Ueberlappung',
            'startsAt' => '11:00',
            'endsAt' => '15:00',
            'enabled' => true,
        ],
        [
            'key' => 'night',
            'label' => 'Nacht',
            'startsAt' => '20:00',
            'endsAt' => '08:00',
            'enabled' => false,
        ],
    ],
]);
$customSegments = $service->segments($customSettings);
$days = $service->monthDays('2026-02');

$checkSame('2026-07-15', $legacySettings['meetingDay'], 'Meeting day should be preserved.');
$checkSame(['early', 'late', 'night'], array_column($legacySegments, 'key'), 'Legacy segments should stay in day order without a separate before-early segment.');
$checkSame('07:00', $legacySegments[0]['startsAt'], 'Legacy early segment should start at configured time.');
$checkSame('15:00', $legacySegments[0]['endsAt'], 'Legacy early segment should end at late start.');
$checkSame('21:30', $legacySegments[2]['startsAt'], 'Legacy night segment should start at configured time.');
$checkSame('07:00', $legacySegments[2]['endsAt'], 'Legacy night segment should end at early start on the following day.');
$checkSame(['first', 'overlap', 'night'], array_column($customSegments, 'key'), 'Custom shifts should keep their configured order.');
$checkSame('11:00', $customSegments[1]['startsAt'], 'Overlapping custom shifts should be allowed.');
$checkSame('15:00', $customSegments[1]['endsAt'], 'Overlapping custom shifts should keep their end time.');
$checkSame('20:00', $customSegments[2]['startsAt'], 'Cross-midnight custom shifts should keep their start time.');
$checkSame('08:00', $customSegments[2]['endsAt'], 'Cross-midnight custom shifts should keep their end time.');
$checkSame(false, $customSegments[2]['enabled'], 'Disabled custom shifts should be preserved.');
$checkSame(28, count($days), 'February 2026 should have 28 days.');
$checkSame('2026-02-01', $days[0]['date'], 'First month day should be correct.');

echo 'AdPlaner shift config smoke tests passed' . PHP_EOL;
