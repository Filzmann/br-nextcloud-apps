# ADR 0001: Gemeinsamer Code, Laufzeit-Apps und App-Store-Releases

- Status: angenommen
- Entscheidung: 2026-08-08
- Geltungsbereich: Parent-Workspace, alle neu angelegten Apps sowie alle
  künftigen Shared-Code-, Cross-App- und Veröffentlichungsentscheidungen
- Noch nicht umgesetzt: die in dieser Datei beschriebene LocalBase-Migration

## Kontext und Belegstatus

`localbase` ist im aktuellen Arbeitsbaum weder nur Bibliothek noch nur
Laufzeitdienst. Die App mischt technische Hilfen, Browser-Basisklassen,
test-only Werkzeuge, öffentliche Cross-App-Events, organisationsweite
Konfiguration, Cache, Hintergrundjob, Administration, Navigation und
organisationsspezifische Demo- und Produktdaten. Deshalb wird nicht die ganze
App pauschal eingestuft, sondern jeder Bestandteil einzeln.

Diese Bestandsaufnahme berücksichtigt den uncommitteten Arbeitsstand vom
2026-08-08. Insbesondere die Organisationsversion 3 und der
`AdOrganizationSnapshot` waren in `localbase` noch nicht vollständig
versioniert. Die Statusangaben bedeuten:

- **verifiziert**: im aktuellen Code, in Metadaten oder durch bestehende Tests
  direkt belegt;
- **plausible Einordnung**: Zielkategorie folgt aus den belegten Eigenschaften,
  benötigt vor Migration aber noch einen eigenen Contract- und Releaseentscheid;
- **unklar**: Verwendungen oder Anforderungen sind nicht vollständig belegt;
- **derzeit nicht entscheidbar**: Produkt-, Lizenz- oder Betriebsentscheidung
  fehlt und darf nicht technisch vorweggenommen werden.

## Verifizierter Ist-Zustand

### Eigener LocalBase-Lebenszyklus

| Eigenschaft | Befund | Status und Beleg |
| --- | --- | --- |
| Nextcloud-App | Eigene App-ID `localbase`, Version, Repository, Namespace und Nextcloud-Kompatibilitätsbereich | verifiziert: `localbase/appinfo/info.xml` |
| Datenbanktabellen | Keine eigene Fach- oder Cachetabelle gefunden | verifiziert: `localbase/lib/Migration/Version000001Date202607220001.php` registriert nur einen Job; Suche in `localbase/lib` findet kein `createTable` |
| Migration | Eine Migration registriert den Kalenderjob additiv | verifiziert: `localbase/lib/Migration/Version000001Date202607220001.php` |
| Hintergrundjob | Tägliche Aktualisierung des laufenden und der zwei folgenden Kalenderjahre | verifiziert: `localbase/appinfo/info.xml`, `localbase/lib/BackgroundJob/RefreshHolidayCalendarJob.php` |
| Gemeinsam gespeicherte Daten | Kalenderkontext, Feiertagsjahre, Organisationsdefinition, Peerfreigaben und Demo-Kontoregister liegen in `IAppConfig`; persönliche Adminanordnung liegt in `IUserConfig` | verifiziert: `localbase/lib/Calendar/CalendarContextSettingsService.php`, `HolidayCalendarCacheStore.php`, `localbase/lib/Organization/AdOrganizationSettingsService.php`, `AdSuiteAdminSettingsService.php`, `localbase/lib/Service/DemoAccountProvisioningService.php`, `AdSuiteAdminLayoutService.php` |
| Öffentliche Laufzeitdienste | PHP-Klassen unter `OCA\LocalBase`, Event-/Capability-Verträge und per DI konsumierte Services | verifiziert: `localbase/docs/architecture.md` sowie Consumerstellen unten |
| Capabilities | Keine Nextcloud-`ICapability` in `info.xml`; vorhanden ist ein eigener öffentlicher Eventvertrag für Integrationsfähigkeiten | verifiziert: `localbase/lib/Integration/IntegrationCapabilityQueryEvent.php`, `AdIntegrationCapabilities.php`, `IntegrationCapabilityService.php` |
| Benutzeroberfläche | Organisationseditor mit CSS, JavaScript, Template und Settings-Adaptern | verifiziert: `localbase/templates/organization-admin.php`, `localbase/js/admin/organization-admin.js`, `localbase/css/organization-admin.css`, `localbase/lib/Settings/StandaloneOrganizationAdmin.php` |
| Routen und API | Fünf geschützte Adminrouten für Lesen, Kalenderkontext, Organisation, Freigaben und persönliches Layout | verifiziert: `localbase/appinfo/routes.php`, `localbase/lib/Controller/AdSuiteAdminApiController.php` |
| Externer Dienst | OpenHolidays-Adapter ohne Schlüssel, mit LocalBase-Cache und Fehlerzuständen | verifiziert: `localbase/lib/Calendar/OpenHolidaysClient.php`, `HolidayCalendarService.php` |

