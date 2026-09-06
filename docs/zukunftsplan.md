# Systemweiter Zukunftsplan

Stand: 2. September 2026

Diese Datei ist die einzige aktive systemweite Planungsquelle des Workspaces.
Sie bündelt openDesk-/Nextcloud-Future-Readiness, app-übergreifende
Migrationen, Datenschutz-Rollout, Delivery-Gates und noch nicht freigegebene
Suite-Module. Sie ergänzt die verbindlichen Architekturentscheidungen,
ersetzt sie aber nicht.
Insbesondere bleibt die Einteilung von gemeinsamem Code und Laufzeitdiensten
in [`ADR 0001`](architecture-decisions/0001-shared-code-runtime-and-app-store.md)
normativ.

Weitere normative Quellen sind `docs/privacy-architecture.md`,
`docs/privacy-provider-guide.md`,
`docs/architecture-decisions/0002-standalone-privacy-platform.md`,
`docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md` und
`docs/architecture-decisions/0004-app-local-temporary-admin-full-access.md`.

Der Plan bildet den aktuellen Soll-/Ist-Stand ab. Er ist weder eine feste
openDesk-Kompatibilitätszusage noch ein historisches Auditprotokoll. Eine
konkrete openDesk- oder Nextcloud-Version wird erst für einen benannten
Installations- oder Releasekandidaten geprüft.

## Planungsgrenzen

| Inhalt | Kanonische Ablage | Darf Aufgaben enthalten? |
| --- | --- | --- |
| systemweite Plattform-, Cross-App-, Suite-, Delivery- und Rolloutarbeit | diese Datei | ja |
| app-spezifische Produktarbeit | `ROADMAP.md` der zuständigen App | ja |
| dauerhafte Architekturentscheidung | `docs/architecture-decisions/` oder app-lokale Architekturquelle | nein; sie begründet Aufgaben, ersetzt sie aber nicht |
| dauerhaft geltende Arbeits- und Sicherheitsregel | `AGENTS.md`, Architekturdokument oder Skill | nein |
| aktueller implementierter App-Umfang | app-lokale `README.md` | nein |
| abgeschlossene App-Änderung | app-lokale `CHANGELOG.md`, Code, Tests und Git-Historie | nein |
| abgelöster systemweiter Planstand | ADR, geltende Dokumentation und Git-Historie; keine zweite Plan-Datei | nein |
| noch nicht bewertete Beobachtung | `docs/learning-candidates.md` | nein |

`offen` bedeutet planbar, aber nicht automatisch freigegeben. `freigegeben`
benötigt weiterhin einen konkreten Auftrag für jedes betroffene Repository.
`blockiert` benennt ein noch fehlendes Entscheidungs- oder Nachweisgate.
Erledigte Details werden aus diesem Plan entfernt. App-lokal werden der
aktuelle Umfang in `README.md` und Änderungen in `CHANGELOG.md` dokumentiert;
systemweit belegen Code, Tests, ADRs, geltende Dokumentation und Git-Historie
den Abschluss.

## Zielzustand

- Jede App bleibt eine standardkonforme, getrennt versionierte Nextcloud-App.
- Identität, Gruppen, Konfiguration, Persistenz, Dateien, Jobs, HTTP und
  Logging verwenden dokumentierte Nextcloud-Abstraktionen.
- Fachliche Inter-App-Kommunikation läuft über kleine, explizite und
  versionierte öffentliche Verträge. Interne Tabellen, private
  Konfiguration, Dateipfade, Controller, Assets oder Implementierungsklassen
  anderer Fachapps sind keine Schnittstellen.
- Optionale Provider dürfen fehlen, deaktiviert, vorübergehend fehlerhaft
  oder inkompatibel sein, ohne die fachlich eigenständige Consumer-App
  unkontrolliert zu brechen.
- Datenbank- und Dateizugriffe bleiben für PostgreSQL und von Nextcloud
  verwaltetes Object Storage portabel.
- Jobs und persistenter Zustand hängen weder von einem bestimmten Host noch
  von genau einem Webprozess oder einem dauerhaft beschreibbaren
  Container-Dateisystem ab.
- Builds und Updates sind reproduzierbar; öffentliche Verträge besitzen
  Provider- und Consumer-Contract-Tests sowie eine nachvollziehbare
  Kompatibilitätsstrategie.

## Geprüfter Ist-Bestand

Erster Root-Audit: 1. September 2026; Planungs- und Mindestversionsaudit:
2. September 2026. Geprüft wurden das Parent-Repository,
alle in `config/workspace-repositories.tsv` registrierten App-Repositories,
ihre lokalen Regeln, Architekturunterlagen, Metadaten, Produktionscode und
relevanten Tests. Vorhandene Fremdänderungen in `adplaner` und `localbase`
wurden nur gelesen und nicht verändert.

Bereits sauber beziehungsweise als belastbare Grundlage vorhanden:

- IAM- und Gruppenentscheidungen verwenden Nextclouds Benutzer-, Gruppen-
  und Session-APIs. Es gibt keine fachliche Laufzeitabhängigkeit von Nubus-,
  LDAP- oder Keycloak-Interna und keine gefundene Annahme einer impliziten
  Nested-Group-Vererbung.
- App-eigene Persistenz verwendet `IDBConnection`, Nextclouds QueryBuilder
  und deklarative Migrationen. Es wurden keine MySQL-/MariaDB-spezifischen
  SQL-Fragmente oder direkten Zugriffe auf Tabellen anderer Fachapps
  gefunden.
- Fachdateien verwenden Nextclouds `IRootFolder`; private Importanlagen
  verwenden `IAppData`. Temporäre lokale Dateien sind auf begrenzte
  Verarbeitungsschritte beschränkt und keine persistente Wahrheit.
