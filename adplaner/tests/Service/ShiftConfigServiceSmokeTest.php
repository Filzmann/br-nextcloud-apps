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
$settings = $service->normalize([
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
        'night' => false,
    ],
]);

$segments = $service->segments($settings);
$days = $service->monthDays('2026-02');

$checkSame('2026-07-15', $settings['meetingDay'], 'Meeting day should be preserved.');
$checkSame('07:00', $segments[1]['startsAt'], 'Early segment should start at configured time.');
$checkSame(false, $segments[0]['enabled'], 'Disabled first segment should be preserved.');
$checkSame(28, count($days), 'February 2026 should have 28 days.');
$checkSame('2026-02-01', $days[0]['date'], 'First month day should be correct.');

echo 'AdPlaner shift config smoke tests passed' . PHP_EOL;
