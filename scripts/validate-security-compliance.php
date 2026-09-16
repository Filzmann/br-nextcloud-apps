<?php

declare(strict_types=1);

$workspace = dirname(__DIR__);
$paths = [
    'scope' => $workspace . '/security-compliance/scope.json',
    'mapping' => $workspace . '/security-compliance/bsi-mapping.json',
    'threat-model' => $workspace . '/security-compliance/threat-model.json',
    'exceptions' => $workspace . '/security-compliance/vulnerability-exceptions.json',
    'analysis-exceptions' => $workspace . '/security-compliance/analysis-exceptions.json',
    'scanner-tools' => $workspace . '/security-compliance/scanner-tools.json',
];
$changedFiles = [];

for ($index = 1; $index < $argc; $index++) {
    $argument = $argv[$index];
    if ($argument === '--changed-file') {
        $changedFiles[] = $argv[++$index] ?? throw new InvalidArgumentException('--changed-file braucht einen Pfad.');
        continue;
    }
    if (str_starts_with($argument, '--')) {
        $key = substr($argument, 2);
        if (!array_key_exists($key, $paths)) {
            throw new InvalidArgumentException("Unbekannte Option: {$argument}");
        }
        $paths[$key] = $argv[++$index] ?? throw new InvalidArgumentException("{$argument} braucht einen Pfad.");
        continue;
    }
    throw new InvalidArgumentException("Unerwartetes Argument: {$argument}");
}

/** @return array<string, mixed> */
function readJson(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Pflichtdatei fehlt: {$path}");
    }
    $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException("JSON-Wurzel ist kein Objekt: {$path}");
    }
    return $decoded;
}

/** @param list<string> $references */
function validateReferences(string $workspace, array $references): void
{
    foreach ($references as $reference) {
        if ($reference === '' || str_starts_with($reference, '/') || str_contains($reference, '..')) {
            throw new RuntimeException("Ungültige Repositoryreferenz: {$reference}");
        }
        if (!file_exists($workspace . '/' . $reference)) {
            throw new RuntimeException("Referenz existiert nicht: {$reference}");
        }
    }
}

/** @param mixed $value @return list<string> */
function stringList(mixed $value, string $label, bool $allowEmpty = false): array
{
    if (!is_array($value) || (!$allowEmpty && $value === [])) {
        throw new RuntimeException("{$label} muss eine nichtleere Liste sein.");
    }
    foreach ($value as $entry) {
        if (!is_string($entry) || trim($entry) === '') {
            throw new RuntimeException("{$label} enthält keinen gültigen String.");
        }
    }
    return array_values($value);
}

function canonicalDate(mixed $value, string $field): DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value)) {
        $rendered = is_scalar($value) ? (string) $value : get_debug_type($value);
        throw new RuntimeException("Nicht kanonisches Ausnahmedatum {$field}: {$rendered}");
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
    if ($date === false || $date->format('Y-m-d') !== $value) {
        throw new RuntimeException("Nicht kanonisches Ausnahmedatum {$field}: {$value}");
    }
    return $date;
}

$scope = readJson($paths['scope']);
$mapping = readJson($paths['mapping']);
$threatModel = readJson($paths['threat-model']);
$exceptions = readJson($paths['exceptions']);
$analysisExceptions = readJson($paths['analysis-exceptions']);
$scannerTools = readJson($paths['scanner-tools']);

if (array_key_exists('product_sets', $scope)) {
    throw new RuntimeException('Produktmengen und Runtime-Abhängigkeiten müssen aus ihren kanonischen Quellen abgeleitet werden.');
}
validateReferences($workspace, array_values($scope['sources'] ?? []));

$inventoryApps = [];
$manifest = fopen($workspace . '/config/workspace-repositories.tsv', 'rb');
if ($manifest === false) {
    throw new RuntimeException('Repositoryinventar kann nicht gelesen werden.');
}
while (($row = fgetcsv($manifest, null, "\t")) !== false) {
    if (($row[0] ?? '') === 'path' || ($row[1] ?? '') !== 'app') {
        continue;
    }
    $inventoryApps[] = (string) ($row[2] ?? '');
}
fclose($manifest);
sort($inventoryApps);