- Wiederkehrende Arbeit ist über Nextcloud-Background-Jobs registriert. Es
  wurde keine fachliche Abhängigkeit von eigenem Host-Cron, systemd oder
  einem lokalen Prozesszustand gefunden.
- Externe HTTP-Zugriffe verwenden den Nextcloud-HTTP-Client. App- und
  Benutzerkonfiguration verwenden Nextclouds AppConfig/UserConfig; sensible
  Kalenderzugänge werden zusätzlich über den Nextcloud-Kryptodienst
  geschützt.
- Die fachlich führenden Daten bleiben app-lokal. Abwesenheiten,
  Planungskonflikte, Capabilities, Datenschutz- und Berechtigungsprovider
  werden bereits über typisierte Eventverträge ausgetauscht. Fehlende
  optionale Listener liefern leere, `missing`, `partial`, `failed`, `stale`
  oder `unavailable` Zustände statt fremder Datenfallbacks.
- `filzmann_data_protection` und `filzmann_permission_matrix` besitzen
  ausdrücklich öffentliche `PublicApi/V1`-Verträge. Der zentrale
  Permission-Contract-Test lädt alle neun realen Provider; für Privacy fehlt
  noch eine entsprechende Root-Matrix mit allen realen Providern (FR-11).
- Der AD-Suite-Build, das Repositoryinventar und die Prüfung künftiger
  Nextcloud-Hauptversionen besitzen bereits reproduzierbare Root-Gates.

Die offizielle openDesk-Dokumentation bestätigt die relevanten
Plattformgrenzen: Kubernetes-/Helm-Deployment, föderiertes IAM, eine
PostgreSQL-Option für Nextcloud sowie S3-basierten Nextcloud-Speicher. Die
Nextcloud-Entwicklerdokumentation bestätigt OCP-Events für
Inter-App-Kommunikation, OCS für öffentliche HTTP-APIs, QueryBuilder,
Filesystem/AppData und Nextcloud-Background-Jobs als vorgesehene
Abstraktionen. Maßgebliche Quellen:

