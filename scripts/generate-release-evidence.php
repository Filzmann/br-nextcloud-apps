<?php

declare(strict_types=1);

/** @return array<string, string> */
function parseOptions(array $arguments): array
{
    $result = [];
    for ($index = 1, $count = count($arguments); $index < $count; $index += 2) {
        $key = $arguments[$index] ?? '';
        $value = $arguments[$index + 1] ?? null;
        if (!str_starts_with($key, '--') || $value === null) {
            throw new InvalidArgumentException('Optionen müssen als --name wert angegeben werden.');
        }
        $result[substr($key, 2)] = $value;
    }
    return $result;
}

$options = parseOptions($argv);
foreach (['manifest', 'output', 'release-id', 'source-timestamp', 'mapping-status', 'test-status', 'release-mode', 'dirty-sources', 'security-scan-evidence', 'sbom-file', 'sbom-sha256', 'artifact-file', 'artifact-sha256'] as $required) {
    if (!isset($options[$required]) || trim($options[$required]) === '') {
        throw new InvalidArgumentException("Pflichtoption fehlt: --{$required}");
    }
}
foreach (['sbom-sha256', 'artifact-sha256'] as $hashField) {
    if (!preg_match('/^[a-f0-9]{64}$/', $options[$hashField])) {
        throw new InvalidArgumentException("Ungültiger SHA-256: --{$hashField}");
    }
}
if (DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $options['source-timestamp']) === false) {
    throw new InvalidArgumentException('source-timestamp ist kein ISO-8601-Zeitpunkt.');
}
if (!in_array($options['mapping-status'], ['passed', 'skipped'], true)) {
    throw new InvalidArgumentException('mapping-status muss passed oder skipped sein.');
}
if (!in_array($options['test-status'], ['passed-by-builder', 'skipped-by-builder'], true)) {
    throw new InvalidArgumentException('test-status muss passed-by-builder oder skipped-by-builder sein.');
}
if (!in_array($options['release-mode'], ['candidate', 'diagnostic'], true)) {
    throw new InvalidArgumentException('release-mode muss candidate oder diagnostic sein.');
}
if (!in_array($options['dirty-sources'], ['true', 'false'], true)) {
    throw new InvalidArgumentException('dirty-sources muss true oder false sein.');
}
$dirtySources = $options['dirty-sources'] === 'true';
if ($dirtySources && $options['release-mode'] !== 'diagnostic') {
    throw new InvalidArgumentException('Dirty Sources sind nur im Diagnosemodus zulässig.');
}

