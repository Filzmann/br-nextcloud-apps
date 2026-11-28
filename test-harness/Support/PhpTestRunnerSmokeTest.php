<?php

declare(strict_types=1);


use OCA\LocalBase\Tests\Support\PhpTestRunner;

$root = dirname(__DIR__, 2);
$originalCoverageCommand = getenv('PHP_COVERAGE_COMMAND');
$originalCoverageOutputDirectory = getenv('PHP_COVERAGE_OUTPUT_DIR');
$repositoryFixture = $root . '/tests/.runner-smoke';
$temporaryDirectory = null;
$usedTemporaryDirectory = null;
$removeTree = static function (?string $directory): void {
    if ($directory === null || !file_exists($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($directory);
};
register_shutdown_function(static function () use (&$temporaryDirectory, $removeTree): void {
    $removeTree($temporaryDirectory);
});

try {
    if (file_exists($repositoryFixture)) {
        throw new RuntimeException('Repository enthält vor dem Smoke-Test ein unerwartetes Runner-Fixture.');
    }

    putenv('PHP_COVERAGE_COMMAND');
    putenv('PHP_COVERAGE_OUTPUT_DIR');
    $files = PhpTestRunner::collect($root, ['tests/Support'], static fn(string $path): bool => str_ends_with($path, 'SmokeTest.php'));
    $names = array_map('basename', $files);
    if (!in_array('PhpTestRunnerSmokeTest.php', $names, true) || !in_array('AssertionsSmokeTest.php', $names, true)) {
        throw new RuntimeException('PHP-Test-Runner sammelt rekursive Smoke-Tests nicht deterministisch ein.');
    }
    if ($names !== array_values(array_unique($names))) throw new RuntimeException('PHP-Test-Runner liefert Testdateien mehrfach.');

    $temporarySeed = tempnam(sys_get_temp_dir(), 'localbase-runner-');
    if ($temporarySeed === false || !unlink($temporarySeed) || !mkdir($temporarySeed, 0775)) {
        throw new RuntimeException('Eindeutiges System-Temp-Verzeichnis konnte nicht angelegt werden.');
    }
    $temporaryDirectory = $temporarySeed;
    $usedTemporaryDirectory = $temporaryDirectory;

    $vendorFixture = $temporaryDirectory . '/fixture/vendor/example';
    $projectFixture = $temporaryDirectory . '/fixture/project';
    if (!mkdir($vendorFixture, 0775, true) || !mkdir($projectFixture, 0775, true)) {
        throw new RuntimeException('Temporäre Runner-Fixtures konnten nicht angelegt werden.');
    }
    if (file_put_contents($vendorFixture . '/Ignored.php', '<?php') === false
        || file_put_contents($projectFixture . '/Included.php', '<?php') === false) {
        throw new RuntimeException('Temporäre Runner-Fixtures konnten nicht geschrieben werden.');
    }
    $collected = PhpTestRunner::collect($temporaryDirectory, ['fixture'], static fn(string $path): bool => str_ends_with($path, '.php'));
    if ($collected !== [$projectFixture . '/Included.php']) {
        throw new RuntimeException('PHP-Test-Runner darf Fremdabhängigkeiten unter vendor nicht sammeln.');
    }

    $normalCommand = [PHP_BINARY, 'tests/example.php'];
    if (PhpTestRunner::withOptionalCoverage($root, $normalCommand) !== $normalCommand) {
        throw new RuntimeException('Coverage darf ohne explizite Umgebung nicht aktiv sein.');
    }
    $coverageFixture = $temporaryDirectory . '/coverage';
    $fakeTool = $coverageFixture . '/phpcov';
    if (!mkdir($coverageFixture, 0775, true)
        || file_put_contents($fakeTool, "#!/bin/sh\n") === false
        || !chmod($fakeTool, 0755)) {
        throw new RuntimeException('Coverage-Testumgebung konnte nicht angelegt werden.');
    }
    putenv("PHP_COVERAGE_COMMAND={$fakeTool}");
    putenv("PHP_COVERAGE_OUTPUT_DIR={$coverageFixture}/reports");
    $coverageCommand = PhpTestRunner::withOptionalCoverage($root, $normalCommand);
    if (($coverageCommand[0] ?? '') !== $fakeTool || !in_array('--clover', $coverageCommand, true) || !in_array($root . '/lib', $coverageCommand, true)) {
        throw new RuntimeException('PHP-Test-Runner reicht Test und Quellfilter nicht korrekt an PHPCOV weiter.');
    }

    $isolatedRoot = $temporaryDirectory . '/isolated-project';
    if (!mkdir($isolatedRoot . '/lib', 0775, true)
        || !mkdir($isolatedRoot . '/tests', 0775, true)
        || file_put_contents($isolatedRoot . '/tests/collision.php', '<?php $arguments = [];') === false) {
        throw new RuntimeException('Coverage-Isolationsfixture konnte nicht angelegt werden.');
    }
    $isolatedCommand = PhpTestRunner::withOptionalCoverage(
        $isolatedRoot,
        [PHP_BINARY, 'tests/collision.php'],
    );
    $coverageScript = (string)end($isolatedCommand);
    $coverageScriptPath = str_starts_with($coverageScript, '/')
        ? $coverageScript
        : $isolatedRoot . '/' . $coverageScript;
    $preservesCallerScope = static function (string $script): bool {
        $arguments = new stdClass();
        require $script;
        return $arguments instanceof stdClass;
    };
    if (!$preservesCallerScope($coverageScriptPath)) {
        throw new RuntimeException('Coverage-Testdatei überschreibt weiterhin interne PHPCOV-Variablen.');
    }
} finally {
    if ($originalCoverageCommand === false) putenv('PHP_COVERAGE_COMMAND');
    else putenv('PHP_COVERAGE_COMMAND=' . $originalCoverageCommand);
    if ($originalCoverageOutputDirectory === false) putenv('PHP_COVERAGE_OUTPUT_DIR');
    else putenv('PHP_COVERAGE_OUTPUT_DIR=' . $originalCoverageOutputDirectory);
    $removeTree($temporaryDirectory);
    $temporaryDirectory = null;
}

if (file_exists($repositoryFixture)) throw new RuntimeException('Smoke-Test hat ein Fixture im Repository hinterlassen.');
if ($usedTemporaryDirectory === null || file_exists($usedTemporaryDirectory)) throw new RuntimeException('Smoke-Test hat Reste im System-Temp-Verzeichnis hinterlassen.');

echo "LocalBase PHP runner smoke test passed\n";