Es gibt damit keine LocalBase-eigenen Tabellen, aber sehr wohl zentralen
persistenten Zustand und einen eigenständigen Laufzeit- und Update-Lebenszyklus.
„Keine eigene Tabelle“ ist kein Beleg für eine reine Bibliothek.

### Verifizierte Consumer und Kopplungsart

| Consumer | Verifizierte Laufzeitverwendung | Kopplungsbelege |
| --- | --- | --- |
| `brtop` | API-Responder, Modelltrait, Logger, Gruppenservice sowie LocalBase-JavaScript | `brtop/lib/Controller/ApiController.php`, `lib/Model/Meeting.php`, `lib/Service/BrtopLogger.php`, `lib/Service/BrGroupsService.php`, `templates/index.php` |
| `adplaner` | technische PHP-/JS-Bausteine, Organisationsdefinition/-persistenz, Demokonten, Navigation und Capability-Event | `adplaner/lib/Controller/ApiController.php`, `lib/Store/TeamSettingsStore.php`, `lib/Service/PlanerDemoPackService.php`, `lib/Listener/StandaloneNavigationListener.php`, `templates/index.php` |
| `brstunden` | API-Responder, Modelltrait, Logger, Gruppenservice und LocalBase-JavaScript | `brstunden/lib/Controller/ApiController.php`, `lib/Model/HourEntry.php`, `lib/Service/BrStundenLogger.php`, `lib/Service/BrGroupsService.php`, `templates/index.php` |
| `br_permission_matrix` | keine LocalBase-Produktivklasse gefunden; gemeinsamer PHP-Test-Runner | `br_permission_matrix/tests/run.php`; Produktivtemplate bindet nur einen OrgSuite-Host ein |
| `adcalendar` | Organisations-/Rechteverträge, Kalender-/Abwesenheits-/Konflikt-Events, Feiertage, Demo-/Navigation-/Capability-Dienste und LocalBase-JavaScript | `adcalendar/lib/Service/CalendarAccessService.php`, `lib/Service/AbsenceService.php`, `lib/Listener/ScheduleConflictQueryListener.php`, `lib/Controller/ApiController.php`, `templates/index.php` |
| `adurlaub` | Organisations-/Rechteverträge, Abwesenheitsprovider, Konflikt-Consumer, Feiertage, Demo-/Navigation-/Capability-Dienste und LocalBase-JavaScript | `adurlaub/lib/Service/VacationAccessService.php`, `lib/Listener/AbsenceQueryListener.php`, `lib/Service/VacationService.php`, `lib/Service/HolidayCalendarService.php`, `templates/index.php` |
| `orgsuite` | Produktkatalog, LocalBase-Template/Assets, LocalBase-Admin-API | `orgsuite/lib/Controller/EntryController.php`, `lib/Listener/NavigationListener.php`, `lib/Settings/Admin.php`, `tests/http-smoke.sh` |
| `adroom` | Kalenderkontext/Feiertage, Demokonten, Navigation/Capability und LocalBase-JavaScript | `adroom/lib/Service/BookingService.php`, `lib/Service/HolidayService.php`, `lib/Service/RoomDemoPackService.php`, `templates/index.php` |
| `adrecruitment` | unveränderlicher Organisationssnapshot, Rechtepolicy und Standalone-Navigation | `adrecruitment/lib/Service/RecruitmentAccessService.php`, `lib/Service/RecruitmentPermissionPolicy.php`, `lib/Listener/StandaloneNavigationListener.php` |

Zusätzlich laden zahlreiche App-Tests Dateien relativ aus dem benachbarten
LocalBase-Repository, beispielsweise `adcalendar/tests/run.php`,
`adurlaub/tests/VacationAccessServiceTest.php` und
`orgsuite/tests/EntryControllerTest.php`. Das ist eine Entwicklungs- und
Testkopplung, keine auslieferbare Produktionsabhängigkeit.

