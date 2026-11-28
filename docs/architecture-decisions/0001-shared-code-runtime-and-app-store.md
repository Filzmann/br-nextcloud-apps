# ADR 0001: Gemeinsamer Code, Laufzeit-Apps und App-Store-Releases

- Status: angenommen
- Entscheidung: 2026-08-08
- Ergänzung PHP-Autoloading: 2026-08-09
- Ergänzung Standalone-Privacy-Verweis: 2026-08-23
- Ergänzung Entwicklungsphase: 2026-09-05
- Ergänzung erster Kategorie-B-Pilot: 2026-09-06
- Ergänzung Provider-Lifecycle des ersten Piloten: 2026-09-07
- Ergänzung Veröffentlichungs- und LocalBase-Zielrichtung: 2026-09-26
- Ergänzung eigenständiger Organisationsprovider: 2026-11-14
- Geltungsbereich: Parent-Workspace, alle neu angelegten Apps sowie alle
  künftigen Shared-Code-, Cross-App- und Veröffentlichungsentscheidungen
- Teilweise umgesetzt: Der erste LocalBase-Pilot einschließlich seines
  Lifecycle-Nachweises ist abgeschlossen. Im aktuellen Arbeitsbaum konsumiert
  `flz_permission_matrix` stattdessen den öffentlichen V1-Vertrag der
  eigenständigen Laufzeit-App `flz_organization` fail-closed. Deren realer
  Installations-, Deaktivierungs-, Entfernungs- und Wiederinstallationsnachweis
  bleibt offen; die übrige LocalBase-Migration ist ebenfalls nicht abgeschlossen.

## Kontext und Belegstatus

`localbase` ist im aktuellen Arbeitsbaum weder nur Bibliothek noch nur
Laufzeitdienst. Die App mischt technische Hilfen, Browser-Basisklassen,
test-only Werkzeuge, öffentliche Cross-App-Events, organisationsweite
Konfiguration, Cache, Hintergrundjob, Administration, Navigation und
organisationsspezifische Demo- und Produktdaten. Deshalb wird nicht die ganze
App pauschal eingestuft, sondern jeder Bestandteil einzeln.

Diese Bestandsaufnahme berücksichtigt den uncommitteten Arbeitsstand vom
2026-08-08. Insbesondere die Organisationsversion 3 und der
`FlzOrganizationSnapshot` waren in `localbase` noch nicht vollständig
versioniert. Die Statusangaben bedeuten:

- **verifiziert**: im aktuellen Code, in Metadaten oder durch bestehende Tests
  direkt belegt;
- **plausible Einordnung**: Zielkategorie folgt aus den belegten Eigenschaften,
  benötigt vor Migration aber noch einen eigenen Contract- und Releaseentscheid;
- **unklar**: Verwendungen oder Anforderungen sind nicht vollständig belegt;
- **derzeit nicht entscheidbar**: Produkt-, Lizenz- oder Betriebsentscheidung
  fehlt und darf nicht technisch vorweggenommen werden.

## Verifizierter Ist-Zustand

### Eigener LocalBase-Lebenszyklus (historisch — Ist-Zustand bei der Inventarisierung, vor dem Rückbau am 28. November 2026; die genannten LocalBase-Pfade existieren danach nicht mehr)

| Eigenschaft | Befund (Stand bei Inventarisierung) | Status und Beleg |
| --- | --- | --- |
| Nextcloud-App | Eigene App-ID `localbase`, Version, Repository, Namespace und Nextcloud-Kompatibilitätsbereich | verifiziert in `localbase/appinfo/info.xml` (heute registrierungsfreie Hülle 0.13.0) |
| Datenbanktabellen | Keine eigene Fach- oder Cachetabelle gefunden | verifiziert: damalige Migration registrierte nur einen Job; kein `createTable` im damaligen LocalBase-lib-Verzeichnis |
| Migration | Eine Migration registrierte den Kalenderjob additiv | historisch: damalige Migration `Version000001Date202607220001` |
| Hintergrundjob | Tägliche Aktualisierung des laufenden und der zwei folgenden Kalenderjahre | historisch: damaliger `RefreshHolidayCalendarJob` |
| Gemeinsam gespeicherte Daten | Kalenderkontext, Feiertagsjahre, Organisationsdefinition, Peerfreigaben und Demo-Kontoregister lagen in `IAppConfig`; persönliche Adminanordnung in `IUserConfig` | historisch: damalige Calendar-/Organization-/Service-Klassen im damaligen LocalBase-lib-Verzeichnis |
| Öffentliche Laufzeitdienste | PHP-Klassen unter `OCA\LocalBase`, Event-/Capability-Verträge und per DI konsumierte Services | historisch: damalige LocalBase-Architekturdokumentation sowie Consumerstellen unten; heute liegen die Verträge in `flz_organization`/`flz_permission_matrix`/`flz_data_protection` |
| Capabilities | Keine Nextcloud-`ICapability` in `info.xml`; eigener öffentlicher Eventvertrag für Integrationsfähigkeiten | historisch: damalige Integration-Event-Klassen im damaligen LocalBase-lib-Verzeichnis |
| Benutzeroberfläche | Organisationseditor mit CSS, JavaScript, Template und Settings-Adaptern | historisch: damalige Template-/JS-/CSS-/Settings-Dateien in LocalBase |
| Routen und API | Fünf geschützte Adminrouten für Lesen, Kalenderkontext, Organisation, Freigaben und persönliches Layout | historisch: damalige Routen-Datei und Admin-Controller in LocalBase |
| Externer Dienst | OpenHolidays-Adapter ohne Schlüssel, mit LocalBase-Cache und Fehlerzuständen | historisch: damalige OpenHolidays-/Holiday-Klassen im damaligen LocalBase-lib-Verzeichnis |

