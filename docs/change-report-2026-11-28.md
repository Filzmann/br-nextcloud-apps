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
sind durch `flz_organization/tests/ProcessingMetadataProviderTest.php` (bzw.
die analogen Dateien in flz_dpa und orgsuite) abgedeckt (100%
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

## LB-RUNTIME-RETIREMENT — LocalBase aus Katalog, Installer und Bundles entfernt (umgesetzt)

Entscheidung (Simon, 28. November 2026): LocalBase fliegt vollständig aus
Katalog, Installer und Bundles; es gibt keinen Produktivbestand, daher ist
keine Datenmigration nötig.

- Kanonischer Katalog (`orgsuite/resources/flz-product-catalog.json`) ohne
  `localbase`-Eintrag; OrgSuite ist alleinige verbindliche Suite-Infrastruktur
  (`FlzProductCatalog::REQUIRED_INFRASTRUCTURE = ['orgsuite']`).
- `scripts/read-flz-product-catalog.php` lädt die Katalogklasse aus OrgSuite
  statt aus LocalBase.
- `scripts/install-flz-product-bundle.sh` installiert und aktiviert
  `localbase` nicht mehr; `tests/check-flz-product-installer.sh` prüft
  positiv, dass LocalBase weder installiert noch aktiviert wird.
- Produktpaket-Vertrag in `scripts/verify-flz-full-suite-delivery.sh` ohne
  `localbase-`-Archive; Brand-Vertrag ohne LocalBase-Runtime-Wurzel;
  Standalone-Vertrag ohne LocalBase-Prüfschleife.
- Doku: flz-full-suite README/INSTALLATION ohne LocalBase-Bestandteil,
  ADR 0001 mit datierter Änderung zur Katalogquelle.
- LocalBase bleibt als Repository bestehen (Test-Harness: PhpTestRunner und
  Coverage-Tooling unter `tests/`, Compat-Stage/Staging-Mirror), wird aber
  nicht mehr gebündelt. Vollständiger Code-Rückbau bleibt offen.

## Entscheidungen vom 28. November 2026 (Session-Abschluss, Simon)

- **FR-01/FR-02 (P0) — weiter ausbauen**: Die V1-Verträge
  (`OCA\FlzOrganization\PublicApi\V1`, `OCA\FlzPermissionMatrix\PublicApi\V1`)
  werden additiv weiter ausgebaut; jeder neue Vertrag braucht Owner,
  Provider-/Consumer-Contract-Tests und Fehlersemantik.
- **ZM-03 (Personalbedarfsprognose) — zurückgestellt**: bleibt offen im
  Zukunftsplan, keine Umsetzung.
- **ZM-05 (Aggregierte Berichte) — Konzept zuerst**: vor einem Pilot in
  flz_berichte wird ein Datenschutzkonzept (Empfänger, Granularität, Export,
  Aufbewahrung, Reidentifikationsrisiko) erstellt.
- **ZM-07 (Recruitment–BQ-Integration) — Minimal-Vertrag**: kleiner
  versionierter V1-Vertrag zwischen flzrecruitment und flzbqplanung (fehlender
  Provider bleibt gültiger Standalone-Zustand); flzbqplanung bleibt bis zur
  Release-Reife aus den Bundles ausgeschlossen.
- **DP-06/DP-09 — inkrementell weiter**: bleiben laufende Pflicht bei jeder
  neuen Datenklasse/Verarbeitung; Lücken bleiben sichtbar als `partial`,
  `UNKNOWN`, `UNSUPPORTED`.
- **flzroom 0.13.0**: stabiler Versionsbump für den offiziellen App Store
  (flzroom PR #15), Store-Artefakt rc4.

## FR-06 und FR-09 — entschieden (Simon, 28. November 2026)

- **FR-06 — Groupfolders-Ausnahme entfernt**: Die private
  Groupfolders-22.x-Ausnahme der Berechtigungsmatrix (Adapter, Sources,
  Provider, Quellgate) ist entfallen (flz_permission_matrix PR #8); das
  unterstützte Mindest-Nextcloud 33+ macht sie gegenstandslos.
  `groupfolders`/`files_groupfolders` werden sichtbar `UNSUPPORTED`,
  unbekannte Kombinationen `UNKNOWN`. ADR 0003 trägt eine datierte Änderung.
- **FR-09 — als Regel geschlossen**: Künftige externe/Inter-App-HTTP-APIs
  müssen zuerst Nextcloud-Standards (Events/Capabilities/OCS) prüfen; nur bei
  nachweislichem Mehrbedarf gibt es einen versionierten OCS-/OpenAPI-Vertrag
  mit Autorisierung, Fehlersemantik und Kombinations-Contract-Tests. In ADR
  0001 verankert.