Der Parent-Releasecode liest LocalBase-Interna direkt:
`scripts/read-ad-product-catalog.php` lädt
`localbase/lib/Catalog/AdProductCatalog.php`, und
`scripts/build-ad-suite-release.sh` liest
`localbase/resources/ad-product-catalog.json`. Diese Quellbaumkopplung ist für
den internen Suite-Build belegt, aber kein zulässiges Modell für ein
eigenständiges App-Store-Archiv.

Direkte Produktivzugriffe eines Consumers auf LocalBase-Tabellen wurden nicht
gefunden; LocalBase besitzt derzeit auch keine eigenen Tabellen. Direkte
Produktivzugriffe auf die privaten LocalBase-AppConfig-Schlüssel wurden
ebenfalls nicht gefunden. `adrecruitment/tests/integration/AuthenticatedPageSmoke.php`
ändert `ad_organization_definition` direkt, aber ausschließlich als
Integrationstest-Fixture. Direkte interne Kopplung besteht dennoch durch
konkrete `OCA\LocalBase`-Klassen, LocalBase-JavaScript-Globals, das
LocalBase-Template, LocalBase-Routen und die interne Produktkatalogdatei.

### Installations- und Store-Fähigkeit heute

Die fünf im Produktkatalog als `product` und `standalone` geführten Apps
`adcalendar`, `adplaner`, `adurlaub`, `adroom` und `adrecruitment` sind die
verifizierten Kandidaten für getrennte Produktveröffentlichungen
(`localbase/resources/ad-product-catalog.json`,
`ad-suite/docs/ARCHITECTURE.md`). Ob auch die BR-Apps öffentlich angeboten
werden sollen, ist derzeit nicht entscheidbar; ihre Metadaten und die direkte
OrgSuite-/LocalBase-Kopplung reichen für diese Produktentscheidung nicht aus.

Die aktuelle AD-Lieferung ist ein privates Multi-App-Produktbundle: jedes
Fachproduktbundle enthält LocalBase, OrgSuite und das Fachprodukt
(`ad-suite/docs/INSTALLATION.md`, `scripts/build-ad-suite-release.sh`). Das ist
kein einzelnes offizielles App-Store-Paket. Die Fachapps importieren zur
Laufzeit LocalBase-Klassen oder -Assets, deklarieren LocalBase in ihren
`appinfo/info.xml` jedoch nicht. `orgsuite` rendert sogar ein
LocalBase-Template. Der dokumentierte AD-Installer kompensiert dies durch
Installationsreihenfolge; eine saubere Einzelinstallation nur des
Fachapp-Archivs ist dadurch nicht belegt. Ein vorhandener manueller Befund
dokumentiert für AD Raumplaner HTTP 500 bei vollständig deaktivierter
LocalBase (`adroom/docs/manual-acceptance.md`).

Damit verhindern oder gefährden die aktuellen Kopplungen eine unabhängige
Installation, Aktualisierung, Deinstallation und App-Store-Veröffentlichung.
Ein fehlendes oder inkompatibles LocalBase kann vor einer verständlichen
Kompatibilitätsmeldung in Autoloading, DI oder Asset-Laden scheitern. Die
Deinstallation von LocalBase würde außerdem gemeinsam gespeicherte AppConfig,
Adminoberfläche, Cache und Job betreffen. Update- und Rollbackfähigkeit sind
deshalb erst nach expliziter Vertragsversionierung und sauber getesteten
Installationskombinationen belegbar.

## Entscheidung

Jeder gemeinsame Bestandteil wird vor Einführung oder Migration genau einer
der folgenden Kategorien zugeordnet. „LocalBase“ ist keine Kategorie und der
heutige Speicherort entscheidet nicht über die Zielkategorie.

### Kategorie A: Gebundelte Bibliothek

Kategorie A umfasst PHP-, TypeScript- oder JavaScript-Code, der

- keinen eigenen Nextcloud-App-Lebenszyklus, keine zentral gemeinsam
  gespeicherten Laufzeitdaten, keine eigene Administration und keinen
  unabhängigen Hintergrundjob braucht;
- in mindestens zwei realen Consumern dieselbe Semantik und denselben
  Änderungsgrund besitzt; und
- als normale, separat versionierte Build-Abhängigkeit konsumiert werden kann.