$scopeApps = [];
foreach (($scope['applications'] ?? []) as $application) {
    if (!is_array($application) || !isset($application['id'], $application['repository'])) {
        throw new RuntimeException('Ungültiger Application-Eintrag im Security-Scope.');
    }
    $id = (string) $application['id'];
    $repository = (string) $application['repository'];
    if (array_key_exists('runtime_dependencies', $application)) {
        throw new RuntimeException('Produktmengen und Runtime-Abhängigkeiten müssen aus ihren kanonischen Quellen abgeleitet werden.');
    }
    if ($id === '' || $repository === '' || !is_dir($workspace . '/' . $repository)) {
        throw new RuntimeException("Ungültiges oder fehlendes App-Repository im Scope: {$id}");
    }
    if (isset($scopeApps[$id])) {
        throw new RuntimeException("Doppelte App im Security-Scope: {$id}");
    }
    $scopeApps[$id] = $repository;
}
$scopeIds = array_keys($scopeApps);
sort($scopeIds);
if ($scopeIds !== $inventoryApps) {
    throw new RuntimeException('Scope und Repositoryinventar weichen voneinander ab.');
}
if (($scope['certification']['status'] ?? null) !== 'not-certified') {
    throw new RuntimeException('Die Zertifizierungsgrenze muss explizit not-certified bleiben.');
}
$repositoryRoots = ['parent' => $workspace];
foreach ($scopeApps as $appId => $repository) {
    $repositoryRoots[$appId] = $workspace . '/' . $repository;
}

if (($scannerTools['schema_version'] ?? null) !== 1 || array_keys($scannerTools['tools'] ?? []) !== ['gitleaks', 'osv-scanner', 'semgrep']) {
    throw new RuntimeException('Scanner-Toolmanifest besitzt kein unterstütztes Schema oder Toolset.');
}
foreach (['gitleaks', 'osv-scanner'] as $toolName) {
    $tool = $scannerTools['tools'][$toolName];
    if (!is_array($tool) || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', (string) ($tool['version'] ?? ''))) {
        throw new RuntimeException("Ungültige gepinnte Toolversion: {$toolName}");
    }
    if (!str_starts_with((string) ($tool['source'] ?? ''), 'https://github.com/')) {
        throw new RuntimeException("Ungültige Toolquelle: {$toolName}");
    }
    foreach (['linux-amd64', 'linux-arm64'] as $platform) {
        $pin = $tool['platforms'][$platform] ?? null;
        if (!is_array($pin)
            || !str_starts_with((string) ($pin['url'] ?? ''), 'https://github.com/')
            || !preg_match('/^[a-f0-9]{64}$/', (string) ($pin['sha256'] ?? ''))
            || !in_array($pin['archive'] ?? null, ['none', 'tar.gz'], true)) {
            throw new RuntimeException("Ungültiger Toolpin: {$toolName}/{$platform}");
        }
    }
}
$semgrepTool = $scannerTools['tools']['semgrep'];
if (!is_array($semgrepTool)
    || !preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', (string) ($semgrepTool['version'] ?? ''))
    || !preg_match('#^docker\.io/semgrep/semgrep:[0-9]+\.[0-9]+\.[0-9]+-nonroot$#', (string) ($semgrepTool['image'] ?? ''))
    || !preg_match('/^sha256:[a-f0-9]{64}$/', (string) ($semgrepTool['image_digest'] ?? ''))) {
    throw new RuntimeException('Ungültiger Semgrep-Image-Pin.');
}
if (!is_file($workspace . '/security-compliance/semgrep-rules.yml')) {
    throw new RuntimeException('Lokale Semgrep-Regeln fehlen.');
}
foreach (['gitleaks.toml', 'gitleaks-ignore.txt', 'osv-scanner.toml'] as $scannerConfig) {
    if (!is_file($workspace . '/security-compliance/' . $scannerConfig)) {
        throw new RuntimeException("Zentrale Scannerkonfiguration fehlt: {$scannerConfig}");
    }
}
$gitleaksConfig = (string) file_get_contents($workspace . '/security-compliance/gitleaks.toml');
if (!preg_match('/^\s*useDefault\s*=\s*true\s*$/m', $gitleaksConfig)
    || preg_match('/\[\[?allowlist\]?\]|regexes\s*=|paths\s*=|stopwords\s*=|commits\s*=/i', $gitleaksConfig)) {
    throw new RuntimeException('Zentrale Gitleaks-Konfiguration muss alle Default-Regeln ohne Allowlist aktivieren.');
}
$gitleaksIgnore = preg_replace('/^\s*#.*$/m', '', (string) file_get_contents($workspace . '/security-compliance/gitleaks-ignore.txt'));
if (trim((string) $gitleaksIgnore) !== '') {
    throw new RuntimeException('Gitleaks-Ignores sind nur über zentrale befristete Analysis-Ausnahmen zulässig.');
}
$osvConfig = (string) file_get_contents($workspace . '/security-compliance/osv-scanner.toml');
if (preg_match('/\[\[(IgnoredVulns|PackageOverrides)\]\]/i', $osvConfig)) {
    throw new RuntimeException('OSV-Suppressionen sind nur über zentrale befristete Vulnerability-Ausnahmen zulässig.');
}