Es gibt damit keine LocalBase-eigenen Tabellen, aber sehr wohl zentralen
persistenten Zustand und einen eigenständigen Laufzeit- und Update-Lebenszyklus.
„Keine eigene Tabelle“ ist kein Beleg für eine reine Bibliothek.

### Verifizierte Consumer und Kopplungsart

| Consumer | Verifizierte Laufzeitverwendung | Kopplungsbelege |
| --- | --- | --- |
| `brtop` | API-Responder, Modelltrait, Logger, Gruppenservice sowie LocalBase-JavaScript | `brtop/lib/Controller/ApiController.php`, `lib/Model/Meeting.php`, `lib/Service/BrtopLogger.php`, `lib/Service/BrGroupsService.php`, `templates/index.php` |
| `flzplaner` | technische PHP-/JS-Bausteine, Organisationsdefinition/-persistenz, Demokonten, Navigation und Capability-Event | `flzplaner/lib/Controller/ApiController.php`, `lib/Store/TeamSettingsStore.php`, `lib/Service/PlanerDemoPackService.php`, `lib/Listener/StandaloneNavigationListener.php`, `templates/index.php` |
| `brstunden` | API-Responder, Modelltrait, Logger, Gruppenservice und LocalBase-JavaScript | `brstunden/lib/Controller/ApiController.php`, `lib/Model/HourEntry.php`, `lib/Service/BrStundenLogger.php`, `lib/Service/BrGroupsService.php`, `templates/index.php` |
| `flz_permission_matrix` | optionaler Organisationssnapshot über `OCA\\FlzOrganization\\PublicApi\\V1`; eigenständiger Test-Runner und Nextcloud-Navigation | `flz_permission_matrix/lib/Service/OrganizationSnapshotService.php`, `flz_permission_matrix/tests/run.php`, `flz_permission_matrix/lib/Listener/StandaloneNavigationListener.php` |
| `flzcalendar` | Organisations-/Rechteverträge, Kalender-/Abwesenheits-/Konflikt-Events, Feiertage, Demo-/Navigation-/Capability-Dienste und LocalBase-JavaScript | `flzcalendar/lib/Service/CalendarAccessService.php`, `lib/Service/AbsenceService.php`, `lib/Listener/ScheduleConflictQueryListener.php`, `lib/Controller/ApiController.php`, `templates/index.php` |
| `flzurlaub` | Organisations-/Rechteverträge, Abwesenheitsprovider, Konflikt-Consumer, Feiertage, Demo-/Navigation-/Capability-Dienste und LocalBase-JavaScript | `flzurlaub/lib/Service/VacationAccessService.php`, `lib/Listener/AbsenceQueryListener.php`, `lib/Service/VacationService.php`, `lib/Service/HolidayCalendarService.php`, `templates/index.php` |
| `orgsuite` | Produktkatalog, LocalBase-Template/Assets, LocalBase-Admin-API | `orgsuite/lib/Controller/EntryController.php`, `lib/Listener/NavigationListener.php`, `lib/Settings/Admin.php`, `orgsuite/tests/http-smoke.sh` |
| `flzroom` | Kalenderkontext/Feiertage, Demokonten, Navigation/Capability und LocalBase-JavaScript | `flzroom/lib/Service/BookingService.php`, `lib/Service/HolidayService.php`, `lib/Service/RoomDemoPackService.php`, `templates/index.php` |
| `flzrecruitment` | unveränderlicher Organisationssnapshot, Rechtepolicy und Standalone-Navigation | `flzrecruitment/lib/Service/RecruitmentAccessService.php`, `lib/Service/RecruitmentPermissionPolicy.php`, `lib/Listener/StandaloneNavigationListener.php` |

Zusätzlich laden zahlreiche App-Tests Dateien relativ aus dem benachbarten
LocalBase-Repository, beispielsweise `flzcalendar/tests/run.php`,
`flzurlaub/tests/VacationAccessServiceTest.php` und
`orgsuite/tests/EntryControllerTest.php`. Das ist eine Entwicklungs- und
Testkopplung, keine auslieferbare Produktionsabhängigkeit.

