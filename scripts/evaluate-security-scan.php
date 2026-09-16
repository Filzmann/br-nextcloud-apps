<?php

declare(strict_types=1);

/** @return array<string, string> */
function options(array $arguments): array
{
    $parsed = [];
    for ($index = 1, $count = count($arguments); $index < $count; $index += 2) {
        $name = $arguments[$index] ?? '';
        $value = $arguments[$index + 1] ?? null;
        if (!str_starts_with($name, '--') || $value === null) {
            throw new InvalidArgumentException('Optionen müssen als --name wert angegeben werden.');
        }
        $parsed[substr($name, 2)] = $value;
    }
    return $parsed;
}

/** @return array<string, mixed> */
function jsonObject(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Scannerbericht fehlt: {$path}");
    }
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException("Scannerbericht ist kein JSON-Objekt: {$path}");
    }
    return $decoded;
}

function relativePath(string $path, string $repositoryPath): string
{
    $normalized = str_replace('\\', '/', $path);
    $root = rtrim(str_replace('\\', '/', $repositoryPath), '/');
    if (str_starts_with($normalized, $root . '/')) {
        $normalized = substr($normalized, strlen($root) + 1);
    } elseif (str_starts_with($normalized, '/src/')) {
        $normalized = substr($normalized, 5);
    }
    $normalized = ltrim($normalized, './');
    if ($normalized === '' || str_starts_with($normalized, '/') || preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
        throw new RuntimeException("Scanner meldet einen ungültigen Pfad: {$path}");
    }
    return $normalized;
}

