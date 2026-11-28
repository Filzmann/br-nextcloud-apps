<?php

declare(strict_types=1);

$temporaryBase = tempnam(sys_get_temp_dir(), 'localbase-coverage-');
if ($temporaryBase === false) {
    throw new RuntimeException('Temporärer Coverage-Pfad konnte nicht erzeugt werden.');
}
unlink($temporaryBase);
if (!mkdir($temporaryBase, 0775, true) && !is_dir($temporaryBase)) {
    throw new RuntimeException('Temporäres Coverage-Verzeichnis konnte nicht erzeugt werden.');
}

$report = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<coverage>
  <project>
    <file name="/synthetic/example.php">
      <line num="1" type="stmt" count="1"/>
      <line num="2" type="stmt" count="0"/>
    </file>
  </project>
</coverage>
XML;
file_put_contents($temporaryBase . '/fixture.xml', $report);

$tool = dirname(__DIR__) . '/coverage/merge-clover.php';
$run = static function (string $minimum) use ($tool, $temporaryBase): array {
    $command = implode(' ', array_map('escapeshellarg', [
        PHP_BINARY,
        $tool,
        'fixture',
        $temporaryBase,
        $minimum,
    ]));
    exec($command . ' 2>&1', $output, $exitCode);
    return [$exitCode, implode("\n", $output)];
};

try {
    [$passingStatus, $passingOutput] = $run('50.00');
    if ($passingStatus !== 0 || !str_contains($passingOutput, "fixture\t2\t1\t50.00")) {
        throw new RuntimeException('Exakt erreichte PHP-Coverage-Schwelle wird nicht akzeptiert.');
    }

    [$failingStatus, $failingOutput] = $run('50.01');
    if ($failingStatus === 0 || !str_contains($failingOutput, '50.00 % liegt unter 50.01 %')) {
        throw new RuntimeException('PHP-Coverage-Rückgang wird nicht mit Ist- und Sollwert abgelehnt.');
    }
    if (!str_contains($failingOutput, 'Nicht abgedeckt: /synthetic/example.php:2')) {
        throw new RuntimeException('PHP-Coverage-Rückgang nennt die nicht abgedeckten Zeilen nicht.');
    }
} finally {
    unlink($temporaryBase . '/fixture.xml');
    rmdir($temporaryBase);
}

echo "LocalBase PHP coverage threshold smoke test passed\n";