Der Parent-Releasecode liest den FLZ-Produktkatalog ausschließlich aus
OrgSuite: `scripts/read-flz-product-catalog.php` lädt
`orgsuite/lib/Catalog/FlzProductCatalog.php` und
`scripts/build-flz-full-suite-release.sh` liest
`orgsuite/resources/flz-product-catalog.json`. (Datierte Änderung 28. November
2026: Zuvor lud der Reader die damalige LocalBase-Katalogklasse; mit
dem LocalBase-Retirement aus den Bundles ist OrgSuite die alleinige
Katalogquelle.) Diese Quellbaumkopplung ist für
den internen Suite-Build belegt, aber kein zulässiges Modell für ein
eigenständiges App-Store-Archiv.

Direkte Produktivzugriffe eines Consumers auf LocalBase-Tabellen wurden nicht
gefunden; LocalBase besitzt derzeit auch keine eigenen Tabellen. Direkte
Produktivzugriffe auf die privaten LocalBase-AppConfig-Schlüssel wurden
ebenfalls nicht gefunden. `flzrecruitment/tests/integration/AuthenticatedPageSmoke.php`
ändert `flz_organization_definition` direkt, aber ausschließlich als
Integrationstest-Fixture. Direkte interne Kopplung besteht dennoch durch
konkrete `OCA\LocalBase`-Klassen, LocalBase-JavaScript-Globals, das
LocalBase-Template, LocalBase-Routen und die interne Produktkatalogdatei.

### Installations- und Store-Fähigkeit heute

Die fünf im Produktkatalog als `product` und `standalone` geführten Apps
`flzcalendar`, `flzplaner`, `flzurlaub`, `flzroom` und `flzrecruitment` sind die
verifizierten Kandidaten für getrennte Produktveröffentlichungen
(`orgsuite/resources/flz-product-catalog.json`,
`flz-full-suite/docs/ARCHITECTURE.md`). Die BR-Apps besitzen derzeit ebenso wie die
übrigen Workspace-Apps kein freigegebenes öffentliches Store-Ziel. Eine
spätere Veröffentlichung bleibt eine gesonderte Entscheidung je App;
Metadaten und heutige OrgSuite-/LocalBase-Kopplung ersetzen diese Freigabe
nicht.

Ergänzung vom 2026-08-15: `flzbqplanung` ist als sechstes FLZ-Fachprodukt in
denselben Kategorie-B-Laufzeitvertrag für Produktkatalog und Navigation
aufgenommen. Die getrennten Katalogflags bleiben maßgeblich: Solange
`fullSuiteBundle` und `productBundle` auf `false` stehen, ist die App ein
navigierbares Entwicklungsprodukt, aber kein Bestandteil eines Release- oder
App-Store-Kandidaten. Diese Aufnahme verschiebt weder BQ-Fachcode nach
LocalBase noch ersetzt sie die ausstehenden Datenschutz-, Rechte- und
Integrationsnachweise der Fachapp.

Die aktuelle FLZ-Lieferung ist ein privates Multi-App-Produktbundle: jedes
Fachproduktbundle enthält LocalBase, OrgSuite und das Fachprodukt
(`flz-full-suite/docs/INSTALLATION.md`, `scripts/build-flz-full-suite-release.sh`). Das ist
kein einzelnes offizielles App-Store-Paket. Die Fachapps importieren zur
Laufzeit LocalBase-Klassen oder -Assets, deklarieren LocalBase in ihren
`appinfo/info.xml` jedoch nicht. `orgsuite` rendert sogar ein
LocalBase-Template. Der dokumentierte FLZ-Installer kompensiert dies durch
Installationsreihenfolge; eine saubere Einzelinstallation nur des
Fachapp-Archivs ist dadurch nicht belegt. Ein vorhandener manueller Befund
dokumentiert für Filzmann Raumplaner HTTP 500 bei vollständig deaktivierter
LocalBase (`flzroom/docs/manual-acceptance.md`).

Damit verhindern oder gefährden die aktuellen Kopplungen eine unabhängige
Installation, Aktualisierung, Deinstallation und App-Store-Veröffentlichung.
Ein fehlendes oder inkompatibles LocalBase kann vor einer verständlichen
Kompatibilitätsmeldung in Autoloading, DI oder Asset-Laden scheitern. Die
Deinstallation von LocalBase würde außerdem gemeinsam gespeicherte AppConfig,
Adminoberfläche, Cache und Job betreffen. Update- und Rollbackfähigkeit sind
deshalb erst nach expliziter Vertragsversionierung und sauber getesteten
Installationskombinationen belegbar.