- [openDesk-Architektur](https://docs.opendesk.eu/operations/architecture/)
- [openDesk-Release-Matrix](https://releases.opendesk.eu/)
- [openDesk External Services](https://docs.opendesk.eu/operations/external-services/)
- [openDesk Data Storage](https://docs.opendesk.eu/operations/data-storage/)
- [Nextcloud Events](https://docs.nextcloud.com/server/latest/developer_manual/basics/events.html)
- [Nextcloud Controller und OCS](https://docs.nextcloud.com/server/latest/developer_manual/basics/controllers.html)
- [Nextcloud Storage und Datenbank](https://docs.nextcloud.com/server/latest/developer_manual/basics/storage/index.html)

## Aktuelle Inter-App-Verträge

| Owner / Quelle | Nutzer / Ziel | Zweck | Mechanismus und Nextcloud-Standard | Öffentlicher Vertrag / Version | Autorisierung und Ausfall | Contract-Nachweis | Bewertung |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `adurlaub` | `adcalendar`, `adplaner` | begrenzte read-only Abwesenheitsabfrage | typisierte LocalBase-Events über OCP Event Dispatcher | fachlich als Kalendervertrag V1 dokumentiert, aber noch kein eigener `PublicApi/V1`-Namespace | der aufrufende Fachservice prüft seinen Scope; ohne Listener leer | LocalBase-Eventtests, Provider- und Consumer-Tests vorhanden | F2, FR-02 |
| `adcalendar`, `adplaner` | `adurlaub`; außerdem interne Planerabfragen | read-only Planungskonflikte vor Genehmigung | `ScheduleConflictQueryEvent` über OCP Event Dispatcher | kleiner expliziter LocalBase-Vertrag, noch ohne unabhängigen Versionshandshake | keine Mutation; fehlender Provider bleibt zulässiger Standalone-Zustand | LocalBase-, Provider- und Consumer-Tests vorhanden; aktueller uncommitteter Planerstand bleibt Fremdarbeit | F2, FR-02 |
| AD-Fachapps | LocalBase-Consumer | optionale Integrationsfähigkeiten | `IntegrationCapabilityQueryEvent` über OCP Event Dispatcher | explizite Capability-Schlüssel, leerer Snapshot zulässig, aber kein unabhängiger Versionshandshake | Capability erweitert niemals Rechte | LocalBase- und Listenertests vorhanden | F2, FR-02 |
| `filzmann_data_protection` als API-Owner und Aggregator | Fachapps als registrierte Provider; Self-Service und Retention-Review als Consumer | Art.-15-Daten und Retention-Vorschau | OCP Event Dispatcher mit app-eigener öffentlicher Provider-API | `OCA\FilzmannDataProtection\PublicApi\V1`, Descriptor-Version `1.0` | Session-Subject, providerweise Fehlerisolation, kein Datenfallback | app-lokale Provider-/Consumer-Tests und synthetisches API-Kit; zentrale reale Provider-Matrix fehlt | F1, FR-11 |
| `filzmann_permission_matrix` als API-Owner und Aggregator | Fachapps als registrierte Provider; Matrixscanner als Consumer | read-only Berechtigungsbeschreibung | OCP Event Dispatcher mit app-eigener öffentlicher Provider-API | `OCA\FilzmannPermissionMatrix\PublicApi\V1`, Descriptor-Version `1.0` | Matrixrechte bleiben lokal; unbekannte/inkompatible Provider werden nicht als erlaubt gewertet | zentraler Contract-Test gegen alle neun realen Provider und app-lokale Tests | F0 |
| `localbase` | AD-Apps, OrgSuite, BR-Apps und Matrix | Organisation, Kalenderkontext, Produktkatalog, Navigation und technische Hilfen | direkte DI-/PHP-Verträge, OCP-Events sowie heute noch LocalBase-Assets/Template | einzelne Payloadversionen vorhanden, aber keine einheitliche öffentliche Kategorie-B-Grenze | viele Consumer behandeln fehlende optionale Provider sauber; fehlende LocalBase kann vor einem kontrollierten Handshake scheitern | zahlreiche Provider-/Consumer-Smokes, aber keine vollständige Installations-/Versionsmatrix | F2/F3, FR-01 und FR-02 |
| `localbase` als Pilot-Registry und Aggregator | `adroom`, `adurlaub` als Retention-Provider | read-only Retention-`REVIEW`-Vorschau | `RetentionProviderRegistryEvent` über OCP Event Dispatcher | LocalBase-Pilot ohne eigenständige öffentliche Versionsgrenze | keine Ausführung oder Mutation; fehlende Provider ergeben keine Löschfreigabe | app-lokale Provider-/Aggregator-Tests, aber keine Standalone-Migrationsmatrix | F2, FR-10 |
| `orgsuite` | AD-/BR-Fachapps | gemeinsame Navigation und AD-Administration | Nextcloud-Navigation, Template-Event, `IAppManager` und LocalBase-Katalog | kein fachlicher Datenvertrag; OrgSuite ist alleiniger Menüowner | Zielapp prüft Rechte selbst; Einzelproduktzustand ist vorgesehen | Navigations-, Asset- und Entkopplungstests | F0; Kataloganteil Teil von FR-01 |
| Nextcloud DAV-App | `adcalendar` | persönlicher Nextcloud-CalDAV-Kalender | app-eigener Port mit Adapter auf `OCA\DAV\CalDAV\CalDavBackend` | bewusst begrenzter, aber privater Nextcloud-Runtimevertrag | Providerfehler isoliert; führende AD-Daten werden nicht zurückgerollt | Adapter- und Synchronisationstests | F2, FR-05 |
| Nextcloud Groupfolders-App | `filzmann_permission_matrix` | read-only Team-Folder-Rechte | app-eigener Port auf private `FolderManager`-Runtime | bewusst auf Groupfolders 22.x/Nextcloud 34 begrenzte ADR-Ausnahme | inkompatibel/unvollständig ergibt `UNKNOWN`/`PARTIAL` | Unit-, Negativ- und Source-Compatibility-Gate | kontrolliertes F2, FR-06 |

Es wurden keine Inter-App-HTTP-Endpunkte gefunden. Die vorhandenen
JSON-Controller bedienen die jeweilige same-origin Weboberfläche und sind
deshalb nicht allein wegen ihrer Existenz auf OCS umzustellen. Sobald ein
Endpoint als externer oder Inter-App-Vertrag veröffentlicht wird, gilt die
OCS-/OpenAPI-Regel unten.

## Mindestversion Nextcloud 33

### Ergebnis der Vorprüfung

Eine Absenkung von Nextcloud 34 auf 33 erscheint ohne große
Architekturänderung möglich, ist im aktuellen Stand aber noch nicht
freigabefähig. [openDesk 1.18.0](https://www.opendesk.eu/de/blog/opendesk-1-18)
und die [aktuelle Komponententabelle](https://docs.opendesk.eu/operations/introduction/)
weisen Nextcloud 33.0.7 aus. Der dazu gepinnte
[offizielle Nextcloud-Quellstand `v33.0.7`](https://github.com/nextcloud/server/tree/v33.0.7)
wurde als Commit
`9f1a39b0622a66607fa4ef4848210cb3f34b2fe3` statisch geprüft. Nextcloud 33
unterstützt laut
[Systemanforderungen](https://docs.nextcloud.com/server/33/admin_manual/installation/system_requirements.html)
PHP 8.3; die PHP-Mindestversion der betroffenen Apps muss daher nicht
abgesenkt werden.

Alle von den ursprünglich acht auf 34/34 begrenzten Apps importierten öffentlichen
OCP-Klassen sind in Nextcloud 33.0.7 vorhanden. Das ist ein positives
statisches Indiz, aber kein Installations-, DI-, Migrations-, Job-, HTTP-,
Asset- oder UI-Nachweis.

| App | Vorprüfung gegen 33.0.7 | Vor einer Absenkung zwingend |
| --- | --- | --- |
| `adplaner` | Nextcloud 33.0.7 Fresh Install und Upgrade auf 34.0.2 mit synthetischen Bestandsdaten grün; DI, Migrationen, Rollen-/Konfliktpfade, Standalone-Betrieb, Privacy-/PermissionProvider, Assets und mobile Oberfläche geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34 |
| `localbase` | Nextcloud 33.0.8 Fresh Install und Upgrade auf 34.0.3 mit synthetischem Kalender- und Cachezustand grün; DI, Migration, Job, API, Rechte, Assets, UI und repräsentative Consumer-/Capability-Kombination geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34 |
| `adcalendar` | Nextcloud 33.0.8 Fresh Install und Upgrade auf 34.0.3 mit 84 synthetischen Facheinträgen sowie 28 DAV-Kalendern/84 DAV-Objekten grün; DI, Migrationen, Job, API, Rechte, DAV-Rename, Privacy-/PermissionProvider, Assets, UI und Standalone-Betrieb geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34; privater DAV-Port bleibt je Plattformfreigabe am Source-/Runtime-Gate |
| `adurlaub` | Nextcloud 33.0.8 Fresh Install und Upgrade auf 34.0.3 grün; DI, App-Suite, API, Rechte, Assets, UI sowie aktueller `stable33`-/`stable34`-Stand geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34 |
| `orgsuite` | Nextcloud 33.0.8 Fresh Install und Upgrade auf 34.0.3 grün; DI, App-Suite, Navigation, Assets, UI sowie aktueller `stable33`-/`stable34`-Stand geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34 |
| `adroom` | Nextcloud 33.0.8 Fresh Install und Upgrade auf 34.0.3 grün; DI, App-Suite, API, Rechte, Assets, UI sowie aktueller `stable33`-/`stable34`-Stand geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34 |
| `adrecruitment` | Nextcloud 33.0.8 Fresh Install und Upgrade auf 34.0.3 grün; DI, Jobs, App-Suite, API, Rechte, Assets, UI sowie aktueller `stable33`-/`stable34`-Stand geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34 |
| `adbqplanung` | Nextcloud 33.0.7 Fresh Install und Upgrade auf 34.0.2 mit synthetischen Bestandsdaten grün; DI, Migrationen, Rollen-/Adminschutz, Curriculum, Kalender, Privacy-/PermissionProvider, Assets und Oberfläche geprüft | erfüllt; App-Metadaten deklarieren 33 bis 34 |

Vier Apps deklarieren bereits `min-version="29"` und brauchen für dieses Ziel
keine Absenkung: `brtop`, `brstunden`, `filzmann_permission_matrix` und
`filzmann_data_protection`. Ihre tatsächliche NC-33-Funktion bleibt trotzdem
Teil einer openDesk-Installationsmatrix.

Die Root-Skripte `scripts/build-ad-suite-release.sh` und
`scripts/verify-ad-suite-delivery.sh` validieren jetzt einen Bereich: Jede
enthaltene App muss den festen OpenDesk-Boden 33 und die explizit über
`NEXTCLOUD_TARGET_MAJOR` benannte Release-Zielmajor enthalten. Standardziel
des aktuellen Delivery-Pfads bleibt 34. Damit ist die Obergrenze nicht mehr
fest verdrahtet; Ziel 35 oder eine spätere Major wird erst akzeptiert, wenn
jede enthaltene App diese Major nachweislich deklariert. Alle acht ursprünglich
auf 34/34 begrenzten Apps haben das app-lokale MIN-33-Gate abgeschlossen.

### Freigabegate MIN-33

1. Statisch gefundene Quellprobleme app-lokal testgetrieben korrigieren.
2. Einen isolierten Nextcloud-33.0.7-Pfad mit PHP 8.3 oder 8.4 bereitstellen;
   keine bestehende NC-34-DDEV-Instanz umschalten.
3. Je App Fresh Install, Aktivierung/DI, Migrationen, Kern-API, Jobs,
   mindestens ein JavaScript- und CSS-Asset sowie die sichtbare Oberfläche
   prüfen. Persistente Apps erhalten zusätzlich einen Upgradepfad 33 nach 34
   mit synthetischen Bestandsdaten.
4. Standalone- und relevante Kombinationen einschließlich LocalBase,
   OrgSuite, Privacy-App, Permission-Matrix und optional fehlender Provider
   prüfen.
5. Erst nach lückenlos grüner Evidenz die jeweilige `info.xml` und
   Release-Dokumentation ändern und das bereits bereichsbasierte Root-Gate
   erneut ausführen. Eine rote oder nur statische App bleibt auf 34 begrenzt
   und blockiert das betroffene Bundle, nicht die ehrliche Einzelbewertung
   anderer Apps.

## Offene Future-Readiness-Punkte

Prioritäten: P0 blockiert einen belastbaren Installations-/Releasekandidaten,
P1 gehört vor einen openDesk-Piloten, P2 wird beim Berühren des Bereichs
geschlossen.

| ID | Klasse / Priorität | Apps / Grenze | Notwendige Änderung und Tests | Status / Gate |
| --- | --- | --- | --- | --- |
| FR-01 | F3 / P0 | `localbase` und alle heutigen Consumer; Packaging und unabhängige Installation | Die in ADR 0001 angenommene Kategorie-A/B/C-Migration inkrementell ausführen: zustandslose Hilfen reproduzierbar und namespace-isoliert bündeln, Kategorie-B-Dienste eigenständig lassen, lokale Fachlogik lokal halten. Je Pilot und Consumer Provider-/Consumer-Contracts sowie saubere Installations-, Update-, Deinstallations- und Rollbackmatrix ausführen. | offen; ADR-Entscheidung vorhanden, Umsetzung ausdrücklich appweise und nur mit Schreibfreigabe je Repository |
| FR-02 | F2 / P0 | `localbase`, AD-/BR-Consumer, OrgSuite und Matrix; öffentliche Runtime-API | Vor der Kategorie-B-Migration Owner, kleinste API, `PublicApi/V1`-Grenze, Aktivierungs-/Versionshandshake, Fehlersemantik und additive Kompatibilität der Organisations-, Kalender-, Capability- und Katalogverträge festlegen. Keine parallele zweite Datenquelle schaffen. | offen; Architekturentscheidung und anschließend ausdrücklich freigegebener Cross-App-Lauf erforderlich |
| FR-03 | F2 / P0 | alle Apps; reale openDesk-/Nextcloud-Laufzeit | Das vorstehende Gate `MIN-33` appweise ausführen und anschließend den echten Versionsbereich in den App-Metadaten abbilden; das Root-Delivery-Gate prüft bereits Boden 33 plus explizite Zielmajor. | erledigt am 2026-09-06; alle acht ursprünglich auf 34/34 begrenzten Apps deklarieren nach app-lokalen Fresh-Install-/Upgrade-Nachweisen 33 bis 34; Abschlussmatrix unter `build/compatibility-min33-2026-09-06-current-tags`, `verify-nextcloud-future-compatibility` bleibt für jede künftige Obergrenze verbindlich |
| FR-04 | F2 / P1 | alle Apps mit Tabellen/Migrationen; PostgreSQL | Einen reproduzierbaren PostgreSQL-Pfad für Fresh Install, Upgrade mit synthetischen Bestandsdaten und relevante Repository-/Transaktionsfälle etablieren. Der statische Audit fand keine DBMS-spezifische SQL-Logik, die heutige Evidenz beweist aber keine vollständige PostgreSQL-Laufzeit. | offen; DDEV-/CI-Umgebungsänderung benötigt gesonderte Freigabe |
| FR-05 | F2 / P1 | `adcalendar`; Nextcloud-CalDAV | Prüfen, ob eine dokumentierte öffentliche Nextcloud-OCP-/DAV-Schnittstelle den heutigen privaten `CalDavBackend`-Adapter inzwischen vollständig ersetzt. Falls nein, Ausnahme, unterstützte Plattformmatrix und kontrollierten Fehlerfall je Release weiter prüfen. Keine zweite Kalenderwahrheit und keinen Loopback-HTTP-Eigenbau einführen. | offen; bestehender Port begrenzt das Risiko, öffentliche Alternative/Releasegate noch zu entscheiden |
| FR-06 | F2 / P1 | `filzmann_permission_matrix`; Groupfolders | Die eng begrenzte private 22.x-Ausnahme nur solange fortführen, wie kein öffentlicher Upstream-Vertrag existiert. Bei jedem betroffenen Release offizielles Quellkompatibilitätsgate und Negativfälle ausführen; neue Versionen bleiben bis zur Entscheidung `UNKNOWN`. | kontrolliert offen; ADR 0003 und Gate sind umgesetzt, keine pauschale Freigabe künftiger Versionen |
| FR-07 | F2 / P1 | `adrecruitment`; Container-/Packaginggrenze | Für die optionale PDF-Textextraktion entscheiden, ob `pdftotext`/Ghostscript eine dokumentierte Image-/Deploymentvoraussetzung bleibt oder durch einen reproduzierbar gebauten Adapter ersetzt wird. Harte Suchpfade und `proc_open` dürfen keine stillschweigende Funktionszusage erzeugen. Tests müssen „verfügbar“, „nicht verfügbar“, Timeout, Größenlimit und unveränderte Fachfunktion ohne Engine belegen. | offen; aktuelle Implementierung fällt kontrolliert auf „nicht verfügbar“ zurück, Packagingentscheidung fehlt |
| FR-08 | F2 / P1 | `brtop`, `adrecruitment`, `adcalendar`; Object Storage und horizontale Ausführung | Die bereits verwendeten Nextcloud-Datei-/AppData-/DAV-Abstraktionen in einer S3/Object-Storage-Zielmatrix und, soweit zustandsrelevant, mit getrennten Web-/Jobprozessen prüfen. Belegen, dass Dateiexport, Importanlage und Kalenderabgleich keine lokalen Pfade oder Prozessspeicher als Wahrheit voraussetzen. | offen; statisch sauber, reale openDesk-artige Integration noch nicht nachgewiesen |
| FR-09 | F2 / P2 | künftige öffentliche HTTP- oder externe Inter-App-APIs | Vor Veröffentlichung prüfen, ob ein vorhandener Nextcloud-Standard genügt. Eigene Daten-APIs bevorzugt als versionierte OCS-Endpunkte mit expliziten Typen, Fehlern, Authentifizierung/Autorisierung und OpenAPI-Schema bereitstellen. Provider-/Consumer-Tests müssen alte/neue Kombinationen und unbekannte additive Felder abdecken. | ereignisgetriggert; aktuell kein solcher Inter-App-HTTP-Vertrag gefunden |
| FR-10 | F2 / P0 | `localbase`, `adroom`, `adurlaub`, `filzmann_data_protection`; Retention-API | Den LocalBase-Piloten `RetentionProviderRegistryEvent` kontrolliert auf den bereits vorhandenen Standalone-Vertrag `OCA\FilzmannDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent` migrieren. Owner, Descriptor-/Versionsprüfung, `REVIEW`-Semantik und fehlende beziehungsweise inkompatible Provider festlegen; alte/neue Kombinationen, Update, Deinstallation und Rollback testen. | offen; ADR 0002 bestimmt die Standalone-Privacy-App als Zielowner, Cross-App-Umsetzung benötigt Schreibfreigabe je Repository |
| FR-11 | F1 / P1 | Parent-Test gegen alle Privacy-V1-Provider | Einen Root-Contract-Test ergänzen, der alle realen PersonalDataProvider gegen die echten Standalone-V1-Klassen lädt und mindestens Descriptor, unterstützten Subject-Typ, Ergebnisstatus, Cursorfortschritt und Registrierungsfehler prüft. App-lokale Tests mit vereinfachten Stubs bleiben ergänzend, aber kein Ersatz. | offen; klein und ohne Architekturentscheidung, wegen app-spezifischer Konstruktoren nicht in diesem Dokumentationslauf improvisiert |
| FR-12 | F2 / P2 | Parent-Governance und alle getrennten App-Repositories | Die Root-Regel „Improve what you touch“ bei der nächsten ausdrücklich freigegebenen Governance-Projektion in den lokal vollständigen App-Regelblock übernehmen und die Parent-Projektionschecks entsprechend erweitern. App-lokale Arbeit darf nicht vom Vorhandensein des Parent-Workspaces abhängen. | erledigt am 2026-09-05; Governance-Vertrag V2 ist in alle registrierten App-Repositories projiziert und der Parent-Projektionscheck prüft Entwicklungsphase sowie Prüfaufwand gegen die kanonischen Root-Quellen |

## Datenschutz, Berechtigungen und Adminzugriff

| ID | Priorität | Systemweite Aufgabe | Status / Gate |
| --- | --- | --- | --- |
| DP-01 | P0 | Den LocalBase-Retention-Piloten kontrolliert auf `filzmann_data_protection` migrieren und LocalBase erst nach grüner Installations-, Update-, Deinstallations- und Rückbaumatrix abbauen. | offen; fachliche Ownerentscheidung und Cross-App-Auftrag für jeden Provider erforderlich; deckt FR-10 ab |
| DP-02 | P1 | Einen Root-Contract-Test gegen alle realen Privacy-V1-Provider ergänzen; Descriptor, Subject-Typ, Status, Cursorfortschritt und Registrierungsfehler gegen die echten Standalone-V1-Klassen prüfen. | offen; deckt FR-11 ab |
| DP-03 | P1 | LocalBase-eigene persönliche UI-Werte und Demo-Registry vollständig inventarisieren; OrgSuite-Nichtanwendbarkeit bei Scopeänderungen neu bewerten. | offen; Umsetzung bleibt app-lokal |
| DP-04 | P1 | Für die Adminfreigabehistorie des Datenschutz-Centers Aufbewahrung, Sperren und eine reine Preview-Policy entscheiden, bevor Retention-Ausführung erwogen wird. | Entscheidung offen; keine automatische Löschung |
| DP-05 | P0 | ADR 0004 appweise umsetzen: Nextcloud-Adminstatus erteilt keinen fachlichen Vollzugriff; notwendige Freigaben bleiben app-lokal, auditierbar und höchstens 24 Stunden gültig. | Architektur angenommen; App-Roadmaps und tatsächliche Nachweise sind einzeln maßgeblich |
| DP-06 | P1 | Native Files-/Share-/Groupfolders-/Calendar-Rechte zuerst über öffentliche Verträge vervollständigen, danach Fremd-Apps read-only inventarisieren. | teilweise; unbekannte Abdeckung bleibt `UNKNOWN`, `UNSUPPORTED`, `partial` oder `missing` |
| DP-07 | P2 | Lifecycle, Retention-Ausführung und ein Vollständigkeits-/Release-Gate erst nach belastbarer Beschäftigungsquelle, Policy-, Nebenläufigkeits- und Rollbackentscheidung einführen. | blockiert bis zu den Entscheidungen; Preview bleibt `REVIEW` |

Die Pflicht, PersonalDataProvider und PermissionProvider bei jeder betroffenen
App-Änderung mitzupflegen, ist bereits dauerhafte Governance und keine offene
Planaufgabe. Die umgesetzte Portfoliozuordnung der Berechtigungsmatrix und die
explizite RC-Bereinigung werden nur in ADR, Code, Tests und Git-Historie belegt.

## Vorgemerkte Suite-Module und Querschnittsvorhaben

Die folgenden Vorhaben sind nicht freigegeben. Eine Freigabe beginnt mit
Owner-, Produktgrenzen-, Daten-, Rechte-, Datenschutz-, Standalone- und
Releaseentscheidung; ein Tabellen-, Controller- oder Assetzugriff zwischen
Fachapps bleibt ausgeschlossen.

Für alle personalbezogenen Zukunftsmodule gelten bereits vor einer
Produktentscheidung harte Schutzgrenzen: keine Leistungs-, Kooperations-
oder Vermittelbarkeitsscores, Rankings, Blacklists, Diagnosen oder
individuellen Ausfallprognosen; keine automatischen Sanktions-, Einstellungs-
oder Teamentscheidungen und keine stillschweigende Erweiterung von Abruf-,
Kapazitäts- oder Verfügbarkeitspflichten. DPA-Fallsteuerung,
Schichtvermittlung, Ausfallgeld, Kapazitätsplanung und AZK-Ausgleich bleiben
getrennte Fachkontexte, bis ein ausdrücklich freigegebener Vertrag ihre
kleinste notwendige Verbindung beschreibt.

| ID | Vorhaben | Zielgrenze | Nächste Entscheidung |
| --- | --- | --- | --- |
| ZM-01 | Schichtvermittlung | mögliche eigenständige Fachapp für Anfragen, Angebote und nachvollziehbare Vermittlung; AdPlaner bleibt Owner der Dienstplanung | Bedarf, Rollen, Zustände, Eskalation und kleinster optionaler Vertrag |
| ZM-02 | DPA-Fallsteuerung | mögliche eigenständige App für dokumentierte Fall- und Maßnahmensteuerung; keine Vermischung mit Datenschutz-Center oder Berechtigungsmatrix | Fachowner, Rechtsgrundlage, Datenklassen, Audit- und Retentionvertrag |
| ZM-03 | Personalbedarfsprognose | eigenständige Auswertung auf freigegebenen Aggregaten, keine zweite Personal- oder Planungswahrheit | Kennzahlen, Quelle, Zeitbezug, Mindestmengen und Fehlinterpretationsschutz |
| ZM-04 | Kapazitäts-/Fallbackplanung | Produktgrenze noch nicht entscheidbar | zuerst klären, ob lokale Erweiterung, gebündelte Bibliothek oder eigenständige Laufzeit-App gemäß ADR 0001 |
| ZM-05 | Aggregierte Berichte | nur datensparsame, zweckgebundene und berechtigte Projektionen aus kanonischen Quellen | Berichtsempfänger, Granularität, Export, Aufbewahrung und Reidentifikationsrisiko |
| ZM-06 | suiteweite Lokalisierung | systemweiter Rolloutentscheid im Root; technische IDs, Status, API-Schlüssel und ISO-Werte bleiben sprachneutral; App-Umsetzung erfolgt später app-lokal | Pilot-App, unterstützte Locales, persönliche oder organisationsweite Dokumentsprache, Fallback und Rohtext-Gate |
| ZM-07 | Recruitment–BQ-Integration | optionaler versionierter Capability-/Eventvertrag; beide Apps bleiben ohne Provider fachlich nutzbar | kleinster Datenumfang, Zustände, Autorisierung, Wiederholung und Rückbau |

### Fachlicher Zielrahmen der noch nicht freigegebenen Module

Die Vormerkungen beruhen auf folgenden Annahmen, die vor einer Umsetzung
fachlich bestätigt oder ersetzt werden müssen:

- Für DPA-Fälle, Ausfallgeld und Kapazitätslisten ist im Workspace noch keine
  belastbare digitale führende Quelle nachgewiesen. Tabellen- oder
  CSV-Bestände werden deshalb zunächst nur inventarisiert und nicht als
  stillschweigend kanonisch importiert.
- AdPlaner bleibt Owner der Wunschdienstplanung; eine spätere
  Schichtvermittlung verwaltet nur die konkrete offene Schicht und ihren
  Vermittlungsverlauf.
- AD Recruitment bleibt Owner von Bewerbung, Eignungsentscheidung,
  BQ-Zuordnung und Einstellungsfreigabe. Der BQ-Planer bleibt Owner der
  Kursdurchführung; ZM-07 darf nur notwendige stabile Referenzen und
  terminliche Zustände verbinden.
- Eine DPA-Fallsteuerung dient der mittelfristigen tragfähigen
  Wiederanbindung an feste Teams. Sie ist weder ein Akut-Springerpool noch
  eine BEM- oder Gesundheitsakte.

#### ZM-01 – Schichtvermittlung

Der spätere Fachprozess beginnt mit einer eindeutig referenzierten offenen
Schicht und einem begrenzten Suchauftrag. Er kann Suchstufen, zulässige
Kontakte, Kontaktzeitpunkt, Ergebnis, Wiedervorlage, begründeten
Stufenübergang, genehmigten Sonderzuschlag und abschließenden
Besetzungsstatus nachvollziehbar halten. Er wählt keine Personen automatisch
aus, wertet Ablehnungen nicht als Leistungsmerkmal und verändert keine
Wunschdienst-, Urlaubs- oder Arbeitszeitdaten. AdPlaner erhält höchstens den
fachlich bestätigten Besetzungsstand über einen kleinen optionalen Vertrag.

Vor einer Freigabe sind Owner, Rollen, erlaubte Zustände und Übergänge,
Kontaktkanäle, Eskalationsgrenzen, Audit, Retention, Nebenläufigkeit und der
Standalone-Fall ohne AdPlaner festzulegen. Tests müssen mindestens erlaubte
und verbotene Übergänge, Wiederholung, parallele Bearbeitung, fehlenden
Provider und ausbleibende Fremdmutation abdecken.

#### ZM-02 – DPA-Fallsteuerung

Ein späterer Fall kann stabile Beschäftigtenreferenz, fachliche
Zuständigkeit, vereinbarten Stundenrahmen, sachliche Einsatzbedingungen,
Teamoptionen, Kennenlernen, Einarbeitung, Wiedervorlagen, Ergebnis und
Abschluss enthalten. Diagnosen, BEM-Inhalte, Freitextgesundheitsdaten,
Rankings und automatische Teamzuordnungen bleiben ausgeschlossen. Eine
Akutschicht darf die DPA nur über einen getrennten, eng begrenzten
Integrationsfall anfragen; sie ändert den DPA-Fall nicht still.

Vor einer Freigabe werden erlaubte Ausgangs- und Zielzustände,
Verantwortungswechsel, fachliche Vorbedingungen, Stundenbedeutung,
Aufbewahrung, Drittpersonenbezug, Auskunft, Audit und Sperren spezifiziert.
Statusübergänge benötigen einen zuständigen Anwendungsservice und positive,
negative, Fehler-, Wiederholungs- und Nebenläufigkeitstests.

#### ZM-03 – Personalbedarfsprognose

Eine Prognose darf nur freigegebene, zweckgebundene Aggregate aus
kanonischen Quellen verwenden. Denkbar sind rollierende Bedarfskorridore,
8–12-Wochen-Checkpoints, Szenarien sowie Forecast-vs.-Ist auf ausreichend
großen Gruppen. Personenprognosen, Krankheitswahrscheinlichkeiten,
automatische Einstellungsentscheidungen und aus Einzelpersonen rückauflösbare
Strukturhinweise sind ausgeschlossen.

Vor einer Freigabe werden Kennzahl, Datenowner, Zeitbezug, Aktualität,
Mindestmenge, Korrekturweg, Unsicherheitsdarstellung und Fehlinterpretations-
schutz entschieden. Jede Ableitung bleibt reproduzierbar und gegen ihre
Quellen prüfbar.

#### ZM-04 und ZM-05 – Kapazität, Übergangsbestände und Berichte

Ausfallgeld, freiwillige Mehrkapazität, DPA-Stunden und AZK-Ausgleich werden
nicht in ein gemeinsames Stundenkonto verschmolzen. Erst ein bestätigter
Fachprozess entscheidet, ob Kapazitäts-/Fallbackplanung app-lokal,
gemeinsam gebündelt oder als eigenständige Laufzeit-App geführt wird.
Excel-/CSV-Dateien sind höchstens ein kontrollierter Übergang: Quelle,
Spalten, stabile IDs, Dubletten, fehlerhafte Zeilen, Vorschau, Bestätigung,
Idempotenz, Importprotokoll und Rückbau müssen vor dem ersten Import
feststehen; Originaldateien werden nicht zur dauerhaften Parallelwahrheit.

Aggregierte Berichte lesen ausschließlich freigegebene öffentliche
Projektionen. Empfänger, Zweck, Granularität, Mindestmengen, Exportrechte,
Aufbewahrung und Reidentifikationsrisiko werden pro Bericht entschieden.
Operative Fremdtabellenabfragen und Personen-Dashboards sind ausgeschlossen.

### Freigabereihenfolge für neue systemweite Module

| Gate | Vor jeder Implementierung nachzuweisen |
| --- | --- |
| ZM-G0 Fachentscheidung | Begriffe, Fachowner, kanonische Quellen, stabile IDs, Produktgrenze, Standalone-Verhalten und ausdrücklich ausgeschlossene Zwecke |
| ZM-G1 Schutzvertrag | Rollen, serverseitige Berechtigungen, Datenschutzklassen, Drittpersonenbezug, Auskunft, Retention, Audit, Sperren und Rückbau |
| ZM-G2 Zustands-/Schnittstellenvertrag | Zustände und Übergänge, kleinste optionale Provider-/Consumer-API, Versionierung, Fehlerzustände, fehlender Provider und additive Evolution |
| ZM-G3 Datenübergang | Bestandsinventar, Importvorschau, Validierung, Idempotenz, Migration, Roll-forward/Rollback und synthetische Upgradefälle |
| ZM-G4 Repositoryfreigabe | App-Klassifikation nach ADR 0001, expliziter Auftrag, erst dann gegebenenfalls `create-nextcloud-app`; keine vorsorgliche App oder LocalBase-Abhängigkeit |
| ZM-G5 Pilot und Abnahme | genau ein kleiner testgetriebener Pilot, Provider-/Consumer-Contracts, Installation/Upgrade/Deinstallation, Plattformmatrix, Datenschutz-, Sicherheits- und fachliche Abnahme |

Der BQ-Planer selbst ist ein vorhandenes Produkt. Seine Produktpakete,
Privacy-Aufgaben und Bundle-Reife stehen ausschließlich in
`adbqplanung/ROADMAP.md`; im Root verbleiben nur die Cross-App- und
Delivery-Grenzen.

## Verbindliche Regeln

1. **Vorhandenes zuerst.** Vor einer Änderung werden bestehender Owner,
   kanonische Quelle, Nextcloud-Standard und vorhandene Tests identifiziert.
2. **Nextcloud-Standard vor Eigenbau.** OCP/Public APIs, OCP Event Dispatcher,
   OCS, WebDAV/CalDAV, Files/Sharing, User/Group, AppConfig/UserConfig,
   Notifications, Activity und Background Jobs werden entsprechend ihrem
   vorgesehenen Zweck bevorzugt. Eine Abweichung braucht eine belegte Lücke
   und eine freigegebene Architekturentscheidung.
3. **Inter-App-Vertrag statt Implementierungszugriff.** Fachapps lesen oder
   verändern keine fremden Tabellen, Repositories, privaten
   Konfigurationsschlüssel, Dateipfade, Controller oder Assets. Eine fremde
   PHP-Klasse ist nur als ausdrücklich öffentliche, versionierte Runtime-API
   zulässig.
4. **Kleine öffentliche Verträge.** Jeder eigene Vertrag benennt Owner,
   Consumer, Zweck, Schema/DTO, Authentifizierung, Autorisierung,
   Fehlerzustände, Version, Kompatibilität und Deinstallationsverhalten.
5. **Additive Evolution.** Erst erweitern, Consumer migrieren und alte/neue
   Kombinationen verifizieren; erst danach eine alte Variante entfernen.
6. **Ausfall ist ein Vertragszustand.** Optionale Provider dürfen fehlen,
   deaktiviert, inkompatibel oder fehlerhaft sein. Der Consumer bleibt soweit
   fachlich möglich nutzbar und behauptet weder Vollständigkeit noch
   konfliktfreie Daten.
7. **Vertrag testen.** Relevante Schnittstellen besitzen Provider- und
   Consumer-Contract-Tests für Erfolg, Validierung, Autorisierung,
   Fehler/Timeout, fehlenden Provider und Versionsverhalten. Tests frieren
   keine private Implementierung ein.
8. **Plattformneutral bleiben.** IAM kommt aus Nextcloud; QueryBuilder und
   Migrationen bleiben DB-portabel; Dateien liegen hinter Nextcloud Storage;
   wiederkehrende Arbeit läuft als Nextcloud-Job; lokaler Prozess- oder
   Containerzustand ist keine persistente Wahrheit; Secrets liegen in
   Nextcloud-Konfiguration beziehungsweise der Zielumgebung.
9. **Kompatibilität beweisen, nicht vermuten.** `info.xml` wird nur aus
   reproduzierbarer Evidenz geändert. Ein openDesk-Pilot prüft die konkrete
   Zielumgebung, ohne sie als dauerhafte Architekturannahme festzuschreiben.
10. **Improve what you touch.** Bei App-Arbeit werden nur die für diese App
    und die berührte Architekturgrenze relevanten Punkte dieses Plans
    geprüft. Ein isolierter UI-Text erzeugt keine künstliche Plattformarbeit.

## Abgeschlossene Planstände

Abgelöste Pläne werden nicht als zweite, zwangsläufig veraltende
Dokumentwahrheit im Arbeitsbaum gehalten. Dauerhafte Entscheidungen bleiben
als ADR beziehungsweise geltender Fachvertrag erhalten; umgesetzte Ergebnisse
werden durch Code und Tests belegt. Der frühere Wortlaut bleibt bei Bedarf in
der Git-Historie nachvollziehbar.
