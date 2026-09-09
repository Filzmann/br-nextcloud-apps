<?php

declare(strict_types=1);

$workspace = dirname(__DIR__);

require_once $workspace . '/filzmann_data_protection/tests/bootstrap.php';

spl_autoload_register(static function (string $class) use ($workspace): void {
    $apps = [
        'OCA\\AdRoom\\' => 'adroom',
        'OCA\\AdPlaner\\' => 'adplaner',
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

use OCA\AdPlaner\Privacy\PlanerProcessingMetadataProvider;
use OCA\AdPlaner\Privacy\PlanerProcessingMetadataProviderListener;
use OCA\AdRoom\Privacy\RoomProcessingMetadataProvider;
use OCA\AdRoom\Privacy\RoomProcessingMetadataProviderListener;
use OCA\FilzmannDataProtection\Privacy\DataProtectionProcessingMetadataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\ProcessingMetadataCatalog;
use OCA\FilzmannDataProtection\PublicApi\V1\ProcessingMetadataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\ProcessingMetadataProviderDescriptor;
use OCA\FilzmannDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FilzmannDataProtection\PublicApi\V1\Testing\ProcessingMetadataProviderContractTestKit;
use OCP\EventDispatcher\Event;

$provider = new RoomProcessingMetadataProvider();
$catalog = ProcessingMetadataProviderContractTestKit::verify($provider);
$planerProvider = new PlanerProcessingMetadataProvider();
$planerCatalog = ProcessingMetadataProviderContractTestKit::verify($planerProvider);

if ($provider->descriptor()->appId() !== 'adroom' || $provider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('AD Raumplaner veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($catalog->processingIds() !== ['room_booking_management', 'temporary_admin_full_access', 'personal_admin_layout']) {
    throw new RuntimeException('Der AD-Raumplaner-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $catalog->toArray())) {
    throw new RuntimeException('Der AD-Raumplaner-Katalog enthält personenbezogene Laufzeitdaten.');
}
if ($planerProvider->descriptor()->appId() !== 'adplaner' || $planerProvider->descriptor()->contractVersion() !== '1.0') {
    throw new RuntimeException('AD Planer veröffentlicht keine stabile Processing-Metadata-Identität.');
}
if ($planerCatalog->processingIds() !== ['shift_planning_management', 'temporary_admin_full_access']) {
    throw new RuntimeException('Der AD-Planer-Katalog deckt seine personenbezogenen Verarbeitungen nicht vollständig ab.');
}
if (array_key_exists('personal_runtime_data', $planerCatalog->toArray())) {
    throw new RuntimeException('Der AD-Planer-Katalog enthält personenbezogene Laufzeitdaten.');
}

$registration = new RegisterProcessingMetadataProvidersEvent();
$listener = new RoomProcessingMetadataProviderListener($provider);
$listener->handle(new Event());
if ($registration->providers() !== []) {
    throw new RuntimeException('Ein fremdes Event hat den AD-Raumplaner-Provider registriert.');
}
$registration->register(new DataProtectionProcessingMetadataProvider());
$listener->handle($registration);
$planerListener = new PlanerProcessingMetadataProviderListener($planerProvider);
$planerListener->handle($registration);
if (array_keys($registration->providers()) !== ['filzmann_data_protection', 'adroom', 'adplaner'] || $registration->registrationFailures() !== []) {
    throw new RuntimeException('Der AD-Raumplaner-Provider wird nicht kompatibel und lazy registriert.');
}

$incompatible = new class($catalog) implements ProcessingMetadataProvider {
    public function __construct(private ProcessingMetadataCatalog $catalog) {}
    public function descriptor(): ProcessingMetadataProviderDescriptor {
        return new ProcessingMetadataProviderDescriptor('incompatible_app', 'Incompatible app', '2.0');
    }
    public function catalog(): ProcessingMetadataCatalog { return $this->catalog; }
};
$registration->register($incompatible);
if (array_keys($registration->providers()) !== ['filzmann_data_protection', 'adroom', 'adplaner']) {
    throw new RuntimeException('Ein inkompatibler Provider hat die gesunde Pilotabdeckung verändert.');
}
if ($registration->registrationFailures() !== ['incompatible_app' => 'Processing metadata provider incompatible.']) {
    throw new RuntimeException('Ein inkompatibler Provider bleibt nicht kontrolliert diagnostizierbar.');
}

echo "Processing-Metadata-Provider-V1-Vertrag geprüft: adroom, adplaner\n";