Ergänzung vom 2026-11-28: Die Ersetzung von LocalBase schreitet voran. Der
kanonische FLZ-Produktkatalog liegt nun in
`orgsuite/resources/flz-product-catalog.json` mit app-lokaler
Reader-Klasse `OCA\OrgSuite\Catalog\FlzProductCatalog`; die Parent-Skripte
lesen ausschließlich diese Quelle (ggf. per `FLZ_PRODUCT_CATALOG_FILE`
übersteuerbar). `flz_organization` hostet die öffentliche
`PublicApi\V1`-Schnittstelle (OrganisationSnapshot, CalendarContext,
HolidayCalendar, AbsenceQuery, ScheduleConflict, IntegrationCapabilities),
über die ehemalige LocalBase-Events konsumieren; `flz_shared` ist die
gebundelte Kategorie-A-Bibliothek (`OCA\<App>\Vendor\Filzmann\Shared`).
Standalone-Navigation, Berechtigungsmatrix und Demo-Packs sind app-lokal.
Sobald kein Consumer mehr `OCA\LocalBase` in lib/appinfo/templates/js
referenziert, wird der LocalBase-Retirement-Schritt (Phase 8) ausgelöst.

## Entscheidung

Jeder gemeinsame Bestandteil wird vor Einführung oder Migration genau einer
der folgenden Kategorien zugeordnet. „LocalBase“ ist keine Kategorie und der
heutige Speicherort entscheidet nicht über die Zielkategorie.

### Verbindlicher PHP-Autoloading- und Migrationsvertrag

PHP-Klassen werden in Produktiv- und Testcode nicht dauerhaft über verteilte
relative `require`- oder `require_once`-Ketten geladen. Für alle bestehenden
und neuen Apps gelten folgende Grenzen:

1. App-eigene Produktivklassen unter `lib/` folgen PSR-4 und werden durch den
   Nextcloud-App-Autoloader geladen. Ein manueller Klassen-Fallback im
   Produktivcode, der nur einen fehlenden Test-Autoloader kompensiert, ist
   unzulässig.
2. Eine gebündelte Kategorie-A-Produktionsabhängigkeit besitzt genau einen
   reproduzierbar erzeugten app-lokalen Composer-Autoloader. Die konkrete
   Paketversion ist gesperrt, die Bibliotheksklassen sind je ausliefernder App
   namespace-isoliert beziehungsweise geprefixt und alle Laufzeitdateien
   liegen im Releasearchiv derselben App-Wurzel.
3. Kategorie-B-Dienste werden niemals über fremde Dateipfade oder einen
   gemeinsamen Workspace-Autoloader eingebunden. Consumer verwenden nur den
   dokumentierten öffentlichen Laufzeitvertrag mit Aktivierungs- und
   Versionsprüfung sowie kontrolliertem Fehlerfall.
4. Jede App besitzt für dependency-arme PHP-Tests einen zentralen app-lokalen Test-Bootstrap
   beziehungsweise Autoloader. Einzelne Tests laden darüber
   App- und Testklassen, statt deren Quelldateien einzeln aufzuzählen. Eine
   test-only LocalBase-Unterstützung wird als versionierte
   Entwicklungsabhängigkeit oder bis zu deren Bereitstellung über genau eine
   zentral konfigurierte Übergangsstelle konsumiert; relative Zugriffe aus
   vielen einzelnen Testdateien sind nach der App-Migration verboten.
5. Nextcloud-Integrationstests dürfen den dokumentierten
   Nextcloud-Testbootstrap laden. Template-Partials, explizite
   Prozess-/Test-Entrypoints und der einmalige Bootstrap eines gebündelten
   Composer-Autoloaders sind keine Klassen-`require`-Ketten und bleiben
   zulässige, begründete Includes.
6. Ein Composer-Path-Repository, ein gemeinsames `vendor/` mehrerer Apps oder
   ein Autoloader auf benachbarte Produktions-Repositorys ist kein
   Liefermodell. Test- und Entwicklungsabhängigkeiten gelangen nicht in das
   Produktionsarchiv.

Die Bestandsmigration erfolgt verbindlich appweise statt als unkontrollierter
Cross-App-Umbau. Sobald ein Änderungsauftrag ausführbaren PHP-Code oder
PHP-Tests einer noch nicht migrierten App schreibend berührt, gehört die
vollständige Autoload-Migration dieser App als eigener vorbereitender Schritt
zum selben App-Lauf. Sie folgt den lokalen Schreibfreigaben und dem
`test-driven-change`-Vertrag, wenn dabei beobachtbares Verhalten geändert
wird. Reine Dokumentations-, Formatierungs- oder JavaScript-Arbeit löst die
PHP-Migration nicht künstlich aus.

Eine App gilt erst als migriert, wenn

- App- und Testklassen über die vorgesehenen Autoloader auflösbar sind;
- verteilte manuelle Klassen-`require`-Ketten und direkte relative
  LocalBase-Klassenpfade entfernt sind;
