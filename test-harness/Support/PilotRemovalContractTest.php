<?php

declare(strict_types=1);

// Rückbaunachweis: Die Laufzeit-Pilotkomponenten der Kategorie-B-App sind entfernt;
// das Repository bleibt als Test-Harness (tests/) und leere Compat-Stage-App bestehen.

$root = dirname(__DIR__, 2);

$removedPaths = [
    'lib',
    'appinfo/routes.php',
    'templates',
    'js',
    'css',
    'img',
    'l10n',
    'resources',
    'docs/architecture.md',
    'docs/l10n-inventory.md',
    'docs/manual-acceptance.md',
    'tests/Controller',
    'tests/Model',
    'tests/Service',
    'tests/Ui',
];
foreach ($removedPaths as $path) {
    if (file_exists($root . '/' . $path)) {
        throw new RuntimeException("Pilot-Pfad darf nicht mehr existieren: {$path}");
    }
}

// Pilot-JS-Smokes sind entfernt; die Harness-Hilfe fake-dom.js bleibt für Verbraucher-Tests erhalten.
foreach (glob($root . '/tests/js/*') ?: [] as $entry) {
    if (basename($entry) !== 'helpers') {
        throw new RuntimeException('tests/js darf nur noch helpers/ enthalten: ' . basename($entry));
    }
}
foreach (glob($root . '/tests/js/helpers/*') ?: [] as $helper) {
    if (basename($helper) !== 'fake-dom.js') {
        throw new RuntimeException('Unerwartete Harness-Datei: tests/js/helpers/' . basename($helper));
    }
}

$info = (string)file_get_contents($root . '/appinfo/info.xml');
foreach (['<background-jobs>', '<settings>', '<navigations>', '<repair-steps>', '<commands>', '<routes'] as $registration) {
    if (str_contains($info, $registration)) {
        throw new RuntimeException("Pilot-Registrierung darf nicht mehr in info.xml stehen: {$registration}");
    }
}
foreach (['<id>localbase</id>', '<namespace>LocalBase</namespace>', '<licence>agpl</licence>'] as $required) {
    if (!str_contains($info, $required)) {
        throw new RuntimeException("Minimaler App-Vertrag fehlt in info.xml: {$required}");
    }
}

$harnessPaths = [
    'tests/Support/PhpTestRunner.php',
    'tests/Support/assertions.php',
    'tests/Support/js-runner.mjs',
    'tests/js/helpers/fake-dom.js',
    'tests/coverage/merge-clover.php',
    'tests/coverage/package.json',
    'tests/coverage/composer.json',
    'tests/run.php',
    'tests/run-js.mjs',
    'tests/bootstrap.php',
    '.github/workflows/tests.yml',
];
foreach ($harnessPaths as $path) {
    if (!is_file($root . '/' . $path)) {
        throw new RuntimeException("Test-Harness-Datei fehlt: {$path}");
    }
}

$workflow = (string)file_get_contents($root . '/.github/workflows/tests.yml');
foreach (['id: consumer-ref', 'Filzmann/nextcloud-orgsuite', 'Filzmann/nextcloud-flz-organization'] as $contract) {
    if (!str_contains($workflow, $contract)) {
        throw new RuntimeException("Consumer-Vertragslauf fehlt im Workflow: {$contract}");
    }
}

echo "PilotRemovalContractTest: OK\n";