Die Quelle wird zentral und separat versioniert. PHP wird als Composer-Paket
oder über einen nachweislich gleichwertigen, reproduzierbaren Mechanismus,
Frontend-Code als passende Paket-/Build-Abhängigkeit eingebunden.
Composer-Path-Repositories sind kein Produktionsmodell. Lock-Dateien legen
die konkrete Version fest. Der Releasebau installiert ohne
Entwicklungsabhängigkeiten, erzeugt die Produktionsartefakte und nimmt alle
benötigten Laufzeitdateien in das Archiv der Fachapp auf. Nutzer*innen
installieren keine Bibliotheks-App. Eine zweite vollständige Nextcloud-App
wird nicht in dieses Archiv gelegt.

PHP-Abhängigkeiten müssen für jede ausliefernde App durch
Namespace-Isolierung beziehungsweise Prefixing so gebaut werden, dass zwei
Apps mit unterschiedlichen Bibliotheksversionen keine global kollidierenden
Klassen laden. Dasselbe Prinzip gilt für globale Browsernamen: neue
Bibliotheken dürfen sich nicht über `window.LocalBase` gegenseitig ersetzen.
Die Isolation, Lizenz und transitive Abhängigkeitsliste werden im
Releaseprozess geprüft.

**Das Mitliefern einer gebauten Bibliotheksversion in mehreren App-Releases
ist keine ungepflegte Quellcode-Duplikation. Die Quelle bleibt zentral; das
Release jeder App enthält eine kontrollierte, versionierte und für diese App
getestete Laufzeitkopie.** Ein Sicherheits- oder Funktionsupdate der
Bibliothek benötigt neue Releases aller betroffenen Apps.

### Kategorie B: Eigenständige Nextcloud-Laufzeit-App

Kategorie B umfasst gemeinsam gespeicherte fachliche Daten, zentrale
Organisationskonfiguration, eigene Migrationen oder Hintergrundjobs, zentrale
öffentliche Services, appübergreifende Berechtigungs-/Konfigurationsdienste,
eigene Administration oder eine fachlich führende Datenquelle.

Eine solche Basis bleibt eine eigenständige App mit eigenem Repository,
Versionen, Migrationen, Tests, Release und dokumentiertem Datenlebenszyklus.
Sie wird nicht in das Releasearchiv einer anderen App kopiert. Consumer
verwenden ausschließlich kleine, dokumentierte, versionierte öffentliche
Schnittstellen. Direkte Zugriffe auf fremde Tabellen, interne Klassen,
private AppConfig-Schlüssel, Controller, Assets oder Dateistrukturen sind
verboten. Die API-/Capability-Version, Installationsreihenfolge,
Kompatibilitätsmatrix, Deinstallationswirkung und der verständliche Fehlerfall
bei fehlender oder inkompatibler Basis werden dokumentiert und getestet.
Fehlen oder Versionskonflikte dürfen nicht als unkontrollierter PHP-Fatal-Error
enden.

Vor einer öffentlichen Veröffentlichung ist gesondert zu entscheiden, ob die
zusätzliche App sachlich notwendig und extern zumutbar ist. Historisch
abgelegte Hilfsfunktionen rechtfertigen keine Basis-App.

### Kategorie C: Bewusst lokaler Code

Kategorie C bleibt im jeweiligen Consumer, wenn wenige triviale Zeilen nur
ähnlich aussehen, die fachliche Bedeutung oder Änderungsursache verschieden
ist oder eine gemeinsame Abstraktion mehr Kopplung als Nutzen erzeugt. Eine
nicht offensichtlich triviale lokale Duplikation erhält eine kurze
Begründung. Ohne zwei konkrete semantisch gleiche Verwendungen entsteht keine
neue Abstraktion.

### Workflow für bestehende Apps

Vor dem Verschieben bestehenden Codes nach LocalBase oder in eine gemeinsame
Bibliothek dokumentiert der zuständige App-Lauf:

1. mindestens zwei konkrete reale Verwendungen;
2. identische Semantik einschließlich negativer und Grenzfälle;
3. denselben Änderungsgrund statt nur ähnlicher Implementierung;
4. dass keine unnötige Laufzeitkopplung oder zyklische Abhängigkeit entsteht;
5. dass weder direkte Fremdtabellen- noch private AppConfig-, Controller-,
   Asset- oder Dateipfadabhängigkeiten eingeführt werden;
6. klare Quellcodeeigentümerschaft, Versionierung und
   Rückwärtskompatibilitätsstrategie; und
7. Auswirkungen auf Installation, Update, Deinstallation, Rollback,
   Consumer-Tests und Releasearchive.

Ist einer dieser Punkte nicht belegt, bleibt der Code Kategorie C oder die
Einordnung `noch zu prüfen`; er wird nicht vorsorglich ausgelagert.