- zulässige verbleibende Includes auf Bootstrap, Entrypoint oder Template
  begrenzt und im Kontext nachvollziehbar sind;
- die app-lokalen PHP-Tests einschließlich der relevanten Provider-/Consumer-
  Contracts grün sind; und
- Releaseprüfungen weder Entwicklungsabhängigkeiten noch fremde
  Repositorypfade im Produktionsartefakt finden.

Der beim Beschluss verifizierte Ausgangsstand umfasst keine Composer-Dateien,
zwei produktive manuelle Klassen-Fallbacks in LocalBase und zahlreiche
relative Test-Includes in allen Consumer-Apps. Die nachfolgende Tabelle hält
den inzwischen je App nachgewiesenen Stand fest:

| App | Ausgangsstatus | Verbindlicher Auslöser | Abschlussnachweis |
| --- | --- | --- | --- |
| `brtop` | umgesetzt und verifiziert | vor der Privacy-Provider-Arbeit am 24. August 2026 bestätigt | zentraler app-lokaler Test-Bootstrap, keine verteilten Produktiv-Klassenpfade, grüne PHP-Suite |
| `flzplaner` | verifiziert am 24. August 2026 | abgeschlossen | zentraler lokaler Bootstrap; Einzeltests laden nur diesen Bootstrap, keine Produktiv-Fallbacks oder verteilten Klassenpfade, grüne PHP-Suite |
| `brstunden` | umgesetzt und verifiziert | vor der Privacy-Provider-Arbeit am 24. August 2026 bestätigt | zentraler app-lokaler Test-Bootstrap, keine verteilten Produktiv-Klassenpfade, grüne PHP-Suite |
| `localbase` | verifiziert am 12. August 2026 | abgeschlossen | zentraler lokaler Bootstrap, keine Produktiv-Fallbacks oder verteilten Test-Klassenpfade, grüne PHP-Suite |
| `flz_permission_matrix` | verifiziert am 23. August 2026 | abgeschlossen | zentraler vollständiger Bootstrap und eigener Runner; LocalBase-Laufzeitvertrag nur am zentralen Testübergang; grüne PHP-Suite |
| `flzcalendar` | verifiziert am 24. August 2026 | abgeschlossen | zentraler lokaler Bootstrap mit genau einem LocalBase-Testübergang, keine Produktiv-Fallbacks oder verteilten Klassenpfade, grüne PHP-Suite |
| `flzurlaub` | verifiziert am 12. August 2026 | abgeschlossen | zentraler lokaler Bootstrap, keine verteilten Klassenpfade, grüne PHP-Suite |
| `orgsuite` | verifiziert am 23. August 2026 | abgeschlossen | lokaler Bootstrap und Runner; LocalBase-Laufzeitvertrag nur am zentralen Testübergang; grüne PHP-Suite |
| `flzroom` | verifiziert am 12. August 2026 | abgeschlossen | zentraler lokaler Bootstrap, keine verteilten Test-Klassenpfade, grüne PHP-Suite |
| `flzrecruitment` | verifiziert am 24. August 2026 | abgeschlossen | zentraler lokaler Bootstrap und eigener Autoload-Contract-Test, keine Produktiv-Fallbacks oder verteilten Klassenpfade, grüne PHP-Suite |
| `flzbqplanung` | umgesetzt und verifiziert | vor der Privacy-Provider-Arbeit am 24. August 2026 bestätigt | zentraler app-lokaler Test-Bootstrap, keine verteilten Produktiv-Klassenpfade, grüne PHP-Suite |