$allowedStatuses = ['implemented', 'partial', 'decision-required', 'not-applicable'];
$requirementIds = [];
foreach (($mapping['requirements'] ?? []) as $requirement) {
    if (!is_array($requirement)) {
        throw new RuntimeException('Ungültiger Mapping-Eintrag.');
    }
    $id = (string) ($requirement['id'] ?? '');
    $status = (string) ($requirement['status'] ?? '');
    if ($id === '' || isset($requirementIds[$id])) {
        throw new RuntimeException("Leere oder doppelte Mapping-ID: {$id}");
    }
    if (!in_array($status, $allowedStatuses, true)) {
        throw new RuntimeException("Ungültiger Mapping-Status für {$id}: {$status}");
    }
    $requirementIds[$id] = true;
    validateReferences($workspace, stringList($requirement['implementation_refs'] ?? null, "implementation_refs von {$id}"));
    validateReferences($workspace, stringList($requirement['verification_refs'] ?? null, "verification_refs von {$id}"));
    stringList($requirement['evidence'] ?? null, "evidence von {$id}");
    stringList($requirement['scope'] ?? null, "scope von {$id}");
    if (in_array($status, ['partial', 'decision-required'], true) && trim((string) ($requirement['gap'] ?? '')) === '') {
        throw new RuntimeException("Ein {$status}-Mapping braucht eine konkrete Lücke: {$id}");
    }
}
if ($requirementIds === []) {
    throw new RuntimeException('Das BSI-Mapping enthält keine Anforderungen.');
}

$controlIds = [];
foreach (($threatModel['common_controls'] ?? []) as $control) {
    if (!is_array($control)) {
        throw new RuntimeException('Ungültiger Threat-Control-Eintrag.');
    }
    $id = (string) ($control['id'] ?? '');
    if ($id === '' || isset($controlIds[$id])) {
        throw new RuntimeException("Leere oder doppelte Control-ID: {$id}");
    }
    $controlIds[$id] = true;
    validateReferences($workspace, stringList($control['implementation_refs'] ?? null, "Implementation von Control {$id}"));
    validateReferences($workspace, stringList($control['verification_refs'] ?? null, "Verification von Control {$id}"));
}