$securityScanEvidence = json_decode((string) file_get_contents($options['security-scan-evidence']), true, 512, JSON_THROW_ON_ERROR);
if (is_array($securityScanEvidence)
    && (($securityScanEvidence['execution']['mode'] ?? null) !== 'production'
        || ($securityScanEvidence['execution']['test_doubles'] ?? null) !== false
        || ($securityScanEvidence['execution']['publishable'] ?? null) !== true)) {
    throw new RuntimeException('Diagnostische Security-Scanner-Evidence ist nicht releasefähig.');
}
if (!is_array($securityScanEvidence)
    || ($securityScanEvidence['schema_version'] ?? null) !== 1
    || ($securityScanEvidence['status'] ?? null) !== 'passed'
    || ($securityScanEvidence['warnings'] ?? null) !== []
    || ($securityScanEvidence['errors'] ?? null) !== []
    || !is_array($securityScanEvidence['scanners'] ?? null)
    || !is_array($securityScanEvidence['scope']['repositories'] ?? null)
    || !is_array($securityScanEvidence['scope']['third_party_components'] ?? null)
    || ($securityScanEvidence['scope']['git_history_scanned'] ?? null) !== false
    || !is_array($securityScanEvidence['findings'] ?? null)) {
    throw new RuntimeException('Nur vollständig bestandene Security-Scanner-Evidence darf in Release-Evidence eingehen.');
}
$scannedRepositories = $securityScanEvidence['scope']['repositories'];
if ($scannedRepositories === []
    || array_values(array_unique($scannedRepositories, SORT_STRING)) !== $scannedRepositories
    || array_filter($scannedRepositories, static fn (mixed $repository): bool => !is_string($repository) || trim($repository) === '') !== []) {
    throw new RuntimeException('Security-Scanner-Evidence besitzt keinen eindeutigen Repository-Scope.');
}
$acceptedFindingCount = 0;
$thirdPartyInventories = [];
foreach ($securityScanEvidence['scope']['third_party_components'] as $component) {
    if (!is_array($component)
        || !is_string($component['repository'] ?? null) || trim($component['repository']) === ''
        || !is_string($component['inventory_path'] ?? null) || trim($component['inventory_path']) === ''
        || !is_string($component['purl'] ?? null) || !str_starts_with($component['purl'], 'pkg:')
        || !is_string($component['tree_sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/', $component['tree_sha256'])
        || !is_int($component['file_count'] ?? null) || $component['file_count'] < 1) {
        throw new RuntimeException('Security-Scanner-Evidence besitzt ungültige Third-Party-Coverage.');
    }
    $thirdPartyInventories[$component['repository'] . "\0" . $component['inventory_path']] = true;
}
foreach (['gitleaks', 'semgrep', 'osv-scanner'] as $scanner) {
    $scannerEvidence = $securityScanEvidence['scanners'][$scanner] ?? null;
    if (!is_array($scannerEvidence) || ($scannerEvidence['status'] ?? null) !== 'passed') {
        throw new RuntimeException("Security-Scanner ist nicht bestanden: {$scanner}");
    }
    if (($scannerEvidence['scanned_repositories'] ?? null) !== count($scannedRepositories)) {
        throw new RuntimeException("Security-Scanner-Scope ist unvollständig: {$scanner}");
    }
    foreach (['scanned_lockfiles', 'scanned_inventories', 'findings', 'accepted_findings', 'warnings'] as $counter) {
        if (!is_int($scannerEvidence[$counter] ?? null) || $scannerEvidence[$counter] < 0) {
            throw new RuntimeException("Security-Scanner-Zähler ist ungültig: {$scanner}/{$counter}");
        }
    }
    if ($scannerEvidence['findings'] !== 0) {
        throw new RuntimeException("Security-Scanner enthält nicht akzeptierte Funde: {$scanner}");
    }
    if ($scannerEvidence['warnings'] !== 0) {
        throw new RuntimeException("Security-Scanner ist wegen Analysewarnungen nur teilweise bestanden: {$scanner}");
    }
    $acceptedFindingCount += $scannerEvidence['accepted_findings'];
    $toolEvidence = $securityScanEvidence['tools'][$scanner] ?? null;
    if (!is_array($toolEvidence)
        || !is_string($toolEvidence['version'] ?? null) || trim($toolEvidence['version']) === ''
        || !is_string($toolEvidence['integrity'] ?? null) || trim($toolEvidence['integrity']) === '') {
        throw new RuntimeException("Security-Scanner-Provenienz fehlt: {$scanner}");
    }
}
if (($securityScanEvidence['scanners']['osv-scanner']['scanned_inventories'] ?? null) !== count($thirdPartyInventories)) {
    throw new RuntimeException('OSV-Scanner-Coverage der Third-Party-Inventare ist unvollständig.');
}
if (count($securityScanEvidence['findings']) !== $acceptedFindingCount
    || array_filter($securityScanEvidence['findings'], static fn (mixed $finding): bool => !is_array($finding) || ($finding['accepted'] ?? null) !== true) !== []) {
    throw new RuntimeException('Bestandene Scanner-Evidence enthält inkonsistente Findings.');
}

$handle = fopen($options['manifest'], 'rb');
if ($handle === false) {
    throw new RuntimeException('Release-Manifest kann nicht gelesen werden.');
}
$header = fgetcsv($handle, null, "\t");
if ($header !== ['app', 'version', 'git_commit', 'sha256', 'signed']) {
    throw new RuntimeException('Unbekanntes Release-Manifestformat.');
}
$sources = [];
while (($row = fgetcsv($handle, null, "\t")) !== false) {
    $entry = array_combine($header, $row);
    if ($entry === false || !preg_match('/^[a-f0-9]{40}$/', $entry['git_commit'])) {
        throw new RuntimeException('Ungültige Manifestzeile.');
    }
    $sources[] = [
        'app' => $entry['app'],
        'version' => $entry['version'],
        'git_commit' => $entry['git_commit'],
        'artifact_sha256' => $entry['sha256'],
        'nextcloud_signed' => $entry['signed'],
    ];
}
fclose($handle);

$workspace = dirname(__DIR__);
$mappingPath = $workspace . '/security-compliance/bsi-mapping.json';
$threatPath = $workspace . '/security-compliance/threat-model.json';
$evidence = [
    'schema_version' => 1,
    'release_id' => $options['release-id'],
    'source_timestamp' => $options['source-timestamp'],
    'sources' => $sources,
    'build_context' => [
        'release_mode' => $options['release-mode'],
        'dirty_sources' => $dirtySources,
        'publishable' => false,
        'release_gate' => 'not-evaluated-by-builder',
    ],
    'tests' => [
        'builder' => $options['test-status'],
        'delivery_gate' => 'not-evaluated-by-builder',
    ],
    'security' => [
        'mapping_consistency' => $options['mapping-status'],
        'sast' => 'passed',
        'dependency_scan' => 'passed',
        'vulnerability_scan' => 'passed',
        'secret_scan' => 'passed',
        'scanner_evidence' => [
            'sha256' => hash_file('sha256', $options['security-scan-evidence']),
            'scope' => $securityScanEvidence['scope'],
            'tools' => $securityScanEvidence['tools'],
            'scanners' => $securityScanEvidence['scanners'],
            'warnings' => $securityScanEvidence['warnings'],
            'errors' => $securityScanEvidence['errors'],
        ],
    ],
    'sbom' => ['file' => $options['sbom-file'], 'sha256' => $options['sbom-sha256'], 'format' => 'CycloneDX-1.6'],
    'artifact' => ['file' => $options['artifact-file'], 'sha256' => $options['artifact-sha256']],
    'compliance' => [
        'mapping_file' => 'security-compliance/bsi-mapping.json',
        'mapping_sha256' => hash_file('sha256', $mappingPath),
        'threat_model_file' => 'security-compliance/threat-model.json',
        'threat_model_sha256' => hash_file('sha256', $threatPath),
        'certification' => 'not-certified',
    ],
];

$encoded = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if (file_put_contents($options['output'], $encoded) === false) {
    throw new RuntimeException("Release-Evidence kann nicht geschrieben werden: {$options['output']}");
}