Der Status wird nur mit dem jeweiligen verifizierten App-Lauf geändert. Eine
bloße zentrale Dokumentationsänderung erklärt keine App als migriert.

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
| `Controller/ApiResponder.php`, `Model/ModelApiTrait.php`, `Service/AppLogger.php` | BRTop, FlzPlaner, BRStunden; Trait zusätzlich Filzmann Urlaubsplanung | A, **sofort eindeutig / verifiziert** | zustandslose technische Verträge; separates PHP-Paket, beim App-Build geprefixt und gebundelt | mittel: Klassenidentität und BC testen; beseitigt harte LocalBase-App-Pflicht | 1 |
| `js/api`, `js/models`, `js/repositories`, kleine `js/ui`-Primitives | BRTop, FlzPlaner, BRStunden, Filzmann Kalender, Filzmann Urlaubsplanung, Filzmann Raumplaner | A, **sofort eindeutig / verifiziert** | gleiche Basisklassen/Clientprimitives; separates Frontend-Paket, app-lokal gebaut, keine globalen `window.LocalBase`-Kollisionen | hoch: viele Consumer und Ladeordnung; wesentlich für Store-Standalone | 1 |
| app-lokale Test-Support-Verzeichnisse, Assertions, JS-Fakes und Coverage-Tooling | fast alle App-Testläufer/CI | A, **sofort eindeutig / verifiziert**, test-only | separat versioniertes Dev-Paket oder reproduzierbare Testtool-Abhängigkeit; niemals Produktivarchiv | niedrig für Runtime, mittel für CI-Reproduzierbarkeit/Lizenz | 2 |
| `GroupProvisioningService.php` | BRTop, BRStunden | A, **wahrscheinlich / plausible Einordnung** | native, zustandslose Gruppenoperation; Semantik beider Consumer vor Extraktion als Contract bestätigen | mittel: Rechte-/Backend-Negativfälle; keine Basis-App nur für Helfer | 2 |
| `FlzDemoFixtureCatalog.php` | Filzmann Kalender, Filzmann Urlaubsplanung | A-intern oder C, **noch zu prüfen** | gleiche Demo-Semantik und öffentliche Eignung der Organisationsbeispiele prüfen; sonst app-lokal | mittel: interne Namen/Fixtures dürfen nicht in Store-Paket rutschen | 4 |
| Kalender-Value-Objects ohne Persistenz (`CalendarContext`, `HolidayPeriod`, `HolidayCalendar`) | Kalender, Urlaub, Raum | A, **wahrscheinlich** | reine Werte/Serialisierung in isoliertes PHP-Paket; nicht den Cache/Provider versteckt mitnehmen | mittel: Vertrag muss unabhängig vom Laufzeitdienst testbar sein | 2 |
| Organisations-Value-Objects/-Policy/-Snapshot ohne Persistenz | Kalender, Urlaub, Planer, Recruitment | A als Vertragstypen, **wahrscheinlich** | schmale versionierte DTO-/Policy-Bibliothek; gespeicherte Definition bleibt B | hoch: Sicherheitsinvariante und Versionshandshake vollständig testen | 2 |
| `CalendarContextSettingsService`, `HolidayCalendarCacheStore`, `HolidayCalendarService`, `OpenHolidaysClient`, Refresh-Job | Kalender, Urlaub, Raum; Admin | B, **sofort eindeutig / verifiziert** | zentrale Konfiguration, Cache, Provider und Job bleiben eigenständiger Laufzeitdienst mit Version/Deinstallationsvertrag | hoch: Cache/Netzwerk/Update/Deinstallation; externe Store-Abhängigkeit begründen | 1 |
| Organisationspersistenz, Snapshot-Service, Hierarchie-/Rechtequelle, Peerfreigaben | Kalender, Urlaub, Planer, Recruitment, OrgSuite | B, **sofort eindeutig / verifiziert** | fachlich führende organisationsweite Konfiguration und Sicherheitsquelle; öffentliche versionierte API statt interner Klassen | sehr hoch: Berechtigungen, Bestands-AppConfig, Migration und Deny-by-default | 1 |
| Admincontroller, Routen, Settings, Template, Organisationseditor und persönliches Layout | einzelne Fachapp oder OrgSuite als Adapter | B, **sofort eindeutig / verifiziert** | eigene Administration und API beim verantwortlichen Laufzeitdienst; Consumer rendert kein fremdes internes Template | hoch: CSRF/Adminrechte, Assets, App-Store-Zumutbarkeit | 1 |
| Abwesenheits-, Konflikt- und Capability-Events | Filzmann Kalender, Filzmann Urlaubsplanung, FlzPlaner, Filzmann Raumplaner | B, **wahrscheinlich** | appübergreifender In-Process-Eventbus benötigt eine einzige Klassenidentität; schmale versionierte öffentliche Runtime-API | hoch: gebundelte, geprefixte Kopien wären nicht eventidentisch; fehlender Provider muss gültig bleiben | 2 |
| `DemoAccountProvisioningService` mit zentralem Kontoregister | mehrere FLZ-Demo-Packs | B-intern, **wahrscheinlich / nicht migrieren bis Produktentscheid** | gemeinsam gespeicherte Eigentümerschaft verhindert lokale A-Einstufung; für öffentliche Releases optionalen Demo-Lebenszyklus und Entfernung klären | hoch: Konten/Gruppen, Datenschutz, LDAP und Deinstallation | 4 |
| Produktkatalog und `StandaloneAppNavigationService` | alle FLZ-Produkte, OrgSuite, Parent-Installer | A für statischen Vertrag/Adapter oder B als Suite-Dienst, **derzeit nicht entscheidbar** | Produktownership, externe Namen und Updatequelle zuerst entscheiden; keine private Katalogdatei still in Store-App voraussetzen | hoch: Installation, Navigation, interne IDs, Releasekopplung | 3 |
| OrgSuite-Adapterlogik | OrgSuite und einzelne FLZ-Produkte | B, **wahrscheinlich** | Navigation/Adminplatzierung gehört zu einer bewusst versionierten Suite-Laufzeit, nicht in generische Bibliothek | mittel bis hoch: öffentliche Zusatz-App muss sachlich zumutbar sein | 3 |
| Privacy-DTOs, Providerinterfaces, Registry, Aggregation, Self-Service, Admin-Auskunft und künftige Privacy-Jobs; heute im LocalBase-Pilot | LocalBase sowie reale Provider in Filzmann Kalender, Filzmann Raumplaner, Filzmann Urlaubsplanung, Filzmann Assistenzplanung und Filzmann Recruitment | B, **sofort eindeutig / verifiziert**; Ziel durch ADR 0002 entschieden | geschlossene Migration in die Standalone-App `flz_data_protection`; Fachprovider bleiben lokal, kein Daten-Fallback und keine zweite aktive Vertragsquelle | sehr hoch: öffentliche API, Rechte, Versionshandshake, optionale Installation, Consumer-Migration und Rückbau; Store-Gate bis zum Nachweis blockiert | 1 |
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