$threatApps = [];
$threatIds = [];
foreach (($threatModel['applications'] ?? []) as $application) {
    if (!is_array($application)) {
        throw new RuntimeException('Ungültiger App-Eintrag im Threat Model.');
    }
    $appId = (string) ($application['id'] ?? '');
    if (!isset($scopeApps[$appId]) || isset($threatApps[$appId])) {
        throw new RuntimeException("Unbekannte oder doppelte Threat-Model-App: {$appId}");
    }
    $threatApps[$appId] = true;
    foreach (['assets', 'actors', 'entry_points', 'trust_boundaries', 'privileges'] as $field) {
        stringList($application[$field] ?? null, "{$field} von {$appId}");
    }
    foreach (($application['threats'] ?? []) as $threat) {
        if (!is_array($threat)) {
            throw new RuntimeException("Ungültige Bedrohung für {$appId}");
        }
        $threatId = (string) ($threat['id'] ?? '');
        if ($threatId === '' || isset($threatIds[$threatId])) {
            throw new RuntimeException("Leere oder doppelte Threat-ID: {$threatId}");
        }
        $threatIds[$threatId] = true;
        foreach (stringList($threat['controls'] ?? null, "Controls von {$threatId}") as $controlId) {
            if (!isset($controlIds[$controlId])) {
                throw new RuntimeException("Threat {$threatId} verweist auf unbekanntes Control: {$controlId}");
            }
        }
        validateReferences($workspace, stringList($threat['verification_refs'] ?? null, "Verification von {$threatId}"));
        if (trim((string) ($threat['residual_risk'] ?? '')) === '') {
            throw new RuntimeException("Threat {$threatId} braucht ein Restrisiko.");
        }
    }
}
$threatAppIds = array_keys($threatApps);
sort($threatAppIds);
if ($threatAppIds !== $scopeIds) {
    throw new RuntimeException('Threat Model und Security-Scope weichen voneinander ab.');
}

$requiredExceptionFields = stringList($exceptions['policy']['required_fields'] ?? null, 'Exception-Pflichtfelder');
$allowedVulnerabilityScanners = stringList($exceptions['policy']['allowed_scanners'] ?? null, 'Erlaubte Vulnerability-Scanner');
$allowedDependencyRelations = stringList($exceptions['policy']['allowed_dependency_relations'] ?? null, 'Erlaubte Dependency-Beziehungen');
$exceptionStringFields = ['id', 'scanner', 'repository', 'path', 'fingerprint', 'vulnerability', 'severity', 'component', 'dependency_relation', 'reason', 'review_on', 'expires_on'];
$today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
$exceptionIds = [];
foreach (($exceptions['exceptions'] ?? []) as $exception) {
    if (!is_array($exception)) {
        throw new RuntimeException('Ungültige Vulnerability-Ausnahme.');
    }
    foreach ($requiredExceptionFields as $field) {
        if (!array_key_exists($field, $exception)) {
            throw new RuntimeException("Vulnerability-Ausnahme ohne Pflichtfeld: {$field}");
        }
    }
    foreach ($exceptionStringFields as $field) {
        if (!is_string($exception[$field] ?? null) || trim($exception[$field]) === '') {
            throw new RuntimeException("Vulnerability-Ausnahme mit ungültigem Stringfeld: {$field}");
        }
    }
    if (!is_bool($exception['fix_available'] ?? null)) {
        throw new RuntimeException('Vulnerability-Ausnahme mit ungültigem Booleanfeld: fix_available');
    }
    stringList($exception['compensating_controls'] ?? null, 'compensating_controls der Vulnerability-Ausnahme');

    $id = $exception['id'];
    if (isset($exceptionIds[$id])) {
        throw new RuntimeException("Doppelte Vulnerability-Ausnahme: {$id}");
    }
    $exceptionIds[$id] = true;
    if (!in_array($exception['dependency_relation'], $allowedDependencyRelations, true)) {
        throw new RuntimeException("Unzulässige Dependency-Beziehung: {$exception['dependency_relation']}");
    }
    if (!in_array($exception['scanner'], $allowedVulnerabilityScanners, true)) {
        throw new RuntimeException("Unzulässiger Vulnerability-Scanner: {$exception['scanner']}");
    }
    if (!isset($repositoryRoots[$exception['repository']])) {
        throw new RuntimeException("Unbekanntes Exception-Repository: {$exception['repository']}");
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $exception['fingerprint'])) {
        throw new RuntimeException("Ungültiger Vulnerability-Fingerprint: {$id}");
    }
    validateReferences($repositoryRoots[$exception['repository']], [$exception['path']]);
    $expires = canonicalDate($exception['expires_on'], 'expires_on');
    $review = canonicalDate($exception['review_on'], 'review_on');
    if ($expires < $today || $review > $expires) {
        throw new RuntimeException("Abgelaufene oder ungültig terminierte Vulnerability-Ausnahme: {$id}");
    }
    if ($review < $today) {
        throw new RuntimeException("Überfällige Vulnerability-Ausnahme: {$id}");
    }
}

