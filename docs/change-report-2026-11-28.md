# Änderungsbericht 2026-11-28

Dokumentiert Entscheidungen und Umsetzungen vom 28. November 2026.

## LocalBase-Runtime-Ersetzung — alle Verbraucher migriert (umgesetzt)

Kein deploybarer Verbraucher mehr vorhanden: Weder `lib/`, `appinfo/`,
`templates/` noch `js/` irgendeiner Fachapp (brtop, brstunden, flzcalendar,
flzplaner, flzurlaub, flzroom, flzrecruitment, flzbqplanung, flz_akut,
flz_organization, flz_permission_matrix, flz_data_protection, flz_dpa,
orgsuite, flz-full-suite) referenziert `OCA\LocalBase\`, und keine
`appinfo/info.xml` deklariert eine Abhängigkeit auf `localbase`.

Ersetzt wurde LocalBase durch:

- `OCA\FlzOrganization\PublicApi\V1\*` (Organisationsdaten, Rollen- und
  Bereichsdefinitionen) in flz_organization,
- `OCA\FlzPermissionMatrix\PublicApi\V1\*` (BR-Rollen-Snapshots,
  `BrRoleSnapshotService`) in flz_permission_matrix — brtop und brstunden
  lesen BR-Gruppen nicht mehr aus LocalBase-AppConfig, sondern fail-closed
  über den V1-Vertrag mit Legacy-Fallback auf die Mitgliedsgruppe,
- die app-lokal gebündelte Kategorie-A-Bibliothek `vendor/flz-shared`
  (u. a. `GroupProvisioningService`, VERSION 0.3.0) in flzcalendar,
  flzplaner, flzurlaub, flzroom, brtop, brstunden,
- app-lokale Standalone-Navigation über natives `INavigationManager`
  (`lib/Listener/StandaloneNavigationListener.php`, je App registriert via
  `LoadAdditionalEntriesEvent`, order 80–86) statt des LocalBase-`StandaloneAppNavigationService`.

Der Parent-Vertrag `tests/check-flz-full-suite-standalone-contract.sh` wurde
entsprechend auf den LocalBase-freien Nachweis umgestellt (Listener nutzt
`INavigationManager` + `LoadAdditionalEntriesEvent`, explizites Verbot von
`OCA\LocalBase\`-Referenzen im Listener).

LocalBase selbst bleibt als Test-Harness (PhpTestRunner, Coverage-Tools) und
Pilot-App bestehen und bleibt Katalog-Infrastruktur in Suite- und
Produktbundles. Der vollständige Rückbau der Kategorie-B-Laufzeitapp ist
als `LB-RUNTIME-RETIREMENT` in `localbase/ROADMAP.md` dokumentiert und
braucht eine ausdrückliche Freigabe (u. a. Katalog-, Installer-, Delivery-
und Staging-Compat-Vertrag).

## DP-08 — Processing-Metadaten-Kataloge (umgesetzt)

flz_organization, flz_dpa und orgsuite liefern jeweils
`resources/privacy-processing.json` (gegen
`docs/contracts/privacy-processing-metadata.schema.json` validiert) plus
`lib/Privacy/<App>ProcessingMetadataProvider.php` samt Listener und
Registration in `Application`. Der Katalogpfad ist zu Testzwecken über
`__construct(private ?string $catalogFile = null)` injizierbar; fehlende,
ungültige und nicht-objektartige Katalogdateien werfen `DomainException` und
sind durch `tests/ProcessingMetadataProviderTest.php` abgedeckt (100%
PHP-Coverage-Gate auf PHP 8.3 und 8.5).

## flzroom — App-Store-Kandidat rc3 (erstellt, Signierung ausstehend)

Aus dem gemergten Main-Stand `d4bf621` wurde
`dist/flzroom-store-rc3.tar.gz` über `scripts/build-app-store-candidate.sh`
gebaut (Gate grün, 160 Dateien, einzelnes Wurzelverzeichnis `flzroom`,
24 `vendor/flz-shared`-Dateien enthalten, keine Tests/CI/.agents, AGPL,
keine LocalBase-Referenzen). sha256
`52378ef7557aa378bfc8c0ded203d5b73bbd58ed3cbf8a87311ebeef5115b778`,
Evidence in `dist/flzroom-store-rc3.evidence.json`. Die Signierung für den
offiziellen Nextcloud App Store ist ein separater menschlicher Schritt
(privater Schlüssel liegt nicht vor).

## OrgSuite — kanonischer Produktkatalog und eigenes Admin-Template (umgesetzt)

Der kanonische FLZ-Produktkatalog liegt app-lokal in OrgSuite
(`orgsuite/resources/flz-product-catalog.json` +
`lib/Catalog/FlzProductCatalog.php`); Parent-Skripte lesen ihn über
`FLZ_PRODUCT_CATALOG_FILE` bzw. mitgelieferte Kopie. Das Admin-Template ist
eigenständig und verlinkt auf die flz_organization-Administration. DP-08
siehe oben (PR #12).
