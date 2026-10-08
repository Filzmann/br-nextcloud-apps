<?php

declare(strict_types=1);

$workspace = dirname(__DIR__);

require_once $workspace . '/flz_data_protection/tests/bootstrap.php';

spl_autoload_register(static function (string $class) use ($workspace): void {
    $apps = [
        'OCA\\FlzRoom\\' => 'flzroom',
        'OCA\\FlzPlaner\\' => 'flzplaner',
        'OCA\\FlzCalendar\\' => 'flzcalendar',
        'OCA\\FlzUrlaub\\' => 'flzurlaub',
        'OCA\\FlzRecruitment\\' => 'flzrecruitment',
        'OCA\\BrStunden\\' => 'brstunden',
        'OCA\\BrTop\\' => 'brtop',
        'OCA\\FlzBqPlanning\\' => 'flzbqplanung',
        'OCA\\FlzPermissionMatrix\\' => 'flz_permission_matrix',
    ];
    foreach ($apps as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $file = $workspace . '/' . $directory . '/lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});

use OCA\FlzPlaner\Privacy\PlanerProcessingMetadataProvider;
use OCA\FlzPlaner\Privacy\PlanerProcessingMetadataProviderListener;
use OCA\FlzCalendar\Privacy\CalendarProcessingMetadataProvider;
use OCA\FlzCalendar\Privacy\CalendarProcessingMetadataProviderListener;
use OCA\FlzUrlaub\Privacy\VacationProcessingMetadataProvider;
use OCA\FlzUrlaub\Privacy\VacationProcessingMetadataProviderListener;
use OCA\BrStunden\Privacy\BrStundenProcessingMetadataProvider;
use OCA\BrStunden\Privacy\BrStundenProcessingMetadataProviderListener;
use OCA\BrTop\Privacy\BrTopProcessingMetadataProvider;
use OCA\BrTop\Privacy\BrTopProcessingMetadataProviderListener;
use OCA\FlzBqPlanning\Privacy\BqProcessingMetadataProvider;
use OCA\FlzBqPlanning\Privacy\BqProcessingMetadataProviderListener;
use OCA\FlzRecruitment\Privacy\RecruitmentProcessingMetadataProvider;
use OCA\FlzRecruitment\Privacy\RecruitmentProcessingMetadataProviderListener;
use OCA\FlzRoom\Privacy\RoomProcessingMetadataProvider;
use OCA\FlzRoom\Privacy\RoomProcessingMetadataProviderListener;
use OCA\FlzDataProtection\Privacy\DataProtectionProcessingMetadataProvider;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataCatalog;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProvider;
use OCA\FlzDataProtection\PublicApi\V1\ProcessingMetadataProviderDescriptor;
use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\Testing\ProcessingMetadataProviderContractTestKit;
use OCA\FlzPermissionMatrix\Privacy\PermissionMatrixProcessingMetadataProvider;
use OCA\FlzPermissionMatrix\Privacy\PermissionMatrixProcessingMetadataProviderListener;
use OCP\EventDispatcher\Event;

$provider = new RoomProcessingMetadataProvider();
$catalog = ProcessingMetadataProviderContractTestKit::verify($provider);
$planerProvider = new PlanerProcessingMetadataProvider();
$planerCatalog = ProcessingMetadataProviderContractTestKit::verify($planerProvider);
$calendarProvider = new CalendarProcessingMetadataProvider();
$calendarCatalog = ProcessingMetadataProviderContractTestKit::verify($calendarProvider);
$vacationProvider = new VacationProcessingMetadataProvider();
$vacationCatalog = ProcessingMetadataProviderContractTestKit::verify($vacationProvider);
$recruitmentProvider = new RecruitmentProcessingMetadataProvider();
$recruitmentCatalog = ProcessingMetadataProviderContractTestKit::verify($recruitmentProvider);
$hoursProvider = new BrStundenProcessingMetadataProvider();
$hoursCatalog = ProcessingMetadataProviderContractTestKit::verify($hoursProvider);
$brTopProvider = new BrTopProcessingMetadataProvider();
$brTopCatalog = ProcessingMetadataProviderContractTestKit::verify($brTopProvider);
$bqProvider = new BqProcessingMetadataProvider();
$bqCatalog = ProcessingMetadataProviderContractTestKit::verify($bqProvider);
$matrixProvider = new PermissionMatrixProcessingMetadataProvider();
$matrixCatalog = ProcessingMetadataProviderContractTestKit::verify($matrixProvider);

if ($provider->descriptor()->appId() !== 'flzroom' || $provider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('Filzmann Raumplaner veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($catalog->processingIds() !== ['room_booking_management', 'secretariat_foreign_booking_intervention', 'temporary_admin_full_access', 'personal_admin_layout']) {
    throw new RuntimeException('Der Filzmann-Raumplaner-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $catalog->toArray())) {
    throw new RuntimeException('Der Filzmann-Raumplaner-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($planerProvider->descriptor()->appId() !== 'flzplaner' || $planerProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('Filzmann Assistenzplanung veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($planerCatalog->processingIds() !== ['shift_planning_management', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der FLZ-Planer-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $planerCatalog->toArray())) {
    throw new RuntimeException('Der FLZ-Planer-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($calendarProvider->descriptor()->appId() !== 'flzcalendar' || $calendarProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('Filzmann Kalender veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($calendarCatalog->processingIds() !== ['calendar_entry_management', 'personal_calendar_preferences', 'external_calendar_connections', 'derived_calendar_publication', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der Filzmann-Kalender-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $calendarCatalog->toArray())) {
    throw new RuntimeException('Der Filzmann-Kalender-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($vacationProvider->descriptor()->appId() !== 'flzurlaub' || $vacationProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('Filzmann Urlaubsplanung veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($vacationCatalog->processingIds() !== ['vacation_management', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der Filzmann-Urlaubsplanung-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $vacationCatalog->toArray())) {
    throw new RuntimeException('Der Filzmann-Urlaubsplanung-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($recruitmentProvider->descriptor()->appId() !== 'flzrecruitment' || $recruitmentProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('Filzmann Recruitment veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($recruitmentCatalog->processingIds() !== ['application_case_management', 'interview_and_basis_qualification', 'recruitment_inbox_and_documents', 'hiring_master_data_release', 'status_mail_communication', 'candidate_pool_management', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der Filzmann-Recruitment-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $recruitmentCatalog->toArray())) {
    throw new RuntimeException('Der Filzmann-Recruitment-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($hoursProvider->descriptor()->appId() !== 'brstunden' || $hoursProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('BR-Stunden veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($hoursCatalog->processingIds() !== ['monthly_hours_management', 'monthly_reminder_communication', 'payroll_pdf_generation']) {
    throw new RuntimeException('Der BR-Stunden-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $hoursCatalog->toArray())) {
    throw new RuntimeException('Der BR-Stunden-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($brTopProvider->descriptor()->appId() !== 'brtop' || $brTopProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('BR TOP veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($brTopCatalog->processingIds() !== ['council_legislature_and_roster_management', 'meeting_agenda_and_protocol_management', 'invitation_snapshot_and_absence_management', 'document_generation_and_file_storage', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der BR-TOP-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $brTopCatalog->toArray())) {
    throw new RuntimeException('Der BR-TOP-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($bqProvider->descriptor()->appId() !== 'flzbqplanung' || $bqProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('Filzmann BQ-Planer veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($bqCatalog->processingIds() !== ['bq_run_curriculum_and_schedule_management', 'lecturer_profile_and_assignment_management', 'external_lecturer_request_tracking', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der Filzmann-BQ-Planer-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $bqCatalog->toArray())) {
    throw new RuntimeException('Der Filzmann-BQ-Planer-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($matrixProvider->descriptor()->appId() !== 'flz_permission_matrix' || $matrixProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('Die Berechtigungsmatrix veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($matrixCatalog->processingIds() !== ['permission_snapshot_and_matrix_management', 'permission_matrix_export_generation', 'permission_audit_logging', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der Berechtigungsmatrix-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $matrixCatalog->toArray())) {
    throw new RuntimeException('Der Berechtigungsmatrix-Katalog enthält personenbezogene Laufzeitdaten.');
}

$registration = new RegisterProcessingMetadataProvidersEvent();
$listener = new RoomProcessingMetadataProviderListener($provider);
$listener->handle(new Event());
if ($registration->providers() !== []) {
    throw new RuntimeException('Ein fremdes Event hat den Filzmann-Raumplaner-Provider registriert.');
}
$registration->register(new DataProtectionProcessingMetadataProvider());
$listener->handle($registration);
$planerListener = new PlanerProcessingMetadataProviderListener($planerProvider);
$planerListener->handle($registration);
$calendarListener = new CalendarProcessingMetadataProviderListener($calendarProvider);
$calendarListener->handle($registration);
$vacationListener = new VacationProcessingMetadataProviderListener($vacationProvider);
$vacationListener->handle($registration);
$recruitmentListener = new RecruitmentProcessingMetadataProviderListener($recruitmentProvider);
$recruitmentListener->handle($registration);
$hoursListener = new BrStundenProcessingMetadataProviderListener($hoursProvider);
$hoursListener->handle($registration);
$brTopListener = new BrTopProcessingMetadataProviderListener($brTopProvider);
$brTopListener->handle($registration);
$bqListener = new BqProcessingMetadataProviderListener($bqProvider);
$bqListener->handle($registration);
$matrixListener = new PermissionMatrixProcessingMetadataProviderListener($matrixProvider);
$matrixListener->handle($registration);
if (array_keys($registration->providers()) !== ['flz_data_protection', 'flzroom', 'flzplaner', 'flzcalendar', 'flzurlaub', 'flzrecruitment', 'brstunden', 'brtop', 'flzbqplanung', 'flz_permission_matrix'] || $registration->registrationFailures() !== []) {
    throw new RuntimeException('Die Processing-Metadata-Provider werden nicht kompatibel und lazy registriert.');
}

$incompatible = new class($catalog) implements ProcessingMetadataProvider {
    public function __construct(private ProcessingMetadataCatalog $catalog) {}
    public function descriptor(): ProcessingMetadataProviderDescriptor {
        return new ProcessingMetadataProviderDescriptor('incompatible_app', 'Incompatible app', '2.0');
    }
    public function catalog(): ProcessingMetadataCatalog { return $this->catalog; }
};
$registration->register($incompatible);
if (array_keys($registration->providers()) !== ['flz_data_protection', 'flzroom', 'flzplaner', 'flzcalendar', 'flzurlaub', 'flzrecruitment', 'brstunden', 'brtop', 'flzbqplanung', 'flz_permission_matrix']) {
    throw new RuntimeException('Ein inkompatibler Provider hat die gesunde Pilotabdeckung verändert.');
}
if ($registration->registrationFailures() !== ['incompatible_app' => 'Processing metadata provider incompatible.']) {
    throw new RuntimeException('Ein inkompatibler Provider bleibt nicht kontrolliert diagnostizierbar.');
}

// Independent schema validation detects drift in the PHP contract. Reuse the
// app's recipient cases; expected accept/reject results are not derived from
// the validator under test. Discover real catalogs through the verified registry.
$schemaCases = require $workspace . '/flz_data_protection/tests/fixtures/processing-metadata-recipients.php';
foreach ($registration->providers() as $appId => $registeredProvider) {
    $schemaCases['catalog ' . $appId] = ['valid' => true, 'payload' => $registeredProvider->catalog()->toArray()];
}
$schemaCheck = <<<'PY'
import json, sys
from jsonschema import Draft202012Validator
with open(sys.argv[1], encoding='utf-8') as source:
    schema = json.load(source)
Draft202012Validator.check_schema(schema)
validator = Draft202012Validator(schema)
for name, case in json.load(sys.stdin).items():
    if validator.is_valid(case['payload']) != case['valid']:
        raise SystemExit('Processing schema/runtime contract differs: ' + name)
print('Real processing catalogs and shared recipient cases match the canonical schema.')
PY;
$process = proc_open(
    ['python3', '-c', $schemaCheck, $workspace . '/docs/contracts/privacy-processing-metadata.schema.json'],
    [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR],
    $pipes,
);
if (!is_resource($process)) {
    throw new RuntimeException('Processing schema validator could not start (requires python3-jsonschema).');
}
fwrite($pipes[0], json_encode($schemaCases, JSON_THROW_ON_ERROR));
fclose($pipes[0]);
if (proc_close($process) !== 0) {
    throw new RuntimeException('Processing schema validation failed (requires python3-jsonschema).');
}

echo "Processing-Metadata-Provider-V1-Vertrag geprüft: flzroom, flzplaner, flzcalendar, flzurlaub, flzrecruitment, brstunden, brtop, flzbqplanung, flz_permission_matrix\n";