$analysisPolicy = $analysisExceptions['policy'] ?? null;
if (!is_array($analysisPolicy)) {
    throw new RuntimeException('Analysis-Exception-Policy fehlt.');
}
$requiredAnalysisFields = stringList($analysisPolicy['required_fields'] ?? null, 'Analysis-Exception-Pflichtfelder');
$allowedAnalysisScanners = stringList($analysisPolicy['allowed_scanners'] ?? null, 'Erlaubte Analysis-Scanner');
$allowedClassifications = stringList($analysisPolicy['allowed_classifications'] ?? null, 'Erlaubte Analysis-Klassifikationen');
$gitleaksClassifications = stringList($analysisPolicy['gitleaks_allowed_classifications'] ?? null, 'Erlaubte Gitleaks-Klassifikationen');
$analysisIds = [];
$analysisFingerprints = [];
foreach (($analysisExceptions['exceptions'] ?? []) as $exception) {
    if (!is_array($exception)) {
        throw new RuntimeException('Ungültige Analysis-Ausnahme.');
    }
    foreach ($requiredAnalysisFields as $field) {
        if (!array_key_exists($field, $exception)) {
            throw new RuntimeException("Analysis-Ausnahme ohne Pflichtfeld: {$field}");
        }
    }
    foreach (['id', 'scanner', 'repository', 'path', 'finding_id', 'content_hash', 'fingerprint', 'classification', 'reason', 'review_on', 'expires_on'] as $field) {
        if (!is_string($exception[$field] ?? null) || trim($exception[$field]) === '') {
            throw new RuntimeException("Analysis-Ausnahme mit ungültigem Stringfeld: {$field}");
        }
    }
    stringList($exception['compensating_controls'] ?? null, 'compensating_controls der Analysis-Ausnahme');
    $id = $exception['id'];
    if (isset($analysisIds[$id])) {
        throw new RuntimeException("Doppelte Analysis-Ausnahme: {$id}");
    }
    $analysisIds[$id] = true;
    if (isset($analysisFingerprints[$exception['fingerprint']])) {
        throw new RuntimeException("Doppelter Analysis-Fingerprint: {$exception['fingerprint']}");
    }
    $analysisFingerprints[$exception['fingerprint']] = true;
    if (!in_array($exception['scanner'], $allowedAnalysisScanners, true)) {
        throw new RuntimeException("Unzulässiger Analysis-Scanner: {$exception['scanner']}");
    }
    if (!in_array($exception['classification'], $allowedClassifications, true)
        || ($exception['scanner'] === 'gitleaks' && !in_array($exception['classification'], $gitleaksClassifications, true))) {
        throw new RuntimeException("Unzulässige Analysis-Klassifikation: {$id}");
    }
    if (!isset($repositoryRoots[$exception['repository']])) {
        throw new RuntimeException("Unbekanntes Analysis-Repository: {$exception['repository']}");
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $exception['fingerprint'])
        || !preg_match('/^[a-f0-9]{64}$/', $exception['content_hash'])) {
        throw new RuntimeException("Ungültiger Analysis-Fingerprint oder Content-Hash: {$id}");
    }
    validateReferences($repositoryRoots[$exception['repository']], [$exception['path']]);
    $expires = canonicalDate($exception['expires_on'], 'expires_on');
    $review = canonicalDate($exception['review_on'], 'review_on');
    if ($expires < $today || $review > $expires) {
        throw new RuntimeException("Abgelaufene oder ungültig terminierte Analysis-Ausnahme: {$id}");
    }
    if ($review < $today) {
        throw new RuntimeException("Überfällige Analysis-Ausnahme: {$id}");
    }
}

