#!/usr/bin/env php
<?php
declare(strict_types=1);

function fail(string $message): never {
    fwrite(STDERR, $message . PHP_EOL);
    exit(2);
}

$allowed = [
    '--active-file', '--lock-file', '--app-id', '--candidate-id', '--deployment-id',
    '--source-commit', '--build-id', '--build-sha256', '--verified-at', '--active-reader-user',
    '--staging-target',
];
if ($argc !== 23) {
    fail('Ungültiger Aufruf für die Staging-Deployment-Verifikation.');
}
$arguments = [];
for ($index = 1; $index < $argc; $index += 2) {
    $name = $argv[$index];
    if (!in_array($name, $allowed, true) || isset($arguments[$name])) {
        fail('Unbekannte oder doppelte Verifikationsoption.');
    }
    $arguments[$name] = $argv[$index + 1];
}
foreach ($allowed as $name) {
    if (!isset($arguments[$name]) || $arguments[$name] === '') {
        fail("Erforderliche Verifikationsoption fehlt: $name");
    }
}
if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $arguments['--app-id'])
    || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$/D', $arguments['--candidate-id'])
    || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$/D', $arguments['--deployment-id'])
    || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,127}$/D', $arguments['--build-id'])
    || !preg_match('/^[a-z_][a-z0-9_-]{0,31}$/D', $arguments['--active-reader-user'])
    || !preg_match('/^[a-z][a-z0-9-]{1,31}$/D', $arguments['--staging-target'])
    || !preg_match('/^[0-9a-f]{40}([0-9a-f]{24})?$/D', $arguments['--source-commit'])
    || !preg_match('/^[0-9a-f]{64}$/D', $arguments['--build-sha256'])
    || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $arguments['--verified-at'])) {
    fail('Ungültige gebundene Verifikationsdaten.');
}
$timestamp = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $arguments['--verified-at'], new DateTimeZone('UTC'));
if ($timestamp === false || $timestamp->format('Y-m-d\TH:i:s\Z') !== $arguments['--verified-at']) {
    fail('Ungültiger Verifikationszeitpunkt.');
}

$lockFile = $arguments['--lock-file'];
$lockMetadata = @lstat($lockFile);
if ($lockMetadata === false || !is_file($lockFile) || is_link($lockFile) || ($lockMetadata['nlink'] ?? 0) !== 1) {
    fail('Gemeinsame Deployment-Sperrdatei fehlt oder ist unsicher.');
}
$deployLock = @fopen($lockFile, 'c+b');
if ($deployLock === false || !flock($deployLock, LOCK_EX)) {
    fail('Gemeinsame Deployment-Sperre kann nicht exklusiv erworben werden.');
}

$activeFile = $arguments['--active-file'];
$metadata = @lstat($activeFile);
if ($metadata === false || !is_file($activeFile) || is_link($activeFile) || ($metadata['nlink'] ?? 0) !== 1) {
    fail('Aktiver Deployment-Marker fehlt oder ist unsicher.');
}
$handle = @fopen($activeFile, 'rb');
if ($handle === false || !flock($handle, LOCK_EX)) {
    fail('Aktiver Deployment-Marker kann nicht gesperrt werden.');
}
try {
    $content = stream_get_contents($handle);
    $value = json_decode($content === false ? '' : $content, true, 64, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    fail('Aktiver Deployment-Marker ist kein gültiges JSON.');
}
$originalDigest = hash('sha256', $content === false ? '' : $content);
$expected = [
    $arguments['--candidate-id'], $arguments['--deployment-id'], $arguments['--source-commit'],
    ['id' => $arguments['--build-id'], 'sha256' => $arguments['--build-sha256']],
    $arguments['--app-id'], $arguments['--staging-target'], 'pending-https-verification',
];
$actual = [
    $value['candidate_id'] ?? null, $value['deployment_id'] ?? null, $value['source_commit'] ?? null,
    $value['build'] ?? null, $value['app_id'] ?? null, $value['staging_target'] ?? null, $value['status'] ?? null,
];
if (($value['schema'] ?? null) !== 'filzmann.active-staging-deployment/v2' || $actual !== $expected) {
    fail('HTTPS-Verifikation stimmt nicht mit dem aktiven Candidate-Deployment überein.');
}
$value['status'] = 'verified';
$value['verified_at'] = $arguments['--verified-at'];
$encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
$temporary = $activeFile . '.verify.' . getmypid();
$output = @fopen($temporary, 'xb');
if ($output === false) {
    fail('Temporärer Verifikationsmarker kann nicht exklusiv erzeugt werden.');
}
try {
    chmod($temporary, 0600);
    if (fwrite($output, $encoded) !== strlen($encoded) || !fflush($output)) {
        fail('Verifizierter Deployment-Marker konnte nicht vollständig geschrieben werden.');
    }
    if (function_exists('fsync')) {
        fsync($output);
    }
} finally {
    fclose($output);
}
$aclProcess = @proc_open(
    ['/usr/bin/setfacl', '-m', 'u:' . $arguments['--active-reader-user'] . ':r--', $temporary],
    [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $aclPipes,
);
if (!is_resource($aclProcess)) {
    @unlink($temporary);
    fail('Acceptance-Leserecht konnte nicht vorbereitet werden.');
}
foreach ($aclPipes as $pipe) {
    if (is_resource($pipe)) {
        stream_get_contents($pipe);
        fclose($pipe);
    }
}
if (proc_close($aclProcess) !== 0) {
    @unlink($temporary);
    fail('Acceptance-Leserecht konnte nicht gesetzt werden.');
}
$current = @lstat($activeFile);
$currentDigest = @hash_file('sha256', $activeFile);
if ($current === false || ($current['dev'] ?? null) !== ($metadata['dev'] ?? null) || ($current['ino'] ?? null) !== ($metadata['ino'] ?? null) || !is_string($currentDigest) || !hash_equals($originalDigest, $currentDigest)) {
    @unlink($temporary);
    fail('Aktiver Deployment-Marker wurde während der Verifikation verändert oder ersetzt.');
}
if (!@rename($temporary, $activeFile)) {
    @unlink($temporary);
    fail('Verifizierter Deployment-Marker konnte nicht atomar aktiviert werden.');
}
flock($handle, LOCK_UN);
fclose($handle);
flock($deployLock, LOCK_UN);
fclose($deployLock);