Für die Privacy-Komponente sind Produktname und App-ID mit
`Data Protection Center` beziehungsweise `flz_data_protection`
entschieden. ADR 0002 ordnet sie als Standalone-App der Kategorie B ein und
ersetzt damit ausschließlich die frühere Zielannahme, die öffentliche
Privacy-Runtime dauerhaft in LocalBase zu belassen.

## Migrationsplan in prüfbaren Schritten

Für Erhaltungsaufwand und Übergangsfristen gilt die kanonische
[Entwicklungsphasenregel](../architecture.md#entwicklungsphase-und-kompatibilitätsbedarf).
Die folgenden Schritte schützen aktuelle Consumer- und Plattformgrenzen;
historische Entwicklungsstände allein erfordern weder Datenübernahme noch
einen zusätzlichen Deprecation-Releasezyklus.

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
   App-ID werden erst danach entschieden. Die Phasenregel entscheidet, welche
   historischen Zustände und Migrationen tatsächlich erhalten werden müssen.
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
   stabilen A-/B-Verträgen stehen, unbenutzte LocalBase-Klassen und Globals
   entfernen. Eine Deprecationfrist ist nur mit konkretem Erhaltungsgrund nach
   der Phasenregel erforderlich. Zu erhaltende Daten werden nicht durch
   Codekopie oder Fremdtabellenzugriff migriert; Rückbaugrenzen bleiben pro
   Schritt dokumentiert.

Jeder Schritt besitzt eine einzelne Eigentümerschaft, kleine Commitgrenze,
Consumerliste und Rückbauentscheidung. Kein Schritt verlangt einen
gleichzeitigen unkontrollierten Umbau mehrerer App-Repositories. Ein
Store-Release hängt nie von einer halb abgeschlossenen Entkopplung ab.

## Folgen und offene Entscheidungen

Umgesetzt und verbindlich ist das Entscheidungs- und Releasemodell. Ebenfalls
verbindlich ist, dass neue Apps nicht automatisch von LocalBase abhängen.
Der erste Kategorie-B-Pilot über LocalBase ist technisch einschließlich seines
Nextcloud-34-Lifecycle-Nachweises abgeschlossen. Im aktuellen Arbeitsbaum
stellt die eigenständige Laufzeit-App `flz_organization` den öffentlichen
Vertrag `OCA\\FlzOrganization\\PublicApi\\V1` Version `1.0` bereit. Die
Berechtigungsmatrix prüft Aktivierung und Version vor der Klassenauflösung und
bleibt bei fehlendem, älterem oder fehlerhaftem Provider fail-closed. Die
app-lokalen Provider- und Consumer-Contract-Tests sind grün; der reale
Installations-, Deaktivierungs-, Entfernungs- und Wiederinstallationsnachweis
für `flz_organization` ist noch offen. Offen bleiben außerdem die weiteren
Kategorie-A-/B-Migrationen und die Lifecycle-Nachweise der übrigen Consumer.

Öffentliche App-Store-Veröffentlichungen sind derzeit nicht freigegeben und
werden später je App einzeln entschieden. Das strategische LocalBase-Ziel ist
keine pauschale Umbenennung der heutigen Misch-App: zustandslose technische
Hilfen werden schrittweise als reproduzierbar gebundelte,
namespace-isolierte Kategorie-A-Bibliotheken aus der Runtime-Pflicht gelöst.
Eine zusätzliche Nextcloud-App nur für solche Hilfsfunktionen ist unzulässig.
Die in der Klassifikation belegten persistenten Organisations-, Kalender-,
Administrations- und Jobanteile bleiben Kategorie B, bis ihr eigenständiger
Owner und eine kontrollierte Consumer-Migration nachgewiesen sind. Weder eine
Paketextraktion noch die Abschaltung der heutigen LocalBase-App ist damit als
bereits umgesetzt behauptet.

Noch zu entscheiden sind insbesondere Governance und Maintainerkreis,
die spätere öffentliche Zumutbarkeit verbleibender Kategorie-B-Dienste, genaue
Composer-/Frontend-Paketgrenzen, Prefixing-Werkzeug, Lizenzfreigabe aller
gebündelten Bestandteile, konkrete Store-Ziele je App, Umgang mit Demo-Packs und
Produktkatalog sowie die Deinstallationspolitik für zentrale AppConfig-Daten.

Verworfen sind eine pauschale LocalBase-Einstufung, bloßes Umbenennen,
Quellcodekopien in Consumer-Repositories, Submodule, Composer-Path-Repositories
als Produktion, direkte Fremdtabellenzugriffe, zyklische App-Abhängigkeiten,
eine Basis-App nur für Hilfsfunktionen und oberflächliche Grep-Gates mit
irreführendem Grünurteil.

## ZM-04: Klassifikation Kapazitäts-/Fallbackplanung (2026-11-28, entschieden durch Simon: Kategorie B)

Die Kapazitäts-/Fallbackplanung (`flz_kapazitaet`, heute als explizites
Navigationsziel in der OrgSuite geführt, noch ohne eigenes Repository im
Inventar) wird nach diesem Vertrag wie folgt vorgeschlagen:

- **Kategorie B: eigenständige Nextcloud-Laufzeit-App.** Kapazitätsplanung
  besitzt fachliche Persistenz (Schicht- und Verfügbarkeitsdaten mit
  Personenbezug), eigene Berechtigungslogik und mindestens einen
  Background-/Konsistenzpfad. Sie ist damit kein Kandidat für eine gebündelte
  Bibliothek (Kategorie A) und kein bewusst lokaler Code (Kategorie C).
- **Kein Kategorie-A-Anteil in flz_shared ohne zweiten Consumer.** Reine
  Value-Objects (z. B. Belegungsintervalle) dürfen erst extrahiert werden, wenn
  mindestens zwei Apps sie semantisch gleich benötigen und der Vertrag
  app-übergreifend testbar ist.
- **Optionale Kopplung an bestehende Fachapps ausschließlich über öffentliche
  Verträge:** Abwesenheits-/Konfliktdaten über `OCA\FlzOrganization\PublicApi\V1`
  (AbsenceQuery/ScheduleConflict), Organisations- und BR-Rollen über die
  V1-Snapshots; ein fehlender Provider ist ein gültiger Standalone-Zustand.
- **Kein direkter Zugriff** auf Tabellen, Controller oder Assets anderer
  Fachapps; keine still voraussgesetzte private App in einem Store-Paket.

**Entscheidung 28.11.2026 (Simon):** Der Vorschlag ist freigegeben —
`flz_kapazitaet` bleibt eigenständige Kategorie-B-Laufzeit-App; die Kopplung
erfolgt ausschließlich über öffentliche V1-Verträge.

## Datierte Änderung 28. November 2026 — FR-09 als verbindliche Regel geschlossen

Externe oder Inter-App-HTTP-APIs folgen ab sofort verbindlich dieser Regel
(Entscheidung Simon, 28. November 2026): Vor Veröffentlichung einer solchen
API ist zu prüfen, ob ein Nextcloud-Standard (Events, Capabilities, OCS)
genügt. Nur wenn nachweislich keiner genügt, wird ein versionierter
OCS-/OpenAPI-Vertrag mit Autorisierung, Fehlersemantik und
Kombinations-Contract-Tests freigegeben. App-lokale Events (z. B. ZM-07
`ApplicationHiredEvent`) sind der bevorzugte Mechanismus und bleiben ohne
HTTP-Vertrag.

## Datierte Änderung 28. November 2026 — LocalBase vollständig zurückgebaut

Nach Abschluss der Kategorie-A/B/C-Migration (alle Consumer auf
`OCA\FlzOrganization\PublicApi\V1\*`, `OCA\FlzPermissionMatrix\PublicApi\V1\*`,
`OCA\FlzDataProtection\PublicApi\V1\*` bzw. app-lokale Komponenten und
`vendor/flz-shared` umgestellt; Freigabe Simon, 28. November 2026) wurde die
Kategorie-B-Laufzeitapp `localbase` vollständig zurückgebaut: kein `lib/`, keine
Registrierungen in `appinfo/info.xml`, keine Templates/JS/CSS/l10n/resources mehr
(Version 0.13.0, PR nextcloud-localbase#12). LocalBase bleibt als Test-Harness
(`tests/`, inkl. PhpTestRunner, js-runner, fake-dom, Coverage-Tooling) und leere
Compat-Stage-App bestehen; der Workspace-Inventar-Eintrag wechselt auf `tool`.
Damit ist die in diesem ADR verankerte Kategorie-B-Pilotrolle von `localbase`
erledigt; künftige gemeinsame Bausteine entstehen nur noch als Kategorie-A-Bibliothek
(`flz_shared`, gebündelt pro App) oder in einer fachlich zuständigen Kategorie-B-App.