echo "Security-Compliance-Konsistenz: OK\n";

if ($changedFiles === []) {
    exit(0);
}

$triggers = [];
$contexts = [];
foreach ($changedFiles as $changedFile) {
    $path = str_replace('\\', '/', ltrim($changedFile, './'));
    $fileTriggers = [];
    if (preg_match('#(^|/)(appinfo/routes\.php|lib/(Controller|PublicApi|Contract)/)#i', $path)) {
        $fileTriggers['api-contract'] = true;
        $fileTriggers['security-mapping'] = true;
        $fileTriggers['threat-model'] = true;
    }
    if (preg_match('#(^|/)(lib/(Middleware|Permission)/|lib/[^/]*(Access|Authorization|Permission)|lib/Service/[^/]*(Access|Authorization|Permission|TemporaryAdmin)|tests/[^/]*(Access|Authorization|Permission|Security))#i', $path)) {
        $fileTriggers['authorization-negative-tests'] = true;
        $fileTriggers['security-mapping'] = true;
        $fileTriggers['threat-model'] = true;
    }
    if (preg_match('#(^|/)(resources/privacy-processing\.json|lib/Privacy/|lib/(Db|Repository|Model)/|docs/privacy-)#i', $path)) {
        $fileTriggers['privacy-processing-provider'] = true;
        $fileTriggers['security-mapping'] = true;
        $fileTriggers['threat-model'] = true;
    }
    if (preg_match('#(^|/)(lib/Migration/|tests/Migration/|appinfo/info\.xml)#i', $path)) {
        $fileTriggers['migration-reinstall'] = true;
        $fileTriggers['security-mapping'] = true;
    }
    if (preg_match('#(^|/)(composer\.(json|lock)|package(-lock)?\.json|pnpm-lock\.yaml|yarn\.lock|\.github/workflows/)#i', $path)) {
        $fileTriggers['dependency-sbom'] = true;
        $fileTriggers['security-mapping'] = true;
    }
    if (preg_match('#(^|/)(lib/BackgroundJob/|lib/[^/]*(File|Upload|Download|Document|Export)|lib/Service/[^/]*(File|Upload|Download|Document|Export|OAuth|CalDav|Mail))#i', $path)) {
        $fileTriggers['external-file-background-boundary'] = true;
        $fileTriggers['security-mapping'] = true;
        $fileTriggers['threat-model'] = true;
    }
    if ($fileTriggers !== []) {
        ksort($fileTriggers);
        $contexts[$path] = array_keys($fileTriggers);
        foreach ($fileTriggers as $trigger => $_present) {
            $triggers[$trigger] = true;
        }
    }
}

if ($triggers === []) {
    echo "Security-Impact: keine kontextuelle Prüfung ausgelöst\n";
    exit(0);
}
ksort($contexts);
foreach ($contexts as $path => $pathTriggers) {
    echo 'Review-Kontext: ' . $path . ' -> ' . implode(', ', $pathTriggers) . "\n";
}
ksort($triggers);
foreach (array_keys($triggers) as $trigger) {
    echo "Review-Trigger: {$trigger}\n";
}
echo "Security-Impact: fachlich prüfen; vorhandene Nachweise dürfen unverändert bestätigt werden\n";
