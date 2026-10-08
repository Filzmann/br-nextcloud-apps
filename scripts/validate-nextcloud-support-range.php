<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if ($argc !== 5) {
    fwrite(STDERR, "Aufruf: php validate-nextcloud-support-range.php <info.xml> <app-id> <opendesk-boden> <release-ziel>\n");
    exit(2);
}

[$script, $path, $expectedAppId, $floorValue, $targetValue] = $argv;
unset($script);

foreach (['OpenDesk-Boden' => $floorValue, 'Release-Ziel' => $targetValue] as $label => $value) {
    if (preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
        fail($label . ' muss eine positive Nextcloud-Hauptversion sein');
    }
}

$floor = (int) $floorValue;
$target = (int) $targetValue;
if ($target < $floor) {
    fail("Release-Ziel {$target} liegt unter dem OpenDesk-Boden {$floor}");
}

libxml_use_internal_errors(true);
$xml = simplexml_load_file($path);
if ($xml === false) {
    fail("info.xml ist ungültig: {$path}");
}

$appId = trim((string) $xml->id);
if ($appId !== $expectedAppId) {
    fail("App-ID stimmt nicht: erwartet {$expectedAppId}, gefunden {$appId}");
}

$minimumValue = (string) $xml->dependencies->nextcloud['min-version'];
$maximumValue = (string) $xml->dependencies->nextcloud['max-version'];
if (preg_match('/^[1-9][0-9]*$/D', $minimumValue) !== 1
    || preg_match('/^[1-9][0-9]*$/D', $maximumValue) !== 1
) {
    fail("Nextcloud-Supportbereich ist ungültig: {$minimumValue}/{$maximumValue}");
}

$minimum = (int) $minimumValue;
$maximum = (int) $maximumValue;
if ($minimum > $maximum) {
    fail("Nextcloud-Supportbereich ist ungültig: {$minimum}/{$maximum}");
}
if ($minimum > $floor) {
    fail("OpenDesk-Boden {$floor} ist nicht enthalten: deklarierter Bereich {$minimum}/{$maximum}");
}
if ($maximum < $target) {
    fail("Release-Ziel {$target} ist nicht enthalten: deklarierter Bereich {$minimum}/{$maximum}");
}