function findingContentHash(string $repositoryPath, string $path, int $startLine, int $endLine): string
{
    $root = realpath($repositoryPath);
    $file = realpath(rtrim($repositoryPath, '/') . '/' . $path);
    if ($root === false || $file === false || !is_file($file)
        || !str_starts_with($file, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException("Scannerfund verweist nicht auf eine reguläre Quelldatei: {$path}");
    }
    $lines = file($file);
    if ($lines === false || !isset($lines[$startLine - 1])) {
        throw new RuntimeException("Scannerfund verweist auf eine ungültige Quellzeile: {$path}:{$startLine}");
    }
    $endLine = max($startLine, $endLine);
    $content = implode('', array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    return hash('sha256', $content);
}

/** @return array{relation: string, groups: list<string>} */
function dependencyRelation(string $lockfile, string $ecosystem, string $packageName, array $groups): array
{
    $relation = 'transitive';
    if ($ecosystem === 'npm') {
        $lock = json_decode((string) @file_get_contents($lockfile), true);
        $root = is_array($lock) && is_array($lock['packages'][''] ?? null) ? $lock['packages'][''] : [];
        foreach (['dependencies', 'devDependencies', 'optionalDependencies', 'peerDependencies'] as $field) {
            if (array_key_exists($packageName, is_array($root[$field] ?? null) ? $root[$field] : [])) {
                $relation = 'direct';
                break;
            }
        }
    } elseif ($ecosystem === 'Packagist') {
        $composerPath = dirname($lockfile) . '/composer.json';
        $composer = json_decode((string) @file_get_contents($composerPath), true);
        foreach (['require', 'require-dev'] as $field) {
            if (is_array($composer[$field] ?? null) && array_key_exists($packageName, $composer[$field])) {
                $relation = 'direct';
                break;
            }
        }
    }
    return ['relation' => $relation, 'groups' => array_values(array_filter($groups, 'is_string'))];
}

function fixAvailable(array $vulnerabilities): bool
{
    foreach ($vulnerabilities as $vulnerability) {
        if (!is_array($vulnerability)) {
            continue;
        }
        foreach (($vulnerability['affected'] ?? []) as $affected) {
            foreach (($affected['ranges'] ?? []) as $range) {
                foreach (($range['events'] ?? []) as $event) {
                    if (is_array($event) && is_string($event['fixed'] ?? null) && $event['fixed'] !== '') {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

function osvSeverity(array $group, array $vulnerabilities): string
{
    $order = ['UNKNOWN' => 0, 'LOW' => 1, 'MODERATE' => 2, 'MEDIUM' => 2, 'HIGH' => 3, 'CRITICAL' => 4];
    $best = 'UNKNOWN';
    foreach ($vulnerabilities as $vulnerability) {
        $candidate = strtoupper((string) ($vulnerability['database_specific']['severity'] ?? 'UNKNOWN'));
        if (($order[$candidate] ?? 0) > ($order[$best] ?? 0)) {
            $best = $candidate;
        }
    }
    if ($best !== 'UNKNOWN') {
        return $best;
    }
    $score = (float) ($group['max_severity'] ?? 0);
    return match (true) {
        $score >= 9.0 => 'CRITICAL',
        $score >= 7.0 => 'HIGH',
        $score >= 4.0 => 'MEDIUM',
        $score > 0 => 'LOW',
        default => 'UNKNOWN',
    };
}

/** @return list<array<string, mixed>> */
function normalizeGitleaks(array $report, string $repository, string $repositoryPath): array
{
    $findings = [];
    foreach ($report as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = (string) ($entry['RuleID'] ?? 'unknown');
        $path = relativePath((string) ($entry['File'] ?? ''), $repositoryPath);
        $line = max(1, (int) ($entry['StartLine'] ?? 1));
        $endLine = max($line, (int) ($entry['EndLine'] ?? $line));
        $contentHash = findingContentHash($repositoryPath, $path, $line, $endLine);
        $findings[] = [
            'scanner' => 'gitleaks',
            'repository' => $repository,
            'path' => $path,
            'line' => $line,
            'finding_id' => $id,
            'severity' => 'HIGH',
            'content_hash' => $contentHash,
            'fingerprint' => hash('sha256', implode("\0", ['gitleaks', $repository, $path, $id, (string) $line, $contentHash])),
        ];
    }
    return $findings;
}

/**
 * @return array{
 *     findings: list<array<string, mixed>>,
 *     warnings: list<array<string, mixed>>,
 *     errors: list<array<string, mixed>>
 * }
 */
function normalizeSemgrep(array $report, string $repository, string $repositoryPath): array
{
    if (!isset($report['results'], $report['errors'])
        || !is_array($report['results'])
        || !is_array($report['errors'])) {
        throw new RuntimeException('Semgrep-Bericht besitzt keine vollständige Ergebnisstruktur.');
    }
    $findings = [];
    foreach ($report['results'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = (string) ($entry['check_id'] ?? 'unknown');
        $path = relativePath((string) ($entry['path'] ?? ''), $repositoryPath);
        $line = max(1, (int) ($entry['start']['line'] ?? 1));
        $endLine = max($line, (int) ($entry['end']['line'] ?? $line));
        $contentHash = findingContentHash($repositoryPath, $path, $line, $endLine);
        $findings[] = [
            'scanner' => 'semgrep',
            'repository' => $repository,
            'path' => $path,
            'line' => $line,
            'finding_id' => $id,
            'severity' => strtoupper((string) ($entry['extra']['severity'] ?? 'UNKNOWN')),
            'content_hash' => $contentHash,
            'fingerprint' => hash('sha256', implode("\0", ['semgrep', $repository, $path, $id, (string) $line, $contentHash])),
        ];
    }

    $warnings = [];
    $errors = [];
    foreach ($report['errors'] as $issue) {
        $rawLevel = is_array($issue) ? strtolower((string) ($issue['level'] ?? '')) : '';
        $rawType = is_array($issue) ? (string) ($issue['type'] ?? '') : '';
        $type = preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $rawType) ? $rawType : 'UnknownSemgrepIssue';
        $warning = in_array($rawLevel, ['warn', 'warning'], true);
        $normalizedIssue = [
            'scanner' => 'semgrep',
            'repository' => $repository,
            'level' => $warning ? 'warning' : 'error',
            'type' => $type,
            'reason' => $warning ? 'semgrep-analysis-warning' : 'semgrep-analysis-error',
        ];
        if (is_array($issue) && is_string($issue['path'] ?? null) && $issue['path'] !== '') {
            $normalizedIssue['path'] = relativePath($issue['path'], $repositoryPath);
        }
        if ($warning) {
            $warnings[] = $normalizedIssue;
        } else {
            $errors[] = $normalizedIssue;
        }
    }

    return ['findings' => $findings, 'warnings' => $warnings, 'errors' => $errors];
}

/** @return list<array<string, mixed>> */
function normalizeOsv(array $report, string $repository, string $repositoryPath, string $declaredSource): array
{
    $findings = [];
    foreach (($report['results'] ?? []) as $result) {
        if (!is_array($result)) {
            continue;
        }
        $inventorySource = str_ends_with($declaredSource, '.cdx.json');
        $reportedLockfile = (string) ($result['source']['path'] ?? '');
        $path = $inventorySource ? relativePath($declaredSource, $repositoryPath) : relativePath($reportedLockfile, $repositoryPath);
        $lockfile = $inventorySource ? '' : (str_starts_with(str_replace('\\', '/', $reportedLockfile), '/')
            ? $reportedLockfile
            : rtrim($repositoryPath, '/') . '/' . $path);
        foreach (($result['packages'] ?? []) as $packageResult) {
            if (!is_array($packageResult)) {
                continue;
            }
            $package = is_array($packageResult['package'] ?? null) ? $packageResult['package'] : [];
            $name = (string) ($package['name'] ?? 'unknown');
            $version = (string) ($package['version'] ?? 'unknown');
            $ecosystem = (string) ($package['ecosystem'] ?? 'unknown');
            $relation = $inventorySource
                ? ['relation' => 'direct', 'groups' => array_values(array_filter((array) ($packageResult['dependency_groups'] ?? []), 'is_string'))]
                : dependencyRelation($lockfile, $ecosystem, $name, (array) ($packageResult['dependency_groups'] ?? []));
            $vulnerabilities = is_array($packageResult['vulnerabilities'] ?? null) ? $packageResult['vulnerabilities'] : [];
            foreach (($packageResult['groups'] ?? []) as $group) {
                if (!is_array($group)) {
                    continue;
                }
                foreach (($group['ids'] ?? []) as $vulnerabilityId) {
                    if (!is_string($vulnerabilityId) || $vulnerabilityId === '') {
                        continue;
                    }
                    $reportedPurl = $package['purl'] ?? null;
                    $component = $inventorySource && is_string($reportedPurl) && $reportedPurl !== ''
                        ? $reportedPurl
                        : "{$ecosystem}:{$name}@{$version}";
                    $findings[] = [
                        'scanner' => 'osv-scanner',
                        'repository' => $repository,
                        'path' => $path,
                        'finding_id' => $vulnerabilityId,
                        'vulnerability' => $vulnerabilityId,
                        'severity' => osvSeverity($group, $vulnerabilities),
                        'component' => $component,
                        'dependency_relation' => $relation['relation'],
                        'dependency_groups' => $relation['groups'],
                        'fix_available' => fixAvailable($vulnerabilities),
                        'fingerprint' => hash('sha256', implode("\0", ['osv-scanner', $repository, $path, $vulnerabilityId, $component])),
                    ];
                }
            }
        }
    }
    return $findings;
}

/** @param array<string, mixed> $finding @param list<array<string, mixed>> $exceptions */
function exceptionFor(array $finding, array $exceptions): ?string
{
    foreach ($exceptions as $exception) {
        if (!is_array($exception)) {
            continue;
        }
        if (($exception['scanner'] ?? null) !== $finding['scanner']
            || ($exception['repository'] ?? null) !== $finding['repository']
            || ($exception['path'] ?? null) !== $finding['path']
            || ($finding['scanner'] !== 'osv-scanner' && ($exception['content_hash'] ?? null) !== $finding['content_hash'])
            || ($exception['fingerprint'] ?? null) !== $finding['fingerprint']) {
            continue;
        }
        $expectedId = $finding['scanner'] === 'osv-scanner' ? ($exception['vulnerability'] ?? null) : ($exception['finding_id'] ?? null);
        if ($expectedId !== $finding['finding_id']) {
            continue;
        }
        if ($finding['scanner'] === 'osv-scanner'
            && (($exception['severity'] ?? null) !== $finding['severity']
                || ($exception['component'] ?? null) !== $finding['component']
                || ($exception['dependency_relation'] ?? null) !== $finding['dependency_relation']
                || ($exception['fix_available'] ?? null) !== $finding['fix_available'])) {
            continue;
        }
        return (string) ($exception['id'] ?? '');
    }
    return null;
}

/** @param list<array<string, mixed>> $exceptions */
function validateRuntimeExceptions(array $exceptions, array $allowedScanners, string $kind): void
{
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $ids = [];
    $fingerprints = [];
    foreach ($exceptions as $exception) {
        if (!is_array($exception)) {
            throw new RuntimeException('Scanner-Ausnahme ist kein Objekt.');
        }
        foreach (['id', 'scanner', 'repository', 'path', 'fingerprint', 'review_on', 'expires_on'] as $field) {
            if (!is_string($exception[$field] ?? null) || trim($exception[$field]) === '') {
                throw new RuntimeException("Scanner-Ausnahme mit ungültigem Pflichtfeld: {$field}");
            }
        }
        if (isset($ids[$exception['id']]) || isset($fingerprints[$exception['fingerprint']])) {
            throw new RuntimeException('Scanner-Ausnahmen besitzen eine doppelte ID oder einen doppelten Fingerprint.');
        }
        $ids[$exception['id']] = true;
        $fingerprints[$exception['fingerprint']] = true;
        if (!in_array($exception['scanner'], $allowedScanners, true)
            || !preg_match('/^[a-f0-9]{64}$/', $exception['fingerprint'])) {
            throw new RuntimeException("Scanner-Ausnahme besitzt einen ungültigen Scanner oder Fingerprint: {$exception['id']}");
        }
        if (str_starts_with($exception['path'], '/')
            || preg_match('#(^|/)\.\.(/|$)#', str_replace('\\', '/', $exception['path']))
            || preg_match('/[\x00-\x1f\x7f]/', $exception['repository'] . $exception['path'])) {
            throw new RuntimeException("Scanner-Ausnahme besitzt einen ungültigen Repository- oder Dateipfad: {$exception['id']}");
        }
        if (!is_string($exception['reason'] ?? null) || trim($exception['reason']) === ''
            || !is_array($exception['compensating_controls'] ?? null)
            || $exception['compensating_controls'] === []
            || array_filter($exception['compensating_controls'], static fn (mixed $control): bool => !is_string($control) || trim($control) === '') !== []) {
            throw new RuntimeException("Scanner-Ausnahme besitzt keine belastbare Begründung oder Controls: {$exception['id']}");
        }
        if ($kind === 'analysis') {
            if (!is_string($exception['finding_id'] ?? null) || trim($exception['finding_id']) === ''
                || !is_string($exception['content_hash'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/', $exception['content_hash'])
                || !in_array($exception['classification'] ?? null, ['false-positive', 'risk-accepted'], true)
                || ($exception['scanner'] === 'gitleaks' && ($exception['classification'] ?? null) !== 'false-positive')) {
                throw new RuntimeException("Ungültige Analysis-Ausnahme: {$exception['id']}");
            }
        } elseif ($kind === 'vulnerability') {
            foreach (['vulnerability', 'severity', 'component', 'dependency_relation'] as $field) {
                if (!is_string($exception[$field] ?? null) || trim($exception[$field]) === '') {
                    throw new RuntimeException("Ungültige Vulnerability-Ausnahme: {$exception['id']}");
                }
            }
            if (!in_array($exception['dependency_relation'], ['direct', 'transitive', 'build-action', 'tooling'], true)
                || !is_bool($exception['fix_available'] ?? null)) {
                throw new RuntimeException("Ungültige Vulnerability-Ausnahme: {$exception['id']}");
            }
        } else {
            throw new LogicException('Unbekannte Scanner-Ausnahmeart.');
        }
        $review = DateTimeImmutable::createFromFormat('!Y-m-d', $exception['review_on'], new DateTimeZone('UTC'));
        $expires = DateTimeImmutable::createFromFormat('!Y-m-d', $exception['expires_on'], new DateTimeZone('UTC'));
        if ($review === false || $expires === false
            || $review->format('Y-m-d') !== $exception['review_on']
            || $expires->format('Y-m-d') !== $exception['expires_on']
            || $review < $today || $expires < $today || $review > $expires) {
            throw new RuntimeException("Scanner-Ausnahme ist abgelaufen oder zur Prüfung fällig: {$exception['id']}");
        }
    }
}

$options = options($argv);
foreach (['reports-manifest', 'inventories-manifest', 'output', 'tools', 'execution-mode', 'analysis-exceptions', 'vulnerability-exceptions'] as $required) {
    if (!isset($options[$required]) || trim($options[$required]) === '') {
        throw new InvalidArgumentException("Pflichtoption fehlt: --{$required}");
    }
}
if (!in_array($options['execution-mode'], ['production', 'test'], true)) {
    throw new InvalidArgumentException('execution-mode muss production oder test sein.');
}
$tools = jsonObject($options['tools']);
$analysisExceptions = array_values((array) (jsonObject($options['analysis-exceptions'])['exceptions'] ?? []));
$vulnerabilityExceptions = array_values((array) (jsonObject($options['vulnerability-exceptions'])['exceptions'] ?? []));
validateRuntimeExceptions($analysisExceptions, ['gitleaks', 'semgrep'], 'analysis');
validateRuntimeExceptions($vulnerabilityExceptions, ['osv-scanner'], 'vulnerability');

$inventoryHandle = fopen($options['inventories-manifest'], 'rb');
if ($inventoryHandle === false) {
    throw new RuntimeException('Third-Party-Inventar-Manifest kann nicht gelesen werden.');
}
$inventoryHeader = fgetcsv($inventoryHandle, null, "\t");
if ($inventoryHeader !== ['repository', 'inventory_path', 'purl', 'bundled_root', 'tree_sha256', 'file_count']) {
    throw new RuntimeException('Unbekanntes Third-Party-Inventar-Manifestformat.');
}
$thirdPartyComponents = [];
$thirdPartyKeys = [];
while (($row = fgetcsv($inventoryHandle, null, "\t")) !== false) {
    $entry = array_combine($inventoryHeader, $row);
    if ($entry === false || !preg_match('/^[a-f0-9]{64}$/', $entry['tree_sha256'])
        || !preg_match('/^[1-9][0-9]*$/', $entry['file_count'])) {
        throw new RuntimeException('Ungültige Third-Party-Inventar-Manifestzeile.');
    }
    $key = $entry['repository'] . "\0" . $entry['purl'];
    if (isset($thirdPartyKeys[$key])) {
        throw new RuntimeException('Doppelte Third-Party-Komponente im Scanner-Scope.');
    }
    $thirdPartyKeys[$key] = true;
    $thirdPartyComponents[] = [
        'repository' => $entry['repository'],
        'inventory_path' => $entry['inventory_path'],
        'purl' => $entry['purl'],
        'bundled_root' => $entry['bundled_root'],
        'tree_sha256' => $entry['tree_sha256'],
        'file_count' => (int) $entry['file_count'],
    ];
}
fclose($inventoryHandle);
usort($thirdPartyComponents, static fn (array $left, array $right): int => [$left['repository'], $left['purl']] <=> [$right['repository'], $right['purl']]);

$handle = fopen($options['reports-manifest'], 'rb');
if ($handle === false) {
    throw new RuntimeException('Scanner-Reportmanifest kann nicht gelesen werden.');
}
$header = fgetcsv($handle, null, "\t");
if ($header !== ['scanner', 'repository', 'repository_path', 'source', 'report', 'exit_code']) {
    throw new RuntimeException('Unbekanntes Scanner-Reportmanifestformat.');
}
$repositories = [];
$scannerRepositories = ['gitleaks' => [], 'semgrep' => [], 'osv-scanner' => []];
$scannerStats = [
    'gitleaks' => ['status' => 'passed', 'scanned_repositories' => 0, 'scanned_lockfiles' => 0, 'scanned_inventories' => 0, 'findings' => 0, 'accepted_findings' => 0, 'warnings' => 0],
    'semgrep' => ['status' => 'passed', 'scanned_repositories' => 0, 'scanned_lockfiles' => 0, 'scanned_inventories' => 0, 'findings' => 0, 'accepted_findings' => 0, 'warnings' => 0],
    'osv-scanner' => ['status' => 'passed', 'scanned_repositories' => 0, 'scanned_lockfiles' => 0, 'scanned_inventories' => 0, 'findings' => 0, 'accepted_findings' => 0, 'warnings' => 0],
];
$findings = [];
$warnings = [];
$errors = [];
while (($row = fgetcsv($handle, null, "\t")) !== false) {
    $entry = array_combine($header, $row);
    if ($entry === false || !isset($scannerStats[$entry['scanner']])) {
        throw new RuntimeException('Ungültige Scanner-Reportmanifestzeile.');
    }
    $scanner = $entry['scanner'];
    $repository = $entry['repository'];
    $repositories[$repository] = true;
    $scannerRepositories[$scanner][$repository] = true;
    if ($entry['source'] !== '.' && $scanner === 'osv-scanner') {
        if (str_ends_with($entry['source'], '.cdx.json')) {
            $scannerStats[$scanner]['scanned_inventories']++;
        } else {
            $scannerStats[$scanner]['scanned_lockfiles']++;
        }
    }
    $exitCode = (int) $entry['exit_code'];
    $expectedExit = $scanner === 'osv-scanner' ? in_array($exitCode, [0, 1], true) : $exitCode === 0;
    if (!$expectedExit) {
        $scannerStats[$scanner]['status'] = 'error';
        $errors[] = [
            'scanner' => $scanner,
            'repository' => $repository,
            'level' => 'error',
            'type' => 'ScannerExecutionError',
            'reason' => 'scanner-execution-failed',
        ];
        continue;
    }
    try {
        $report = jsonObject($entry['report']);
        $normalizedReport = match ($scanner) {
            'gitleaks' => ['findings' => normalizeGitleaks($report, $repository, $entry['repository_path']), 'warnings' => [], 'errors' => []],
            'semgrep' => normalizeSemgrep($report, $repository, $entry['repository_path']),
            'osv-scanner' => ['findings' => normalizeOsv($report, $repository, $entry['repository_path'], $entry['source']), 'warnings' => [], 'errors' => []],
        };
    } catch (Throwable) {
        $scannerStats[$scanner]['status'] = 'error';
        $errors[] = [
            'scanner' => $scanner,
            'repository' => $repository,
            'level' => 'error',
            'type' => 'InvalidOrIncompleteReport',
            'reason' => 'invalid-or-incomplete-report',
        ];
        continue;
    }
    foreach ($normalizedReport['warnings'] as $warning) {
        $warnings[] = $warning;
        $scannerStats[$scanner]['warnings']++;
        if ($scannerStats[$scanner]['status'] === 'passed') {
            $scannerStats[$scanner]['status'] = 'partial';
        }
    }
    foreach ($normalizedReport['errors'] as $error) {
        $errors[] = $error;
        $scannerStats[$scanner]['status'] = 'error';
    }
    foreach ($normalizedReport['findings'] as $finding) {
        $exceptionId = exceptionFor($finding, $scanner === 'osv-scanner' ? $vulnerabilityExceptions : $analysisExceptions);
        $finding['accepted'] = $exceptionId !== null;
        if ($exceptionId !== null) {
            $finding['exception_id'] = $exceptionId;
            $scannerStats[$scanner]['accepted_findings']++;
        } else {
            $scannerStats[$scanner]['findings']++;
            if ($scannerStats[$scanner]['status'] !== 'error') {
                $scannerStats[$scanner]['status'] = 'failed';
            }
        }
        $findings[] = $finding;
    }
}
fclose($handle);

foreach ($scannerRepositories as $scanner => $scannedRepositories) {
    $scannerStats[$scanner]['scanned_repositories'] = count($scannedRepositories);
}

ksort($repositories);
usort($findings, static fn (array $left, array $right): int => [
    $left['scanner'], $left['repository'], $left['path'], $left['finding_id'], $left['fingerprint'],
] <=> [
    $right['scanner'], $right['repository'], $right['path'], $right['finding_id'], $right['fingerprint'],
]);
$failed = $errors !== [] || $warnings !== []
    || array_filter($findings, static fn (array $finding): bool => $finding['accepted'] !== true) !== [];
$diagnostic = $options['execution-mode'] === 'test';
$evidence = [
    'schema_version' => 1,
    'generated_at' => gmdate(DateTimeInterface::ATOM),
    'status' => $failed ? 'failed' : ($diagnostic ? 'diagnostic' : 'passed'),
    'execution' => [
        'mode' => $diagnostic ? 'diagnostic' : 'production',
        'test_doubles' => $diagnostic,
        'publishable' => !$diagnostic,
    ],
    'scope' => [
        'repositories' => array_keys($repositories),
        'git_history_scanned' => false,
        'third_party_components' => $thirdPartyComponents,
    ],
    'tools' => [
        'gitleaks' => ['version' => $tools['tools']['gitleaks']['version'], 'integrity' => 'sha256-pinned'],
        'osv-scanner' => ['version' => $tools['tools']['osv-scanner']['version'], 'integrity' => 'sha256-pinned'],
        'semgrep' => ['version' => $tools['tools']['semgrep']['version'], 'integrity' => $tools['tools']['semgrep']['image_digest']],
    ],
    'scanners' => $scannerStats,
    'findings' => $findings,
    'warnings' => $warnings,
    'errors' => $errors,
];
$encoded = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (file_put_contents($options['output'], $encoded) === false) {
    throw new RuntimeException('Scanner-Evidence kann nicht geschrieben werden.');
}
exit($failed ? 1 : 0);
