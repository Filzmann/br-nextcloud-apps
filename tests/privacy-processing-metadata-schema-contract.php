<?php

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, "Processing-Metadata-Schema ungültig: {$message}\n");
    exit(1);
}

$schemaPath = $argv[1] ?? '';
if ($schemaPath === '' || !is_file($schemaPath)) {
    fail('Schemadatei fehlt.');
}

try {
    $schema = json_decode((string) file_get_contents($schemaPath), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    fail('JSON kann nicht gelesen werden: ' . $exception->getMessage());
}

if (!is_array($schema)) {
    fail('Wurzel ist kein JSON-Objekt.');
}

$assertSame = static function (mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fail($message);
    }
};

$assertSame(
    'https://json-schema.org/draft/2020-12/schema',
    $schema['$schema'] ?? null,
    'JSON-Schema-Version muss Draft 2020-12 sein.',
);
$contractOwner = $schema['x-contract-owner'] ?? null;
$assertSame(
    [
        'app_id' => 'flz_data_protection',
        'product_name' => 'Data Protection Center',
        'german_product_name' => 'Datenschutz-Center',
        'php_namespace' => 'OCA\\FlzDataProtection',
    ],
    $contractOwner,
    'Contract-Owner muss die kanonische Identität der Datenschutz-App verwenden.',
);
$assertSame(false, $schema['additionalProperties'] ?? null, 'Unbekannte Wurzelfelder müssen abgewiesen werden.');

$required = $schema['required'] ?? [];
foreach (['schema_version', 'app_id', 'processings'] as $field) {
    if (!in_array($field, $required, true)) {
        fail("Pflichtfeld fehlt: {$field}");
    }
}

$processing = $schema['$defs']['processing'] ?? null;
if (!is_array($processing)) {
    fail('Definition processing fehlt.');
}
$assertSame(false, $processing['additionalProperties'] ?? null, 'Unbekannte Processing-Felder müssen abgewiesen werden.');

$processingRequired = $processing['required'] ?? [];
foreach ([
    'processing_id',
    'name',
    'controller',
    'data_categories',
    'data_subjects',
    'purposes',
    'access_roles',
    'recipients',
    'data_sources',
    'legal_basis',
    'retention',
    'logging',
    'backup',
    'exports_and_reports',
    'data_subject_rights',
    'international_transfers',
    'automated_decisions',
    'special_safeguards',
    'systems',
] as $field) {
    if (!in_array($field, $processingRequired, true)) {
        fail("Processing-Pflichtfeld fehlt: {$field}");
    }
}

$processingIdPattern = $processing['properties']['processing_id']['pattern'] ?? null;
$assertSame('^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$', $processingIdPattern, 'processing_id ist nicht stabil normalisiert.');

$decisionMarker = $schema['$defs']['decision_required']['properties']['status']['const'] ?? null;
$assertSame('PRIVACY-DECISION-REQUIRED', $decisionMarker, 'Fachliche Entscheidungslücke ist nicht kanonisch markiert.');

$runtimeDataRule = $schema['properties']['personal_runtime_data'] ?? null;
if (!is_array($runtimeDataRule) || ($runtimeDataRule['not'] ?? null) !== []) {
    fail('Personenbezogene Laufzeitdaten werden nicht ausdrücklich ausgeschlossen.');
}

$retentionActions = $schema['$defs']['retention_rule']['properties']['action']['enum'] ?? [];
foreach (['delete', 'anonymize', 'restrict', 'review'] as $action) {
    if (!in_array($action, $retentionActions, true)) {
        fail("Retention-Maßnahme fehlt: {$action}");
    }
}

if (in_array('keep_forever', $retentionActions, true)) {
    fail('Unbekannte Retention darf nicht als unbegrenzte Aufbewahrung modelliert werden.');
}

echo "Processing-Metadata-Schema: OK\n";