## Store- und Releasevertrag

Für jede App mit Ziel „offizieller Nextcloud App Store“ gilt:

1. Das eigene Releasearchiv plus alle ausdrücklich dokumentierten
   Laufzeitvoraussetzungen ergibt eine vollständig funktionsfähige App.
2. Kategorie-A-Abhängigkeiten sind als gebaute Produktionsabhängigkeiten im
   Archiv enthalten; eine vollständige zweite App-Wurzel ist verboten.
3. Kategorie-B-Abhängigkeiten sind fachlich notwendig, öffentlich
   dokumentiert, kontrolliert geprüft und versioniert. Es gibt keine interne
   Datenbank-, AppConfig-, Controller-, Asset- oder Dateipfadkopplung.
4. Keine private oder unveröffentlichte Workspace-App ist eine
   stillschweigende Voraussetzung. Interne App-IDs, Suite-/Arbeitgebernamen
   und organisationsspezifische Defaults werden vor Veröffentlichung entfernt
   oder als bewusst öffentlich geeigneter Produktvertrag freigegeben.
5. Alle eigenen und fremden Bestandteile besitzen geklärte Lizenz,
   Urheberrechtsherkunft und erforderliche Hinweise. Beispiele und Fixtures
   sind neutral und datenschutzarm.
6. Der Build ist aus definiertem Commit und festgelegten
   Abhängigkeitsversionen reproduzierbar. Root-Steuerung, Tests,
   Entwicklungsabhängigkeiten, Secrets, Zertifikate, private Schlüssel und
   interne Dokumentation bleiben außerhalb des Pakets.
7. Der private App-Signaturschlüssel ist weder Codex noch dem normalen
   Entwicklungsworkflow zugänglich. Signierung und endgültige Veröffentlichung
   sind getrennte, ausdrücklich menschlich freizugebende Delivery-Ereignisse.

Der Releaseworkflow unterscheidet ausdrücklich:

- **Quellcode-Abhängigkeit**: deklarierte Build-Eingabe, nicht automatisch
  Bestandteil des Pakets;
- **gebundelte Produktionsabhängigkeit**: gebaute Kategorie-A-Laufzeitdateien
  innerhalb derselben App-Wurzel;
- **externe Nextcloud-App-Laufzeitabhängigkeit**: getrennt installierte und
  versionierte Kategorie-B-App.

Das Gate prüft maschinell nur belastbare Eigenschaften: Lock-Dateien und
Buildausgaben, eine einzige passende Archivwurzel, notwendige Laufzeitdateien,
verbotene Root-/Entwicklungs-/Secret-Dateien, Lizenzinventar und deklarierte
Metadaten. Fachliche Notwendigkeit, gleiche Semantik, öffentliche Eignung von
Namen, Zumutbarkeit einer zweiten App und das Fehlen verdeckter interner
Kopplung bleiben Reviewentscheidungen. Ein Grep allein ist dafür kein grüner
Nachweis. Die saubere Installation wird in einer isolierten Zielmatrix mit
genau den dokumentierten Voraussetzungen geprüft.

### Aktueller offizieller Nextcloud-Stand

Geprüft am 2026-08-08, ausschließlich in offiziellen Nextcloud-Unterlagen:

- Der [Releaseprozess](https://docs.nextcloud.com/server/latest/developer_manual/app_publishing_maintenance/release_process.html)
  verlangt sinngemäß Produktionsdependencies, gebaute Artefakte, Entfernung
  von Entwicklungsdateien und Tests, Signierung und ein `.tar.gz`-Paket.
- Der [App-Store Developer Guide](https://nextcloudappstore.readthedocs.io/en/latest/developer.html)
  verlangt genau einen kleingeschriebenen Top-Level-Appordner mit
  `appinfo/info.xml`; unbekannte `info.xml`-Elemente werden vor
  Schema-Validierung entfernt. Die dort dokumentierten Dependency-Typen
  enthalten keine App-zu-App-Abhängigkeit. **Keine automatische App-zu-App-Installation**
  oder Versionsauflösung darf deshalb für diesen
  Architekturvertrag vorausgesetzt werden; eine externe Laufzeit-App braucht
  einen eigenen kontrollierten Installations- und Fehlervertrag.
- Die [Code-Signing-Dokumentation](https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/code_signing.html)
  verlangt Signierung für Apps auf `apps.nextcloud.com`, hält private Schlüssel
  geheim, erzeugt `appinfo/signature.json` und verlangt erneute Signierung nach
  jeder Paketänderung.
- Die [App-Store-Regeln](https://docs.nextcloud.com/server/latest/developer_manual/app_publishing_maintenance/publishing.html)
  verlangen eine AGPL-3.0-or-later-kompatible Lizenz, Einhaltung von Urheber-
  und Markenrecht, ausschließlich öffentliche Nextcloud-APIs sowie korrekte
  Upgrades, Downgrades und Deinstallation.

Diese Aussagen werden vor jeder Store-Veröffentlichung erneut gegen die dann
aktuelle offizielle Dokumentation geprüft.

## LocalBase-Klassifikation

| Bestandteil / heutiger Ort | Aktuelle Nutzer | Kategorie und Sicherheit | Begründung / Zielstruktur | Risiko / Store-Auswirkung | Priorität |
| --- | --- | --- | --- | --- | --- |
| `Controller/ApiResponder.php`, `Model/ModelApiTrait.php`, `Service/AppLogger.php` | BRTop, AdPlaner, BRStunden; Trait zusätzlich AD Urlaub | A, **sofort eindeutig / verifiziert** | zustandslose technische Verträge; separates PHP-Paket, beim App-Build geprefixt und gebundelt | mittel: Klassenidentität und BC testen; beseitigt harte LocalBase-App-Pflicht | 1 |
| `js/api`, `js/models`, `js/repositories`, kleine `js/ui`-Primitives | BRTop, AdPlaner, BRStunden, AD Kalender, AD Urlaub, AD Raumplaner | A, **sofort eindeutig / verifiziert** | gleiche Basisklassen/Clientprimitives; separates Frontend-Paket, app-lokal gebaut, keine globalen `window.LocalBase`-Kollisionen | hoch: viele Consumer und Ladeordnung; wesentlich für Store-Standalone | 1 |
| `tests/Support`, Assertions, JS-Fakes und Coverage-Tooling | fast alle App-Testläufer/CI | A, **sofort eindeutig / verifiziert**, test-only | separat versioniertes Dev-Paket oder reproduzierbare Testtool-Abhängigkeit; niemals Produktivarchiv | niedrig für Runtime, mittel für CI-Reproduzierbarkeit/Lizenz | 2 |
| `GroupProvisioningService.php` | BRTop, BRStunden | A, **wahrscheinlich / plausible Einordnung** | native, zustandslose Gruppenoperation; Semantik beider Consumer vor Extraktion als Contract bestätigen | mittel: Rechte-/Backend-Negativfälle; keine Basis-App nur für Helfer | 2 |
| `AdDemoFixtureCatalog.php` | AD Kalender, AD Urlaub | A-intern oder C, **noch zu prüfen** | gleiche Demo-Semantik und öffentliche Eignung der Organisationsbeispiele prüfen; sonst app-lokal | mittel: interne Namen/Fixtures dürfen nicht in Store-Paket rutschen | 4 |
| Kalender-Value-Objects ohne Persistenz (`CalendarContext`, `HolidayPeriod`, `HolidayCalendar`) | Kalender, Urlaub, Raum | A, **wahrscheinlich** | reine Werte/Serialisierung in isoliertes PHP-Paket; nicht den Cache/Provider versteckt mitnehmen | mittel: Vertrag muss unabhängig vom Laufzeitdienst testbar sein | 2 |
| Organisations-Value-Objects/-Policy/-Snapshot ohne Persistenz | Kalender, Urlaub, Planer, Recruitment | A als Vertragstypen, **wahrscheinlich** | schmale versionierte DTO-/Policy-Bibliothek; gespeicherte Definition bleibt B | hoch: Sicherheitsinvariante und Versionshandshake vollständig testen | 2 |
| `CalendarContextSettingsService`, `HolidayCalendarCacheStore`, `HolidayCalendarService`, `OpenHolidaysClient`, Refresh-Job | Kalender, Urlaub, Raum; Admin | B, **sofort eindeutig / verifiziert** | zentrale Konfiguration, Cache, Provider und Job bleiben eigenständiger Laufzeitdienst mit Version/Deinstallationsvertrag | hoch: Cache/Netzwerk/Update/Deinstallation; externe Store-Abhängigkeit begründen | 1 |
| Organisationspersistenz, Snapshot-Service, Hierarchie-/Rechtequelle, Peerfreigaben | Kalender, Urlaub, Planer, Recruitment, OrgSuite | B, **sofort eindeutig / verifiziert** | fachlich führende organisationsweite Konfiguration und Sicherheitsquelle; öffentliche versionierte API statt interner Klassen | sehr hoch: Berechtigungen, Bestands-AppConfig, Migration und Deny-by-default | 1 |
| Admincontroller, Routen, Settings, Template, Organisationseditor und persönliches Layout | einzelne Fachapp oder OrgSuite als Adapter | B, **sofort eindeutig / verifiziert** | eigene Administration und API beim verantwortlichen Laufzeitdienst; Consumer rendert kein fremdes internes Template | hoch: CSRF/Adminrechte, Assets, App-Store-Zumutbarkeit | 1 |
| Abwesenheits-, Konflikt- und Capability-Events | AD Kalender, AD Urlaub, AdPlaner, AD Raumplaner | B, **wahrscheinlich** | appübergreifender In-Process-Eventbus benötigt eine einzige Klassenidentität; schmale versionierte öffentliche Runtime-API | hoch: gebundelte, geprefixte Kopien wären nicht eventidentisch; fehlender Provider muss gültig bleiben | 2 |
| `DemoAccountProvisioningService` mit zentralem Kontoregister | mehrere AD-Demo-Packs | B-intern, **wahrscheinlich / nicht migrieren bis Produktentscheid** | gemeinsam gespeicherte Eigentümerschaft verhindert lokale A-Einstufung; für öffentliche Releases optionalen Demo-Lebenszyklus und Entfernung klären | hoch: Konten/Gruppen, Datenschutz, LDAP und Deinstallation | 4 |
| Produktkatalog und `StandaloneAppNavigationService` | alle AD-Produkte, OrgSuite, Parent-Installer | A für statischen Vertrag/Adapter oder B als Suite-Dienst, **derzeit nicht entscheidbar** | Produktownership, externe Namen und Updatequelle zuerst entscheiden; keine private Katalogdatei still in Store-App voraussetzen | hoch: Installation, Navigation, interne IDs, Releasekopplung | 3 |
| OrgSuite-Adapterlogik | OrgSuite und einzelne AD-Produkte | B, **wahrscheinlich** | Navigation/Adminplatzierung gehört zu einer bewusst versionierten Suite-Laufzeit, nicht in generische Bibliothek | mittel bis hoch: öffentliche Zusatz-App muss sachlich zumutbar sein | 3 |
| LocalBase-spezifischer Organisationseditor/Exporter | keine zweite semantisch identische UI gefunden | B als Adminoberfläche, nicht A; **nicht migrieren** | gehört zur zentralen Organisationsverwaltung; ähnliche Fachapp-UIs sind keine zweite Verwendung | hoch: Personendarstellung, Export, Accessibility | 3 |
| app-spezifische Fachmodelle, Repositorys, Workflows und UI-Komponenten außerhalb LocalBase | jeweilige Fachapp | C, **verifiziert / nicht migrieren** | ähnliche CRUD-/Dialog-/Kalenderformen haben unterschiedliche Fachsemantik und Änderungsgründe | niedrig bei lokalem Verbleib; voreilige Abstraktion wäre Store-Kopplung | fortlaufend |

Im heutigen LocalBase-Bestand ist kein dort liegender Bestandteil bereits
verifiziert Kategorie C. Kategorie C ist stattdessen die verbindliche
Zielentscheidung für die nur ähnlich aussehenden app-spezifischen Bausteine,
die nicht nach LocalBase oder in ein Paket verschoben werden. Ob einzelne
heutige Demo-/Katalogteile künftig bewusst lokal werden, bleibt wie in der
Tabelle ausgewiesen noch zu prüfen.

Die A-Einstufung einzelner Typen bedeutet nicht, dass ihre heutige
`OCA\LocalBase`-Klasse sofort kopiert werden darf. Erst Paket, Versionierung,
Prefixing, Provider-/Consumer-Tests und Releaseintegration machen daraus eine
gebundelte Bibliothek. Ebenso bleibt Kategorie B vorerst eine Klassifikation;
Name, App-ID und Umfang des späteren Laufzeitdienstes sind noch nicht
entschieden.

## Migrationsplan in prüfbaren Schritten

1. **Releaseblocker sichtbar machen.** Pro Store-Kandidat eine
   Abhängigkeitsmanifestation erstellen: alle PHP-Klassen, Assets, Templates,
   Routen, AppConfig-/Datenquellen und dokumentierte Voraussetzungen. Saubere
   Installation ohne implizite Workspace-Nachbarn muss zunächst erwartbar rot
   sein. Noch keine API verschieben.
2. **A-Paketgrenzen charakterisieren.** Zuerst API-Responder, Modelltrait,
   Logger und JS-Primitives mit bestehenden Provider-/Consumer-Tests
   charakterisieren. Identische Semantik und Änderungsgrund pro Consumer
   bestätigen; abweichende Teile Kategorie C belassen.
3. **Reproduzierbaren Bibliotheksbau beweisen.** Separates versioniertes
   Paket, Lock-Dateien, Lizenzinventar und app-spezifisches PHP-Prefixing sowie
   Frontend-Isolation einführen. Mit genau einer Pilot-App beginnen. Deren
   Archiv muss ohne aktivierte LocalBase die A-Funktionen in sauberer
   Installation ausführen. Kein zweiter Consumer wird gleichzeitig migriert.
4. **A-Consumer einzeln umstellen.** Je App eigener Test-first-Lauf,
   Releasearchivprüfung und Rückbau auf die vorherige Appversion. Erst nach
   grünem Pilot folgen weitere Apps einzeln. Während der Übergangszeit keine
   Klasse zugleich aus globaler LocalBase und gebundeltem Paket laden.
5. **B-Vertrag schneiden.** Persistente Organisation/Kalender/Administration,
   Jobs und Eventbus als schmale, versionierte Laufzeit-API festlegen.
   Bestands-AppConfig, Gültigkeitszustände, Upgrade, Downgradegrenze,
   Deinstallation und Fehler bei inkompatibler Version dokumentieren. Name und
   App-ID werden erst danach entschieden; historische Migrationen bleiben
   unverändert.
6. **B-Consumer mit Versionshandshake migrieren.** Wieder nur eine Fachapp pro
   Schritt. Consumer prüft App-Aktivierung und API-/Capability-Version vor
   Klassenauflösung und meldet verständlich. Provider- und Consumer-Contract-
   Tests sowie saubere Installations-/Update-/Deinstallationsmatrix sind grün.
7. **Suite-/Store-Modell entscheiden.** Für jeden Store-Kandidaten menschlich
   entscheiden, ob B-Abhängigkeit öffentlich notwendig und zumutbar ist oder
   die App eine lokale Alternative braucht. Interne IDs, Namen, Demo- und
   Arbeitgeberannahmen sowie Lizenzen separat freigeben. Halb migrierte Apps
   bleiben unveröffentlichbar.
8. **Alte Oberflächen entfernen.** Erst wenn alle betroffenen Consumer auf
   stabilen A-/B-Verträgen stehen, unbenutzte LocalBase-Klassen und Globals in
   einem eigenen Releasezyklus deprecaten und später entfernen. Bestandsdaten
   werden nicht durch Codekopie oder Tabellenzugriff migriert; Rückbaugrenzen
   bleiben pro Schritt dokumentiert.

Jeder Schritt besitzt eine einzelne Eigentümerschaft, kleine Commitgrenze,
Consumerliste und Rückbauentscheidung. Kein Schritt verlangt einen
gleichzeitigen unkontrollierten Umbau mehrerer App-Repositories. Ein
Store-Release hängt nie von einer halb abgeschlossenen Entkopplung ab.

## Folgen und offene Entscheidungen

Umgesetzt und verbindlich ist das Entscheidungs- und Releasemodell. Ebenfalls
verbindlich ist, dass neue Apps nicht automatisch von LocalBase abhängen.
Dokumentiert, aber noch nicht technisch umgesetzt sind die Paketextraktion,
Namespace-Isolierung, API-Versionierung und LocalBase-Migration.

Noch zu entscheiden sind insbesondere Name und App-ID eines möglichen
Kategorie-B-Dienstes, öffentliche Zumutbarkeit dieser Zusatz-App, genaue
Composer-/Frontend-Paketgrenzen, Prefixing-Werkzeug, Lizenzfreigabe aller
gebündelten Bestandteile, Store-Ziel der BR-Apps, Umgang mit Demo-Packs und
Produktkatalog sowie die Deinstallationspolitik für zentrale AppConfig-Daten.

Verworfen sind eine pauschale LocalBase-Einstufung, bloßes Umbenennen,
Quellcodekopien in Consumer-Repositories, Submodule, Composer-Path-Repositories
als Produktion, direkte Fremdtabellenzugriffe, zyklische App-Abhängigkeiten,
eine Basis-App nur für Hilfsfunktionen und oberflächliche Grep-Gates mit
irreführendem Grünurteil.
