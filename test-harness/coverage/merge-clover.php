<?php

declare(strict_types=1);

if ($argc !== 3 && $argc !== 4) {
    fwrite(STDERR, "Aufruf: php merge-clover.php <App-ID> <Clover-Verzeichnis> [Mindest-Coverage]\n");
    exit(2);
}

$appId = $argv[1];
$directory = $argv[2];
$minimum = null;
if ($argc === 4) {
    if (!is_numeric($argv[3]) || (float)$argv[3] < 0.0 || (float)$argv[3] > 100.0) {
        fwrite(STDERR, "Ungültige Mindest-Coverage: {$argv[3]}\n");
        exit(2);
    }
    $minimum = (float)$argv[3];
}
$reports = glob(rtrim($directory, '/') . '/*.xml') ?: [];
if ($reports === []) {
    throw new RuntimeException("Keine Clover-Berichte für {$appId} gefunden.");
}

/** @var array<string, array<int, int>> $lines */
$lines = [];
foreach ($reports as $report) {
    $xml = simplexml_load_file($report);
    if ($xml === false) throw new RuntimeException("Ungültiger Clover-Bericht: {$report}");
    $files = $xml->xpath('/coverage/project/file | /coverage/project/package/file') ?: [];
    foreach ($files as $file) {
        $path = (string)$file['name'];
        foreach ($file->line as $line) {
            if ((string)$line['type'] !== 'stmt') continue;
            $number = (int)$line['num'];
            $count = (int)$line['count'];
            $lines[$path][$number] = max($lines[$path][$number] ?? 0, $count);
        }
    }
}

$executable = 0;
$covered = 0;
foreach ($lines as $fileLines) {
    $executable += count($fileLines);
    foreach ($fileLines as $count) if ($count > 0) $covered++;
}
$percent = $executable === 0 ? 0.0 : ($covered / $executable) * 100;
printf("%s\t%d\t%d\t%.2f\n", $appId, $executable, $covered, $percent);
if ($minimum !== null && round($percent, 2) < $minimum) {
    fwrite(
        STDERR,
        sprintf("%s: %.2f %% liegt unter %.2f %%.\n", $appId, $percent, $minimum),
    );
    ksort($lines);
    foreach ($lines as $path => $fileLines) {
        ksort($fileLines);
        foreach ($fileLines as $number => $count) {
            if ($count === 0) {
                fwrite(STDERR, sprintf("Nicht abgedeckt: %s:%d\n", $path, $number));
            }
        }
    }
    exit(1);
}
