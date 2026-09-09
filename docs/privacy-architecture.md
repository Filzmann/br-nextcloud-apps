# App-übergreifende Datenschutzarchitektur

Stand: 9. September 2026

Dieses Dokument ist die normative Root-Quelle für app-übergreifende
Datenschutzauskunft, Datenlebenszyklen, Aufbewahrung, Löschung und
Anonymisierung. Es legt Verträge, Pilotstand und Ausbauplanung fest, trifft
aber keine pauschale Aussage, der Workspace oder eine App sei DSGVO-konform.

Die Inventur beschreibt den aktuellen Arbeitsbaum einschließlich noch nicht
committeter Änderungen. App-spezifische Entscheidungen und Implementierungen
bleiben Aufgaben der getrennten App-Repositories und brauchen jeweils einen
eigenen Auftrag.

## Verifizierter Ausgangsstand vor dem Pilot

- Vor dem Pilot implementierte keine App einen `PersonalDataProvider`,
  `RetentionProvider` oder `SubjectLifecycleProvider`.
- Vor dem Pilot gab es keine aggregierte Self-Service- oder Admin-Auskunft.
- Es gibt keine belastbare Quelle für Beschäftigungsende oder Austritt. Eine
  Kontodeaktivierung oder Kontolöschung in Nextcloud ist nicht mit einem
  Beschäftigungsende gleichzusetzen.
- LocalBase besitzt bereits kleine synchrone, optionale Eventverträge für
  Abwesenheiten, Konflikte und Integrationsfähigkeiten. Dieses Muster belegt,
  dass Fachapps Daten selbst liefern können, ohne fremde Tabellen zu öffnen;
  der Datenschutzvertrag benötigt zusätzlich eine explizite Registry und
  Fehlerstatus.
- Die Berechtigungsmatrix entfernt nach jedem Scan ältere Snapshots anhand
  einer konfigurierbaren Anzahl von 1 bis 500. Das ist eine technische
  mengenbasierte Retention, keine allgemeine personenbezogene Löschfrist.
- AD Recruitment speichert an Bewerbungen einen `retention_state`, besitzt
  aber noch keine daraus abgeleitete Policy, Fälligkeitsermittlung oder
  Lösch-/Anonymisierungsautomatik.
- Vorhandene fachliche Löschwege, etwa für einzelne Stunden, Buchungen,
  Urlaube oder Kalendereinträge, sind keine Datenschutz-Retention-Infrastruktur.
- Nextclouds User-Migration exportiert app-eigene Benutzerdaten über native
  Migratoren. Sie wird vor einer Umsetzung auf Wiederverwendung geprüft, ist
  aber wegen ihres Export-/Importzwecks kein Ersatz für eine verständliche
  Auskunft, Drittpersonenschutz, Retention oder Admin-Auskunft.

## Architekturentscheidung und Verantwortungsgrenze

Jede Fachapp bleibt alleinige Eigentümerin ihrer Fachdaten und ihrer
fachlichen Wahrheit. Die zentrale Datenschutzkomponente besitzt deshalb
keinen direkten SQL-Zugriff auf Tabellen anderer Apps, kennt keine fremden
Tabellennamen, reflektiert keine fremden Entitäten und durchsucht keine
fremden Volltexte oder Dateien.

Die Fachapp entscheidet und testet selbst:

- welche Daten personenbezogen sind und wie eine Person referenziert wird;
- welche zulässige Sicht eine Auskunft auf eigene und fremde Inhalte hat;
- welche Zwecke, Herkunfts- und Empfängerkategorien ausgegeben werden;
- welches Datum oder Ereignis eine Frist auslöst;
- ob eine Löschsperre oder manuelle Prüfung gilt;
- wie sie eigene Datensätze, Dateien, Exporte, Shares, Indizes und Caches
  löscht, anonymisiert oder von einer Person entkoppelt;
- welche fachliche Information nach einer zulässigen Anonymisierung erhalten
  bleiben muss.

Die zentrale Komponente kennt registrierte Provider, ruft sie einzeln auf,
aggregiert ihre Antworten, grenzt Fehler je Provider ein und weist
Vollständigkeit sichtbar aus. Sie darf Läufe planen und koordinieren, aber
keine Fachdaten fremder Apps selbst lesen oder verändern.

Nach
[`ADR 0001`](architecture-decisions/0001-shared-code-runtime-and-app-store.md)
(`docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`) ist
die zentrale Datenschutzkomponente wegen öffentlicher Services, eigener
Nextcloud-Oberfläche und app-übergreifender Koordination eine eigenständige
Nextcloud-Laufzeit-App der Kategorie B, keine gebundelte Hilfsbibliothek.

[`ADR 0002`](architecture-decisions/0002-standalone-privacy-platform.md)
(`docs/architecture-decisions/0002-standalone-privacy-platform.md`) legt als
Ziel die neutrale Standalone-App `filzmann_data_protection` fest. Sie besitzt
künftig DTOs,
Provider-Verträge, Registry, Aggregation, Coverage, Oberflächen, Audit und
Jobs geschlossen. LocalBase bleibt der verifizierte Pilot und während der
schrittweisen Migration rückbaufähig, ist aber nicht die dauerhafte
Eigentümerin der öffentlichen Privacy-Runtime. OrgSuite darf bei aktiver
Suite einen Navigation- oder Adminadapter anbieten, ist jedoch weder
Fachdatenbesitzerin noch notwendige Runtime der Auskunft.

Fachapps bleiben ohne aktive Privacy-App fachlich nutzbar und registrieren
ihre Provider lazy erst bei einem kompatiblen Registry-Aufruf. Ein
Versionshandshake prüft Vertragsversion, Subject-Typen, Fähigkeiten und
Paginggrenzen vor dem Datenabruf. Es wird keine automatische
App-zu-App-Installation oder unkontrollierte Versionsauflösung vorausgesetzt.
Bei fehlendem oder inkompatiblem Provider gilt sichtbar `missing`; es gibt
kein Daten-Fallback über SQL, Reflection, Volltextsuche, fremde Speicherpfade
oder `IUserMigrator`-Archive. Der öffentliche Integrationsvertrag und die
Empfehlungen für andere App-Entwickler stehen in
[`docs/privacy-provider-guide.md`](privacy-provider-guide.md).

## Processing-Metadata-Vertrag

Für alle eigenen Apps gilt **Central governance, decentralized ownership**:
Root definiert mit
[`docs/contracts/privacy-processing-metadata.schema.json`](contracts/privacy-processing-metadata.schema.json)
das gemeinsame, versionierte JSON-Schema; jede datenbesitzende App hält genau
einen daraus abgeleiteten fachlichen Katalog unter dem kanonischen Pfad
`resources/privacy-processing.json` für ihre eigenen Verarbeitungen. Apps ohne
eigene personenbezogene Verarbeitung dokumentieren die begründete
Nichtanwendbarkeit in ihrer Architektur und erzeugen keinen leeren
Scheinkatalog.
Root, LocalBase und das Datenschutz-Center führen keine Registry mit Kopien
dieser App-Kataloge. Git versioniert Änderungen an Zweck, Datenkategorien,
Empfängern, Retention und Schutzanforderungen im zuständigen App-Repository.
Der maschinenlesbare Contract-Owner verwendet dieselbe kanonische Identität
wie die bestehende Datenschutz-App: App-ID `filzmann_data_protection`,
Produktname `Data Protection Center` beziehungsweise `Datenschutz-Center` und
PHP-Namespace `OCA\FilzmannDataProtection`. Eine zusätzliche generische
„Privacy“-App oder zweite App-ID wird nicht eingeführt.

Das Schema trennt strikt:

- Processing-Metadaten beschreiben mit stabiler `processing_id` Zweck,
  Datenkategorien, Personengruppen, fachliche Verantwortung, Zugriff,
  Empfänger und erlaubte Weitergaben, Herkunft, Rechtsgrundlage, Retention,
  Logging, Backup, Betroffenenrechte, Schutzanforderungen und Systeme;
- personenbezogene Laufzeitdaten bleiben außerhalb des Katalogs und werden
  nur für eine konkrete, autorisierte Anfrage durch den zuständigen Provider
  projiziert;
- unbekannte fachliche Werte werden mit `PRIVACY-DECISION-REQUIRED` samt
  Auswirkung, datenschutzärmerer Alternative, möglicher Zuständigkeit und
  Blockierungsstatus sichtbar. Unbekannte Retention ist keine unbegrenzte
  Aufbewahrung.

Ein App-Katalog ist die kanonische Policyquelle für Art.-15-Metadaten,
Retention-Preview, Architekturreview und zugehörige Contract-Tests. Provider
dürfen daraus jeweils nur den für ihren Zweck erforderlichen Ausschnitt
projizieren. Laufzeitdaten, interne Tabellen-, Datei- oder AppConfig-Strukturen
und vollständige Datensätze gehören niemals in diesen Katalog.

Der heutige öffentliche V1-`PersonalDataProvider` liefert Processing-Angaben
noch datensatzbezogen in `PersonalDataEntry`; `RetentionProvider` führt einen
separaten Policykatalog. Diese implementierten Verträge bleiben gültig. Eine
gemeinsame Katalogprojektion ist als kleiner zusätzlicher
`ProcessingMetadataProvider` im kanonischen öffentlichen Vertrag von
`filzmann_data_protection` versioniert, statt die bestehenden Provider still
zu verbreitern. Der additive Pilot besitzt eine eigene typisierte Registry,
damit personenbezogene Laufzeitdaten und Processing-Metadaten nicht in
demselben Aufruf vermischt werden. Sein Minimalvertrag lautet:

```php
interface ProcessingMetadataProvider {
    public function descriptor(): ProcessingMetadataProviderDescriptor;
    public function catalog(): ProcessingMetadataCatalog;
}
```

Der Katalogaufruf liefert ausschließlich schema-konforme Metadaten. Die
Registrierung verwendet denselben lazy Nextcloud-Event- und
Versionshandshake-Mechanismus wie die vorhandenen Provider. Der erste Pilot
isoliert inkompatible und ungültige Provider bereits appweise; die Zuordnung
zu `missing`, `failed` oder `partial` entsteht erst mit dem gesondert
freizugebenden Consumer- und Coverage-Rollout. Es gibt weder
Quellcodeheuristiken noch direkte Fremddatenzugriffe. Diese Einordnung ist
Kategorie B für Registry und Aggregation, bewusst app-lokal für Werte und
Root-Governance für das Schema; sie ist keine gebündelte
Kategorie-A-Laufzeitbibliothek.

Der Parent entdeckt Kataloge ausschließlich über das kanonische
Repositoryinventar und den festen app-lokalen Pfad; er führt keine zweite
manuelle App- oder Processing-Liste. Während des appweisen Rollouts ist ein
fehlender Katalog ein offen dokumentierter Migrationsstand. Sobald ein Katalog
vorhanden ist, muss ihn der app-lokale Test gegen das Root-Schema validieren;
die spätere zentrale Providerabdeckung entscheidet getrennt, ob sein Fehlen
zur Laufzeit als `missing` ausgewiesen wird.

## Privacy-by-Design beim Ändern einer App

Der Harness prüft inkrementell den berührten fachlichen Scope, nicht bei jeder
Änderung den gesamten Workspace. Bei Datenmodell, Formular, API, Import,
Export, Report, Log, Backup, Berechtigung, Synchronisation, Index, Integration
oder personenbezogenem Workflow wird vor der Umsetzung bestimmt:

1. welche bestehende `processing_id` betroffen ist oder ob eine neue
   Verarbeitung entsteht;
2. ob der app-lokale Katalog, `PersonalDataProvider`, `RetentionProvider`,
   PermissionProvider oder ein API-Contract im selben App-Auftrag angepasst
   werden muss;
3. ob Zweck, Erforderlichkeit, minimale Attribute, Zugriff, Weitergabe,
   Rechtsgrundlage, Retention-Trigger/-Maßnahme, Logging, Backup und
   Betroffenenrechte bereits fachlich entschieden sind;
4. welche bestehende Security-, Provider-, Contract- oder Integrationprüfung
   die technische Invariante bereits belegt.

Vorhandene Entscheidungen werden umgesetzt; offene technische Details darf
Codex lösen. Eine fehlende fachliche oder rechtliche Entscheidung wird nicht
geraten. Kann der übrige Scope mit Nicht-Speichern, temporärer Verarbeitung,
weniger Attributen, Aggregation, Pseudonymisierung, Anonymisierung,
Zugriffsbeschränkung oder verhinderter Weitergabe sicher fortgesetzt werden,
wird die Lücke nicht blockierend dokumentiert. Andernfalls bleibt nur der
betroffene Teil blockiert und erhält eine gezielte Rückfrage. APIs geben nur
die für den Consumer erforderlichen Attribute aus; Privacy Provider sind
keine privilegierten Hintertüren.

Die Berechtigungsmatrix ist nach
[`ADR 0003`](architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md)
(`docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md`)
perspektivisch im selben Portfolio IKT/Datenschutz angesiedelt, bleibt aber
eine eigenständige Kategorie-B-App. Sie wird nicht in die Privacy-App
verschmolzen. Matrixdaten, Scans, Baselines, Exporte, Audit und Rechte bleiben
bei der App `filzmann_permission_matrix`. Nur ihre Art.-15-Projektion wird später über den
öffentlichen Providervertrag geliefert.

## Personenreferenzen

Ein universeller Personen-Identifier wird nicht vorweggenommen. Der Vertrag
verwendet eine typisierte `DataSubjectRef` aus `subjectType` und `subjectId`.
Die erste Ausbaustufe unterstützt `nextcloud-user` mit einer Nextcloud-UID.
Die Self-Service-UID stammt ausschließlich aus der authentifizierten
Nextcloud-Session.

AD Recruitment besitzt davon getrennte Bewerber*innen mit interner numerischer
Personen-ID. Eine Bewerber-ID wird nicht in eine Nextcloud-UID umgedeutet.
Eine spätere Auskunft für externe Bewerber*innen benötigt einen eigenen
Identitätsprüfungs- und Zustellprozess; bis dahin darf nur eine eng berechtigte
Adminauskunft diesen Personentyp verwenden.

Weitere Personentypen werden erst nach einem realen Modell und mindestens
einem Provider ergänzt. Anbieterinterne Primärschlüssel bleiben außerhalb
ihres Providers bedeutungslos.

## Provider-Registry

Aktivierte Fachapps registrieren konkrete Provider über einen kleinen,
typisierten Vertrag der Standalone-App. Während der Migration ist der
gleichartige LocalBase-Vertrag der charakterisierte Pilot, aber keine zweite
dauerhafte Quelle. Eine Registrierung enthält eine stabile App-ID,
Anzeigename, Vertragsversion, unterstützte Subject-Typen, angebotene
Providerfähigkeiten und Paginggrenzen. Doppelte App-IDs, inkompatible
Versionen oder widersprüchliche Fähigkeiten werden abgelehnt beziehungsweise
als kontrollierter Coverage-Fehler ausgewiesen.

Die Registry erzeugt pro Anfrage eine feste Providerliste. Der Aggregator
ruft jeden Provider getrennt auf und fängt dessen Fehler ab. Statuswerte sind
mindestens:

- `complete`: Provider hat seine zulässige Sicht vollständig geliefert;
- `partial`: bekannte Teilmenge, Einschränkung ist begründet;
- `not_applicable`: Provider unterstützt den Subject-Typ, hat aber keine Daten;
- `failed`: Provideraufruf ist fehlgeschlagen;
- `missing`: erwartete Registrierung konnte nicht aufgelöst werden.

Die Laufzeitvollständigkeit bezieht sich zunächst nur auf die Registry. Sie
darf nicht behaupten, alle Apps des Systems abzudecken, solange das spätere
Coverage-Gate noch nicht belegt, welche Apps mit personenbezogenen Daten einen
Provider benötigen. Leere Scheinprovider sind dafür unzulässig.

## `PersonalDataProvider`

Der konzeptionelle Minimalvertrag lautet:

```php
interface PersonalDataProvider {
    public function descriptor(): ProviderDescriptor;
    public function collect(PersonalDataRequest $request): PersonalDataPage;
}
```

Dieser Vertrag beschreibt weiterhin das spätere Zielbild einschließlich
einer ausdrücklich freizugebenden Ausführung. Der aktuell implementierte
öffentliche V1-Vertrag endet bewusst nach `descriptor()`, `policies()` und
`preview()`: Er unterstützt ausschließlich `REVIEW` und besitzt keine
`execute()`-Methode. Eine Ausführung wird nicht nachträglich still in V1
ergänzt, sondern benötigt eine neue freigegebene Vertragsversion samt
Policy-, Lifecycle-, Nebenläufigkeits- und Rückbauentscheidung.

`PersonalDataRequest` enthält ausschließlich die typisierte betroffene Person,
Sprache, Ausgabezweck, technische Begrenzungen und einen opaken Cursor.
`PersonalDataPage` liefert eine eindeutige Endmarke oder den nächsten Cursor.
Cursor-Paging darf keine Datensätze still auslassen oder doppeln; Änderungen
während einer langen Auskunft werden durch stabilen Snapshot oder sichtbaren
`partial`-Status behandelt. Ein Providerbericht enthält strukturierte
Kategorien und menschenlesbare Einträge mit mindestens:

- Datenkategorie, appweise Abschnittsüberschrift und verständliche
  Zusammenfassung des konkreten zulässigen Datensatzes;
- datensatzbezogener Verarbeitungszweck;
- Herkunft, soweit bekannt;
- Empfänger oder Empfängerkategorie, soweit relevant;
- datensatzbezogene Aufbewahrungsregel oder nachvollziehbare Kriterien; ein
  reiner `REVIEW`-Stichtag darf nicht als Löschdatum ausgegeben werden;
- Hinweis auf geschützte Inhalte anderer Personen;
- Providerstatus, Einschränkungen und technischen Vollständigkeitshinweis.

Ein Bericht ist keine rohe Folge von Datenbankzeilen. Passwörter,
Authentifizierungstoken, OAuth-Secrets, verschlüsselte Zugangsdaten,
interne Hashes und andere Sicherheitsgeheimnisse werden nicht ausgegeben.
Der Provider kann stattdessen Existenz, Zweck und Kategorie einer geheimen
Konfiguration melden.

Der Provider schützt die Rechte anderer Personen selbst. Kommentare,
Freigaben, Diensttausch, Genehmigungen, Interviews oder Nachrichten mit
mehreren Beteiligten werden nicht ungeprüft vollständig zurückgegeben. Die
zentrale Aggregation besitzt nicht genug Fachwissen, um diese Sicht generisch
zu erraten.

## `RetentionProvider` und Policy-Modell

Der konzeptionelle Minimalvertrag lautet:

```php
interface RetentionProvider {
    public function appId(): string;
    public function policies(): array;
    public function preview(RetentionPreviewRequest $request): RetentionPreviewPage;
    public function execute(RetentionExecutionRequest $request): RetentionRunResult;
}
```

Die Fachapp ermittelt Kandidaten, interpretiert ihre Datumsfelder, prüft
Sperren und führt die konkrete Maßnahme aus. Die zentrale Runtime übergibt nur
Policy-ID, Bewertungszeitpunkt, Batchgrenze und gegebenenfalls ein geprüftes
Lifecycle-Ereignis. Sie übergibt niemals SQL oder fremde Primärschlüssel mit
der Anweisung, Tabellenzeilen zentral zu löschen.

Jede Policy besitzt eine stabile ID und mindestens Datenklasse, Zweck,
Rechtsgrundlagenhinweis, Trigger, konfigurierbare Dauer oder Termin,
zulässige Konfigurationsgrenzen, Maßnahme, Sperr-/Reviewregeln und Version.
Konkrete Rechtsgrundlagen und Fristen werden fachlich sowie
datenschutzrechtlich freigegeben und nicht aus Beispielen abgeleitet.

Unterstützte Trigger:

| Trigger | Bedeutung |
| --- | --- |
| `CREATED_AT` | Frist ab Erzeugung des Datensatzes |
| `COMPLETED_AT` | Frist ab fachlich definiertem Abschluss |
| `SUBJECT_EVENT` | Frist ab geprüftem Ereignis einer betroffenen Person |
| `FIXED_DATE` | einmalige Aktion an einem ausdrücklich freigegebenen Termin |
| `NO_AUTO_ACTION` | Keine automatische Aktion; ausschließlich manuelle Prüfung |

Unterstützte Maßnahmen:

| Maßnahme | Vertrag |
| --- | --- |
| `DELETE` | Personenbezogene Daten oder den vollständigen Datensatz einschließlich abhängiger Artefakte entfernen |
| `ANONYMIZE` | Personenbezug irreversibel entfernen und den begründet fortbestehenden Fachdatenkern erhalten |
| `REMOVE_PERSON_REFERENCE` | Verknüpfung zur Person entfernen; ist eine Rückauflösung anderweitig möglich, ist dies keine Anonymisierung |
| `REVIEW` | Kandidat zur manuellen Entscheidung markieren, ohne automatisch destruktiv einzugreifen |

Neutrale Platzhalter wie `Ehemalige Mitarbeiter*in` enthalten keine
versteckte Rückauflösung, erteilen keine Rechte oder Zuständigkeiten und
werden nicht als Nextcloud-Konto behandelt. Ein Anzeigeplatzhalter allein
anonymisiert keinen Datensatz, wenn UID, Auditspur oder verknüpfte Dateien die
Person weiterhin bestimmen lassen.

Eine Ausführung benötigt vorher einen Dry Run. Der Previewbericht enthält
Policyversion, Bewertungszeitpunkt, Kandidatenanzahl, Maßnahmen, Sperren,
Teilrisiken und einen kurzlebigen Integritätsbezug. Die Ausführung darf nur
den dazu passenden unveränderten Policy- und Previewstand verwenden. Ungültige
oder fehlende Konfiguration, unbekannte Ereignisse und widersprüchliche Daten
führen zu keiner destruktiven Aktion. Läufe sind gebatcht, wiederholbar,
nebenläufigkeitssicher und je Provider fehlerisoliert.

## `SubjectLifecycleProvider`

Der konzeptionelle Minimalvertrag lautet:

```php
interface SubjectLifecycleProvider {
    public function providerId(): string;
    public function events(SubjectLifecycleRequest $request): SubjectLifecycleReport;
}
```

Ein Lifecycle-Ereignis enthält Subject-Referenz, stabilen Ereignistyp,
Zeitpunkt, Quellenbezeichnung, Vertrauens-/Vollständigkeitsstatus und bei
Korrekturen eine stabile Ereignisidentität. Vorgesehene Ereignistypen dürfen
Beschäftigungsende, Nextcloud-Kontodeaktivierung und Kontolöschung
unterscheiden; keiner wird aus einem anderen abgeleitet.

Derzeit existiert kein `SubjectLifecycleProvider`. Insbesondere werden keine
imaginären Austrittsdaten in LocalBase, OrgSuite oder einer Fachapp erzeugt.
Unbekannte, fehlende oder widersprüchliche Ereignisse lösen höchstens
`REVIEW`, niemals automatische Löschung oder Anonymisierung aus.

## Self-Service-Auskunft

Die zentrale persönliche Ansicht wird als Nextcloud-native persönliche
Einstellung beziehungsweise eigener geschützter LocalBase-Bereich geplant und
bleibt auch ohne aktive OrgSuite erreichbar. Der Server konstruiert die
`DataSubjectRef(nextcloud-user, <Session-UID>)` selbst. Eine vom Browser frei
übermittelte UID wird nicht als Zielperson akzeptiert.

Die Ansicht erklärt die Betroffenenrechte einmal im Kopf und zeigt danach je
App zunächst Herkunft, Empfängerkategorien und weitere Verarbeitungsangaben.
Darunter gliedert sie die Datensätze in Abschnitte je Datentyp. Die
Tabellenköpfe bestehen aus sämtlichen vom Provider freigegebenen Datenfeldern.
Ist ein Grund oder eine Aufbewahrungsaussage innerhalb des Datentyps
identisch, wird sie einmal vor die Tabelle gezogen; nur unterschiedliche
Werte bleiben zusätzliche Tabellenspalten. Sie bietet eine verständliche
HTML-Ansicht und ein unmittelbar clientseitig erzeugtes mehrseitiges PDF.
Menschenlesbare Datumsangaben verwenden in beiden Darstellungen die deutsche
Kurzform `TT.MM.JJ`; Uhrzeiten werden bei Bedarf getrennt als `HH:MM Uhr`
ergänzt. Der Gesamtstatus nennt erfolgreiche, teilweise, fehlende und fehlgeschlagene
Provider. Eine Teilantwort wird nie als vollständig dargestellt.

Berichte werden standardmäßig nur für die laufende Anfrage aggregiert und
nicht zentral als zweite personenbezogene Datenkopie persistiert. Exporte
werden gestreamt oder unmittelbar ausgeliefert; eine spätere Ablage benötigt
einen eigenen Speicher-, Rechte- und Löschvertrag.

## Admin-Auskunft

Die Admin-Auskunft verwendet denselben Aggregator, aber einen getrennten
serverseitigen `PrivacyAccessService`. App-Administration oder Sichtbarkeit
eines Adminmenüs erteilt keinen Auskunftszugriff. Vorgesehen ist eine
dedizierte, über native Nextcloud-Gruppen konfigurierte Datenschutzrolle;
ohne explizite Zuordnung wird verweigert. Ob Nextcloud-Admins zusätzlich
automatisch oder nur nach Zuordnung lesen dürfen, bleibt vor der Runtime als
ausdrückliche Produkt- und Sicherheitsentscheidung offen.

Berechtigte Personen wählen eine typisierte Zielperson, erhalten dieselben
Provider- und Vollständigkeitsstatus und können das aggregierte Ergebnis
strukturiert exportieren. Der Auditnachweis speichert nur anfragende UID,
Subject-Typ und erforderlichenfalls eine minimierte Subject-Referenz,
Zeitpunkt, Zweck, Providerstatus und Exportflag. Berichtsinhalte werden nicht
in Auditlogs kopiert. Der Auditbestand benötigt selbst eine freigegebene
Retention-Policy.

## Datenlebenszyklus über technische Grenzen

Provider berücksichtigen neben Datenbankzeilen auch AppConfig/UserConfig,
AppData, Nextcloud-Dateien, generierte Kalenderobjekte, Shares, Exporte,
Vorschaudaten, Suchindizes und Caches, soweit die App diese Artefakte besitzt.
Für Backups wird betrieblich festgelegt, wann Daten aus dem Sicherungszyklus
entfallen und wie eine Wiederherstellung abgelaufene Daten nicht dauerhaft
reaktiviert.

Das Ausscheiden einer Person wird unabhängig von technischer Kontoänderung
behandelt. Zugriffsrechte und offene Zuständigkeiten werden zuerst sicher
entzogen oder kontrolliert übertragen. Historische Fachdatensätze bleiben
nur mit der minimal freigegebenen Personeninformation oder einem neutralen
Platzhalter bestehen. Referenzintegrität darf nicht durch das bloße Fehlen
eines Nextcloud-Kontos zusammenbrechen.

## Architektur- und Migrationsstand

Dieser Abschnitt dokumentiert Architekturgrenzen und den erreichten
Migrationsstand. Verbleibende systemweite Aufgaben und ihre Priorität stehen
ausschließlich in `docs/zukunftsplan.md`; dieser Abschnitt ist keine zweite
Planungsquelle.

### Etappe 1 – Architekturvertrag

Mit diesem Dokument und dem Pilotstand vom 12. August 2026 umgesetzt:

- aktuelles Dateninventar und Identifier-Grenzen;
- Provider-, Registry-, Retention- und Lifecycle-Zielvertrag;
- Rechte- und Aggregationsgrenzen;
- Root-Migrationsmatrix und schrittweise Ausbauplanung;
- Root-Prüfvertrag für die normative Quelle.

Im Pilot umgesetzt sind die PHP-Verträge für Nextcloud-User-Subjects,
PersonalData- und Retention-Preview-Provider, feste Registry-Snapshots,
fehlerisolierte Aggregation, Self-Service-/Admin-Grundansichten und der
LocalBase-eigene Provider für das Nextcloud-Konto. Der frühere reale
`adrecruitment`-Pilot ist inzwischen auf den Standalone-Vertrag migriert.
Der flüchtige Bericht enthält pro Provider
Zwecke, Kategorien, Empfänger*innen, Herkunft, Aufbewahrung,
Drittlandübermittlung und automatisierte Entscheidungen sowie zentrale
Betroffenenrechte. Die Rechte erscheinen einmal im Berichtskopf; Datensätze
werden nach App und Datenart mit Zweck und Aufbewahrungsaussage dargestellt.
Nutzer*innen können exakt diesen Stand ohne Serverablage als mehrseitiges PDF
herunterladen. Der kanonische Self-Service ist für normale Konten
sowohl als `Datenschutz`-Eintrag im rechten Nextcloud-Benutzermenü als auch
über den persönlichen Einstellungsbereich `Datenschutz` erreichbar; beide
Einstiege führen auf denselben Bericht. Retention bleibt ausschließlich ein Dry Run mit
`REVIEW`; für AD Raumplaner ist die Review-Frist im eigenen Adminbereich
bearbeitbar. Admin-Karten sind zugänglich klapp- und per Tastatur oder
Drag-and-drop verschiebbar; ihre persönliche Anordnung ist keine fachliche
Konfiguration.
Nicht umgesetzt sind Ausführung, automatische Maßnahmen, Lifecycle-Provider,
Jobs oder ein Vollständigkeits-/Release-Gate. Alle eigenen AD-/BR-Apps
besitzen inzwischen eine erste Providerprojektion; bekannte Teilabdeckung und
Retention-Lücken bleiben in der Migrationsmatrix sichtbar.

### Etappe 2 – Standalone-Vertrag und App-Identität

Eigener Root-/Neue-App-Auftrag ohne gleichzeitige Consumer-Migration:

1. Produktname `Data Protection Center`/`Datenschutz-Center`, App-ID
   `filzmann_data_protection`, AGPL-Lizenz und Repository sind entschieden
   und angelegt; Governance bleibt offen;
2. den LocalBase-Pilot als rückwärtskompatiblen Ausgangsvertrag
   charakterisieren;
3. Version 1 von Descriptor, Subject, Status, Art.-15-Metadaten,
   providergebundenem Cursor-Paging und Versionshandshake festlegen; der
   technische Contract-Kern, das Test-Kit und die ersten realen Consumer
   `adroom`, `adplaner`, `adcalendar` und `adurlaub` sind umgesetzt und lokal
   verifiziert;
4. erwartete Providerabdeckung und das sichtbare `missing`-Verhalten
   modellieren; das Laufzeitmodell ist umgesetzt, die administrative Quelle
   eines konkreten Coverage-Profils bleibt offen;
5. Contract-Test-Kit und neutrale Referenzfixtures bereitstellen; das
   technische Test-Kit ist umgesetzt, vollständige Referenzfixtures folgen
   mit dem synthetischen Referenzprovider;
6. saubere Installation mit fehlender, deaktivierter, kompatibler und
   inkompatibler Privacy-App prüfen: kompatibel aktiv und deaktiviert sind im
   lokalen DDEV verifiziert. Der opt-in Check
   `scripts/check-privacy-app-compatibility` automatisiert am
   Matrix-Consumer zusätzlich den physisch fehlenden Zustand und eine von
   Nextcloud real als inkompatibel abgewiesene Installation mit vollständigem
   Rückbau. Die Versionsablehnung eines registrierten Providers bleibt
   ergänzend im Contract-Test belegt; weitere Consumer benötigen weiterhin
   ihren eigenen Start- und Vertragsnachweis.

Die App wird mit `create-nextcloud-app` erst nach der ausdrücklichen
Namens-/App-ID- und Repositoryfreigabe angelegt. Es gibt keine automatische
App-zu-App-Installation und kein Daten-Fallback.

### Etappe 3 – Standalone-Runtime und erster Provider

1. Registry, Aggregator, Self-Service und getrennte Adminberechtigung in der
   neutralen Standalone-App test-first implementieren. Registry, Aggregator
   sowie die ausschließlich an `OCP\IUserSession` gebundene Self-Service-API
   und die flüchtige zugängliche Report-UI sind umgesetzt; die
   Self-Service-Integration ist in lokaler Nextcloud-Laufzeit verifiziert.
   Die getrennte REVIEW-Berechtigung ist umgesetzt: konfigurierte
   Prüfgruppen besitzen das fachliche Leserecht; native Nextcloud-Admins
   erhalten es nicht automatisch und benötigen eine app-lokale, auditierbare
   Freigabe von höchstens 24 Stunden. Technisches Konfigurationsrecht bleibt
   davon getrennt.
2. Zunächst einen synthetischen Referenzprovider anbinden, damit keine
   Fachdatenmigration die Runtimegrenze verdeckt.
3. Genau einen vorhandenen realen Provider mit Aktivierungs- und
   Versionsprüfung aus LocalBase migrieren: `adroom` ist als erster Consumer
   auf den öffentlichen V1-Vertrag umgestellt. Der freie Buchungstitel wird
   wegen möglicher Drittpersonenangaben durch einen neutralen Platzhalter
   ersetzt; Raum, Zweck und Zeitraum erhalten den fachlichen Kontext.
4. Installation mit und ohne Privacy-App, Providerfehler, Cursor-Grenzen,
   Teilantwort, Audit und Rückbau zum charakterisierten Pilotstand prüfen.
5. Der erste Consumerlauf ist in den Repository-Tests und in lokaler
   Nextcloud-Laufzeit grün. Alle später hinzugekommenen Provider behalten
   app-lokale Vertragsnachweise; der Root behauptet daraus keine automatisch
   vollständige Datenabdeckung.

Der Processing-Metadata-Rollout ist anschließend appweise fortgeführt worden:
`adplaner` veröffentlicht als zweiter realer Katalog-Consumer die
Verarbeitungen `shift_planning_management` und
`temporary_admin_full_access`. Der Katalog wird aus der bereits vorhandenen
PersonalDataProvider- und Dateninventur abgeleitet, enthält keine
personenbezogenen Laufzeitdaten und weist ungeklärte Rechtsgrundlagen,
Retention-, Backup- und Betroffenenrechtsregeln ausdrücklich als
`PRIVACY-DECISION-REQUIRED` aus.

`adcalendar` veröffentlicht als dritter realer Katalog-Consumer die getrennten
Verarbeitungen für führende Kalendereinträge, persönliche Kalenderstandards,
verschlüsselte externe Verbindungen, abgeleitete interne und externe
Zielkalender sowie temporäre Adminfreigaben. Secrets werden weder entschlüsselt
noch als Laufzeitdaten in den Katalog übernommen; offene Anbieter-,
Drittland-, Retention-, Backup- und Restoreentscheidungen bleiben sichtbar.

`adurlaub` veröffentlicht als vierter realer Katalog-Consumer die getrennten
Verarbeitungen `vacation_management` und `temporary_admin_full_access`.
Freiwillige Urlaubsnotizen bleiben als mögliche Drittpersonenklasse sichtbar,
verlassen AD Urlaub aber nicht über Consumer-Verträge. Die vorhandene
administrative Retention-Vorschau bleibt ausdrücklich `REVIEW`-only und wird
nicht als freigegebene Lösch- oder Aufbewahrungsregel dargestellt.

Als zweiter ausdrücklich freigegebener Consumer ist
`filzmann_permission_matrix` auf denselben V1-Vertrag angebunden und lokal in
Nextcloud verifiziert. Die App projiziert ausschließlich eigene
Snapshot-Ersteller-, Exportmetadaten- und Auditbezüge. Snapshot- und
Exportinhalte, freie Dateinamen, freie Auditdetails sowie Angaben anderer
Personen bleiben ausgeschlossen. Bestehende Matrixberechtigungen und das
Tabellenschema wurden dadurch nicht verändert. Zusätzlich liefert die App als
erster Standalone-Retention-Consumer zwei reine `REVIEW`-Policies für
Exportmetadaten und Auditprotokolle. Beide Fristen sind app-lokal getrennt
konfigurierbar und standardmäßig 180 Tage; UIDs, Dateinamen, Inhalte und freie
Auditdetails werden nicht an die Vorschau übergeben. Der öffentliche
V1-Vertrag besitzt keinen Ausführungspfad.

Als dritter Consumer ist der `PersonalDataProvider` von `adurlaub` auf den
Standalone-V1-Vertrag migriert. Er liefert ausschließlich Urlaube der
typisierten Nextcloud-UID, weist eigene freiwillige Notizen als möglichen
Drittpersoneninhalt aus und lehnt andere Subject-Typen sowie unbekannte
Cursor kontrolliert ab. Der bestehende subjectbezogene Retention-Dry-Run
bleibt bis zu einer getrennten Entscheidung über die globale administrative
V1-Prüfung im LocalBase-Pilot.

Als vierter Consumer ist der `PersonalDataProvider` von `adplaner` auf den
Standalone-V1-Vertrag migriert. Er trennt eigene Schichtwünsche und
-zuweisungen von subjectbezogenen Bearbeitungsnachweisen. Fremde
Assistenz- und Bearbeiterkennungen sowie freie Tagesnotiztexte werden nicht
ausgegeben; deren fachlicher Kontext bleibt als geschützte Datenklasse
sichtbar.

Als fünfter Consumer ist der `PersonalDataProvider` von `adcalendar` auf den
Standalone-V1-Vertrag migriert. Eigene Dienste und Termine bleiben nach
fachlicher Organisationszeitzone lesbar. Bei gemeinsamen Terminen wird nur
das Vorhandensein weiterer Beteiligter ausgewiesen; deren UIDs, Titel und
fremde Kalendereinträge bleiben geschützt.

Als sechster Consumer ist der interne `PersonalDataProvider` von
`adrecruitment` auf den Standalone-V1-Vertrag migriert. Er weist ausschließlich
die in fachlichen Bearbeitungs- und Auditfeldern gespeicherten Bezüge zur
angefragten Nextcloud-UID aus. Bewerberstammdaten, Nachrichten, Interviews,
Dateinamen und Inhalte der privaten AppData-Anhänge bleiben ausgeschlossen.
Eine Bewerber-Selbstauskunft setzt einen getrennten, sicher authentifizierten
Subject-Vertrag voraus; eine bloße Zuordnung über die E-Mail-Adresse findet
nicht statt.

Als siebter Consumer stellt `brstunden` eigene Monatswerte, Fortbildungszeiten
und freiwillige Notizen über den Standalone-V1-Vertrag bereit. Ist die
betroffene Person nur als Bearbeiter*in eines fremden Stundensatzes gespeichert,
liefert der Provider einen neutralen Bearbeitungsnachweis ohne fremde UID,
Stundenwerte oder Notiz. Die App erzeugt Abrechnungs-PDFs nur auf Anforderung
im Response und speichert sie nicht selbst in Files oder Team Folders.

Als achter Consumer stellt `brtop` ausschließlich explizit per Nextcloud-UID
zuordenbare Mitgliedschafts-, Ladungs-, Sitzungsverantwortungs-, Dokumentart-
und Bearbeitungsmetadaten bereit. Datei- und Anhangpfade, Dateiinhalte sowie
TOP-, Protokoll- und Beschlussinhalte bleiben vorerst ausgeschlossen. Weil
unstrukturierte Inhalte dennoch Erwähnungen enthalten können, meldet der
Provider diese Grenze stets als Teilantwort; eine spätere sichere
Inhaltsanalyse bleibt ausdrücklich vorbehalten.

Als neunter Consumer stellt `adbqplanung` interne PFK-Profile,
Hauptdozentinnen- und Modulzuordnungen sowie eigene Bearbeitungsnachweise über
die geprüfte Nextcloud-UID bereit. Externe Dozentinnenprofile bleiben bis zu
einem sicher authentifizierten externen Subject-Vertrag ausgeschlossen; Name
und E-Mail werden weder als Ersatzidentität noch als Suchschlüssel verwendet.

Der vorhandene LocalBase-Pilot bleibt bis zur vollständigen Umstellung der
Oberflächen und Provider rückbaufähig, darf aber nicht parallel als zweiter
aktiver Aggregator oder zweite kanonische Vertragsquelle betrieben werden.

### Etappe 4 – Appweise Provider-Migration und laufende Vollständigkeit

Jede App erhält einen einzelnen Auftrag in ihrem Repository:

1. Dateninventar gegen den dann aktuellen Code bestätigen.
2. Subject-Typen und Drittpersonensicht entscheiden.
3. `PersonalDataProvider` gegen das öffentliche Contract-Test-Kit migrieren
   beziehungsweise implementieren und testen.
4. Policies, Trigger und Sperren fachlich freigeben.
5. `RetentionProvider`, Dry Run und Maßnahmen test-first implementieren.
6. Dateien und Nebenspeicher einbeziehen.
7. Self-Service-, Admin-, Provider-, Versions-, Paging- und Negativfälle
   prüfen; Betrieb ohne Privacy-App bleibt fachlich grün.
8. Matrixstatus aktualisieren; erst danach die nächste App beginnen.

Kein Big-Bang und keine leeren Provider.

Nach der Erstimplementierung ist ein Provider kein abgeschlossener
Einmalbaustein. Dateninventar und Providerprojektion werden bei jeder relevanten Weiterentwicklung der datenbesitzenden
App zusammen mit Subject-Typen, Drittpersonenschutz, AppConfig,
UserConfig, AppData, Dateien, Shares, Exporte, Caches und sonstige
Nebenspeicher gegen den tatsächlichen Code neu geprüft. Neue oder geänderte
relevante Daten werden bei derselben fachlichen Änderung in die zulässige
Providerprojektion und deren Provider-/Consumer-/Negativtests aufgenommen.
Bewusst noch nicht sicher lieferbare Bestände bleiben mit Grund sichtbar
`partial`; sie dürfen nicht durch einen zu engen Providerumfang als
`complete` erscheinen.

### Etappe 5 – Lifecycle-Ereignisse

Erst nach Benennung einer belastbaren Beschäftigungsdatenquelle:

- `SubjectLifecycleProvider` implementieren;
- Ereigniskorrekturen, fehlende und widersprüchliche Daten behandeln;
- Austrittsfristen zunächst nur im Dry Run prüfen;
- Rechteentzug, Zuständigkeitsübergabe und nachgelagerte Retention getrennt
  testen.

### Etappe 6 – LocalBase-Rückbau, Vollständigkeits- und Release-Gates

Self-Service, Adminoberfläche, Registry und öffentliche Privacy-Klassen werden
erst aus LocalBase entfernt, wenn alle vorgesehenen Consumer auf dem
Standalone-Vertrag stehen und Update, Deinstallation sowie Rückbau geprüft
sind. Danach wird ein Coverage-Gate aktiviert. Es prüft mechanisch, ob als
personenbezogen klassifizierte oder administrativ erwartete Apps kompatible
Provider und Datenschutzmetadaten besitzen. Bis dahin meldet der Root-Check
nur Planungs- und Dokumentkonsistenz; er behauptet keine
Runtime-Vollständigkeit.

### Etappe 7 – Fremd-App-Coverage

Erst nachdem die eigenen Nextcloud-Apps nach dem laufenden Providervertrag
klassifiziert und ihre bekannten Lücken geschlossen oder ehrlich als
Teilabdeckung markiert sind, folgt die Fremd-App-Coverage als getrennte
Etappe:

1. aktivierte fremde Apps nach Zweck, personenbezogenen Daten,
   Nebenspeichern und eigener Berechtigungslogik inventarisieren;
2. offizielle öffentliche Provider-, OCP-, Capability- oder Exportverträge
   bevorzugen und eine Unterstützung des Providervertrags upstream anregen;
3. fehlende oder inkompatible Verträge sichtbar als `missing`, `UNKNOWN` oder
   `UNSUPPORTED` behandeln, ohne daraus Freigaben oder Vollständigkeit
   abzuleiten;
4. einen versionsgebundenen read-only Adapter nur je konkreter App, Version
   und belegter Quelle nach eigener Risiko-, Datenschutz-, Update- und
   Releaseentscheidung zulassen;
5. keine SQL-, Reflection-, Fremd-AppConfig-, Volltext-, Datei- oder
   Migrator-Fallbacks einführen. Die Groupfolders-Ausnahme ist kein
   allgemeiner Präzedenzfall.

Die erste Aufgabe dieser Etappe ist eine read-only Bestands- und
Schnittstellenanalyse. Sie autorisiert noch keinen Adapter und keine Änderung
an einer fremden App.

## Migrationsmatrix

`nötig` bedeutet geplant, nicht implementiert. Fristen und Maßnahmen sind
bewusst nicht vorweggenommen.

Keines der App-Repositories besitzt am 9. September 2026 bereits einen
app-lokalen, gegen das neue Root-Schema validierten Processing-Katalog. Die
vorhandenen Provider enthalten schmalere, teils datensatzbezogene Metadaten;
sie sind Implementierungsbeleg, aber noch keine einmalig nutzbare
Processing-Policyquelle. Bis zur jeweils ausdrücklich beauftragten
app-lokalen Migration gelten folgende echte Entscheidungslücken:

| App / Verarbeitung | `PRIVACY-DECISION-REQUIRED` | Technische Relevanz | Blockierend | Fachliche Zuständigkeit |
| --- | --- | --- | --- | --- |
| `brtop` / Sitzungen, Ladungen und Dokumente | Zweck-/Empfängerabgrenzung und Retention für Gremieninhalte, Pfade, Anhänge und Nachweise | bestimmt Katalog, vollständige Art.-15-Projektion, Datei-/RetentionProvider und Anonymisierung | ja für Aufnahme dieser Inhalte, nein für bestehende Teilprojektion | ungeklärt |
| `adplaner` / Schichtplanung | Retention, Empfänger und Erforderlichkeit freier Tagesnotizen | bestimmt Katalog, Datenminimierung, Providerprojektion und Lösch-/Anonymisierungsweg | ja für automatische Maßnahme, nein für bestehende Auskunft | ungeklärt |
| `brstunden` / Stunden- und Fortbildungsnachweise | Retention und zulässiger Aggregaterhalt nach Entfernen des Personenbezugs | bestimmt Katalog und Retention-/Anonymisierungsvertrag | ja für automatische Maßnahme, nein für bestehende Auskunft | ungeklärt |
| `localbase` / persönliche UI-Werte und Demo-Registry | Zweck, Retention und Löschzuständigkeit für UI-Werte und Demokontenregister | bestimmt eigenen Katalog und fehlende Providerabdeckung | ja für Vollständigkeitsbehauptung, nein für heutige Fachapps | ungeklärt |
| `filzmann_permission_matrix` / Snapshot-, Export- und Auditnachweise | fachliche Maßnahme nach Ablauf der vorhandenen REVIEW-Fristen | bestimmt späteren ausführenden Retention-Contract; die bestehenden 180-Tage-Previews bleiben REVIEW | ja für Ausführung, nein für Preview und Auskunft | ungeklärt |
| `filzmann_data_protection` / Adminfreigabehistorie | Aufbewahrung, Sperren und zulässige Maßnahme für den sicherheitsrelevanten Nachweis | bestimmt eigenen Katalog und RetentionProvider | ja für Retention-Ausführung, nein für Aggregation | ungeklärt |
| `adcalendar` / Dienste, Termine, Einstellungen und Verbindungen | Retention, Empfänger und Behandlung abgeleiteter DAV-/Providerkalender nach Restore | bestimmt Katalog, Retention und Backup-/Restore-Vertrag | ja für automatische Maßnahme, nein für bestehende Auskunft | ungeklärt |
| `adurlaub` / Urlaubsverwaltung | fachliche Frist, zentrale REVIEW-Berechtigung und Behandlung freiwilliger Notizen | bestimmt Katalog und Migration des Retention-Piloten | ja für Standalone-Retention und Ausführung, nein für Auskunft | ungeklärt |
| `orgsuite` / Navigation und Adminadapter | derzeit keine eigene personenbezogene Verarbeitung belegt; bei Scopeänderung erneut entscheiden | begründete Nichtanwendbarkeit, kein leerer Scheinprovider | nein | nicht anwendbar im heutigen Scope |
| `adroom` / Raumbuchung | fachliche Frist, Trigger und Maßnahme für Buchungen | bestimmt Katalog und Migration des Retention-Piloten | ja für automatische Maßnahme, nein für Auskunft | ungeklärt |
| `adrecruitment` / Bewerbungsakten und interne Bearbeitung | Rechtsgrundlage, Fristen, Sperren, externer Subject-Vertrag und differenzierte Maßnahmen | bestimmt Katalog, Bewerberauskunft, Retention und AppData-Anhänge | ja für externen Subject- und Retention-Scope, nein für bestehende interne Teilprojektion | ungeklärt |
| `adbqplanung` / PFK- und Dozentinnenplanung | Retention-Trigger/-Maßnahmen und sicherer externer Subject-Vertrag | bestimmt Katalog, externe Auskunft und Retention | ja für externen Subject- und Retention-Scope, nein für interne Auskunft | ungeklärt |

Rechtsgrundlagen, Backupfristen, konkrete fachliche Verantwortlichkeiten und
bislang nicht ausdrücklich festgelegte Empfänger werden bei der app-lokalen
Katalogerstellung ebenfalls als `PRIVACY-DECISION-REQUIRED` erfasst; die
Tabelle erfindet dafür keine Defaultwerte. `orgsuite` bleibt die begründete
Nichtanwendbarkeit und wird bei jeder Scopeänderung neu bewertet.

| App | personenbezogene Daten laut aktuellem Code | PersonalDataProvider nötig | RetentionProvider nötig | Lifecycle-Abhängigkeit | Anonymisierung sinnvoll | Priorität | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `brtop` | Nextcloud-UIDs, Namen und E-Mails von Mitgliedern/Empfänger*innen, Vertretungen, Abwesenheiten, personenbezogene TOP-/Protokollinhalte, Dokument- und Anhangspfade sowie erzeugte Dateien im persönlichen Files-Bereich; keine gezielte Team-Folder-API | ja | ja | Konto, Mitgliedschaft und später Beschäftigungsende; Legislaturende ist ein eigener Fachtrigger | für einzelne historische Referenzen möglich; Ladungs- und Dokumentnachweise brauchen Fachentscheidung | hoch | Standalone-V1-Metadatenprovider umgesetzt: explizite UID-Bezüge werden subjectgebunden projiziert. Datei-/Anhangpfade, Dateiinhalte und Gremieninhalte bleiben ausgeschlossen und als Teilantwort sichtbar; eine spätere sichere Inhaltslösung bleibt offen. Keine Retention-Policy |
| `adplaner` | Assistenz- und Bearbeiter-UIDs, Schichtwünsche/-zuweisungen, freie Tagesnotizen | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | bei historischen Zuweisungen und Bearbeiterreferenzen prüfbar | hoch | PersonalDataProvider auf Standalone V1 migriert; Schichtwünsche/-zuweisungen und alle gespeicherten Bearbeitungsreferenzen werden subjectgebunden projiziert. Fremde UIDs und freie Tagesnotiztexte werden nicht ausgegeben; keine Retention-Policy |
| `brstunden` | Mitglieds- und Bearbeiter-UIDs, Monats-/Fortbildungsminuten, freie Notizen; Abrechnungs-PDFs werden nur im Response erzeugt und nicht appseitig in Files/Team Folders gespeichert | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | Aggregaterhalt mit entfernter Personenreferenz denkbar, fachlich offen | hoch | PersonalDataProvider auf Standalone V1 umgesetzt: eigene Stunden, Fortbildungszeiten und Notizen werden vollständig ausgewiesen; reine Bearbeitungsbezüge auf fremden Sätzen bleiben neutral und geben keine Drittpersonendaten preis. Fachliche Einzellöschung vorhanden, keine Retention-Policy |
| `localbase` | Nextcloud-Kontoprofil sowie persönliche Adminlayout-/Zoomwerte und Registry synthetischer Demokonten; Organisationssnapshot selbst enthält keine Mitgliederlisten | ja für app-eigene Personenwerte | zu prüfen: native UserConfig-Bereinigung versus Demo-Registry | Kontolebenszyklus für persönliche Werte; kein Beschäftigungsende | für Demo-Registry nicht der primäre Weg; persönliche Werte eher löschen | mittel | Öffentliche Privacy-Verträge, Registry, Aggregation und UI sowie Nextcloud-Kontoprovider implementiert; persönliche LocalBase-UI-Werte und Demo-Registry noch nicht abgedeckt |
| `filzmann_permission_matrix` | Snapshot-/Export-Ersteller-UIDs und Audit-UIDs; `include_users` ist konfigurierbar, im aktuellen Snapshotcode sind jedoch keine persistierten Benutzerlisten belegt | ja | ja | Kontolebenszyklus und eigener Auditnachweis | für ältere Ersteller-/Auditbezüge prüfbar; Beweiswert beachten | hoch, IKT/Datenschutz | PersonalDataProvider und öffentlicher Standalone-V1-Preview-Provider implementiert: eigene Art.-15-Bezüge bleiben kontextuell erhalten; Exportmetadaten und Auditprotokolle werden nach getrennt konfigurierbaren, standardmäßig 180-tägigen Fristen ausschließlich als `REVIEW` gemeldet. Inhalte, Dateinamen, freie Auditdetails, UIDs und Drittpersonenangaben bleiben ausgeschlossen. Keine Retention-Ausführung und kein Lifecycle-Provider |
| `filzmann_data_protection` | angefragte Nextcloud-UID und aggregierte Berichte bleiben transient; die app-lokale Historie zeitlich begrenzter Adminfreigaben persistiert Ziel-, Freigabe- und Widerrufs-UIDs samt Zeitpunkten. Reviewer-Gruppen sind Rollen-Konfiguration, keine Mitgliedskopie | ja | ja, für die eigene Adminfreigabehistorie zu entscheiden | Kontolebenszyklus und noch offene Aufbewahrungsentscheidung für den sicherheitsrelevanten Freigabenachweis | erst nach fachlicher Aufbewahrungsentscheidung; der Beweiswert ist zu erhalten | hoch, IKT/Datenschutz | Eigener PersonalDataProvider auf Standalone V1 und eigener PermissionProvider auf Matrix V1 umgesetzt. Der Provider projiziert nur die Rolle der betroffenen Person und neutralisiert fremde Administrator*innen; eine Retention-Policy für die Adminfreigabehistorie fehlt noch |
| `adcalendar` | Mitarbeiter- und Ersteller-UIDs, Dienste/Termine/Titel, persönliche Filter/Dienststandards, externe Verbindungskonfiguration, erzeugte DAV-/Providerkalender | ja | ja | Beschäftigungs-/Kontolebenszyklus sowie Entzug externer Verbindungen; derzeit keine Beschäftigungsquelle | für historische Dienste/Termine möglich; Secrets werden gelöscht, nicht ausgegeben | sehr hoch | PersonalDataProvider auf Standalone V1 für eigene Dienste und Termine sowie tatsächlich gespeicherte persönliche Filter-, Standarddienst- und Sync-Werte vervollständigt. Gemeinsame Meetings nennen weitere Beteiligte nur abstrakt; Filter nennen ausgewählte Personen nur als Anzahl. Externe Verbindungen werden ohne Entschlüsselung ausschließlich als Anbieter-/OAuth-Vorhandenseinsmetadaten ausgewiesen. Serveradressen, Kontonamen, technische Kennungen, Passwörter, Tokens und OAuth-State bleiben ausgeschlossen. Native DAV- und externe Kalenderobjekte sind abgeleitete Darstellungen der führenden AD-/Urlaubsdaten und werden nicht als zweite Quelle gelesen. Keine Files-/Team-Folder-Ablage, keine Retention-Policy |
| `adurlaub` | Mitarbeiter- und Ersteller-UIDs, Urlaubszeiträume, Status und freie Notiz | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | für Personenreferenzen möglich, Notiz kann Drittpersonen enthalten | sehr hoch | PersonalDataProvider auf Standalone V1 migriert; eigene freiwillige Notizen werden als möglicher Drittpersoneninhalt gekennzeichnet. Der konfigurierbare Retention-REVIEW-Dry-Run verbleibt bis zur Entscheidung über eine globale Adminprüfung im LocalBase-Pilot; Admin-UI der Regel noch offen |
| `orgsuite` | keine eigenen Fachdaten oder App-Tabellen; Navigation und LocalBase-Adminadapter | derzeit nein | derzeit nein | keine eigene Quelle | nicht anwendbar | niedrig | Kein eigener Provider erforderlich; bei neuen Personenwerten neu bewerten |
| `adroom` | Buchungs-UID, Zweck, freier Titel und Zeitraum | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | neutraler Platzhalter erhält den Buchungskontext ohne möglichen Drittpersonen-Freitext | hoch, Pilot | PersonalDataProvider auf den öffentlichen Standalone-V1-Vertrag migriert und aktiv/deaktiviert lokal in Nextcloud verifiziert; Raum, Zweck und Zeitraum bleiben erhalten, der freie Titel wird neutral ersetzt. LocalBase-Retention-Dry-Run mit `REVIEW` bleibt separat; keine fachliche Frist, Ausführung oder Lifecycle-Quelle |
| `adrecruitment` | interne Bewerber-ID, Namen/Kontakt, Bewerbung und Statushistorie, Interviews/Antworten, BQ-Bewertung, Einstellungsdaten, Nachrichten, Anhänge in AppData, Kommentare, Feldnachweise sowie Beschäftigten-UIDs in Bearbeitung/Audit | ja, getrennte Subject-Typen | ja | Prozessabschluss für Bewerbungen; Beschäftigungs-/Kontolebenszyklus für interne Akteur*innen; keine Beschäftigungsquelle | nur differenziert: Akteur*innenreferenzen eventuell, Bewerbungsakte überwiegend löschen/sperren nach Fachentscheidung | sehr hoch | PersonalDataProvider für alle internen Nextcloud-UID-Bezüge implementiert; Bewerber-Selbstauskunft bleibt bis zu einem sicheren authentifizierten Subject-Vertrag offen; `retention_state` ohne ausführende Policy |
| `adbqplanung` | interne PFK-UIDs, minimale externe Dozentinnenprofile mit Name und E-Mail, Lehranfragen und Bearbeitungsreferenzen; keine Bewerbungsakten oder Teilnehmerkopien | ja | ja | Beschäftigungs-/Kontolebenszyklus für interne Akteur*innen sowie fachlicher Abschluss externer Lehranfragen; derzeit keine belastbare Quelle | Entfernen oder Anonymisieren abgeschlossener externer Kontakte und Bearbeitungsreferenzen fachlich zu prüfen | sehr hoch | Standalone-V1-Provider für interne Nextcloud-PFKs umgesetzt: Profil, Hauptleitung, Modulzuordnung und subjectgebundene Bearbeitungsnachweise werden ausgegeben. Externe Profile bleiben bis zu einem authentifizierten externen Subject-Vertrag ausgeschlossen; keine E-Mail-Identifikation. Retention-Trigger und Maßnahmen offen |

## Neue Apps

Sobald personenbezogene Daten Bestandteil des Funktionsumfangs werden, muss
die App-Planung vor fachlicher Fertigstellung beantworten:

1. Welche personenbezogenen Daten werden gespeichert?
2. Welche realen Personentypen und Identifier werden verwendet?
3. Wie liefert die App eine zulässige Auskunft?
4. Welche Inhalte anderer Personen können enthalten sein?
5. Welche Aufbewahrungsregel oder manuelle Prüfung ist vorgesehen?
6. Welche Triggerdaten oder Lifecycle-Ereignisse werden benötigt?
7. Welche Datenklassen unterstützen `DELETE`, `ANONYMIZE`,
   `REMOVE_PERSON_REFERENCE` oder `REVIEW`?
8. Welche `PersonalDataProvider`-, `PermissionProvider`-, Rechte-, Negativ-
   und Grenztests belegen den Vertrag?

Sind personenbezogene Daten oder eigene Fachberechtigungen bereits Teil des
ersten Scopes, gehören der jeweils anwendbare Provider und sein Contract-Test
zum ersten betroffenen Feature. Ein bloßer Planungseintrag genügt nicht für
dessen fachliche Fertigstellung oder den ersten Release. Sind beide Provider
nach dem Zweck der App nicht anwendbar, wird diese Entscheidung begründet und
bei jeder Scopeänderung neu geprüft.

## Test- und Reviewvertrag für spätere Umsetzung

- Provider- und Consumer-Contract-Tests sichern jeden öffentlichen Vertrag.
- Self-Service testet Sessionbindung, manipulierte Ziel-UID, fremde Inhalte,
  Teilantwort und Providerfehler.
- Admin-Auskunft testet dedizierte Allow-/Deny-Fälle, manipulierte Subjects,
  Auditmetadaten und ausbleibende Berichtskopien.
- Retention testet Triggergrenzen, Konfigurationsgrenzen, Dry Run, veränderten
  Previewstand, Sperren, Wiederholung, Teilfehler, Nebenläufigkeit und
  Nebenspeicher.
- Anonymisierung belegt, dass weder aktive Referenz noch versteckte
  Rückauflösung verbleibt; Platzhalter erteilen keine Rechte.
- Lifecycle-Tests behandeln unbekannte, fehlende, korrigierte und
  widersprüchliche Ereignisse ohne unkontrollierte Löschung.

## Unentschiedene Architekturgrenzen

Die folgenden Grenzen bleiben offen; konkrete Aufgaben, Reihenfolge und
Freigabegates stehen ausschließlich in `docs/zukunftsplan.md`:

1. Welche Organisation und welcher Maintainerkreis verantworten den
   öffentlichen Vertrag, Releases und Sicherheitsmeldungen?
2. Welche kleinste Nextcloud-Version und welche Vertragsversionen werden im
   ersten Release unterstützt?
3. Wie wird eine erwartete Providerabdeckung zur Laufzeit deklariert, bevor
   das spätere Release-Gate aktiv ist?
4. Welche Stelle liefert künftig Beschäftigungsende und Korrekturen mit
   belastbarer Semantik?
5. Wie werden externe Bewerber*innen identifiziert und Auskünfte sicher
   zugestellt, ohne sie künstlich zu Nextcloud-Konten zu machen?
6. Welche Aufbewahrung benötigt das Audit der Admin-Auskunft selbst?
7. Wann ist der Vertrag stabil genug, um eine implementierbare OCP-
   Schnittstelle bei Nextcloud vorzuschlagen?

### Pilotentscheidungen vom 12. August 2026

Für den ausdrücklich beauftragten Pilot mit `localbase` und `adroom` gelten
folgende enge Entscheidungen. Sie beantworten nur den Pilotumfang und nehmen
keine allgemeine Store- oder rechtliche Retentionentscheidung vorweg:

1. LocalBase ist im internen Pilot eine getrennt versionierte
   Kategorie-B-Runtimevoraussetzung. Standalone bedeutet für AD Raumplaner
   den Betrieb ohne andere Fachapps, nicht ohne diese deklarierte
   Infrastruktur. Ein öffentlicher Store-Release bleibt blockiert, bis
   Versionshandshake, Installations-/Deinstallationsvertrag und öffentliche
   Zumutbarkeit der Zusatz-App gesondert entschieden und geprüft sind.
2. Self-Service bindet das Subject ausschließlich an die UID der aktiven
   Nextcloud-Sitzung. Die Admin-Auskunft benötigt zusätzlich zu einer
   authentifizierten Sitzung die Mitgliedschaft in einer ausdrücklich
   konfigurierten Nextcloud-Datenschutzgruppe. Nextcloud-Adminstatus allein
   erteilt keinen inhaltlichen Auskunftszugriff. Fehlt die Konfiguration, gilt
   deny by default.
3. Der Pilot liefert eine zugängliche, appweise und nach Datenarten gegliederte
   menschliche Ansicht. Jeder Datensatz nennt Zweck und Aufbewahrung; die
   Betroffenenrechte werden einmal im Kopf erläutert. Derselbe flüchtig
   aggregierte Stand wird clientseitig als mehrseitiges PDF erzeugt und nicht
   auf dem Server gespeichert. Drittpersonen werden nur abstrakt erwähnt,
   niemals namentlich aus fremden Datensätzen übernommen.
4. Die Laufzeitantwort weist den festen Registry-Snapshot und den Status jedes
   darin registrierten Providers aus. Eine erwartete vollständige App-Liste
   und ein Release-Gate werden im Pilot noch nicht behauptet.
5. Beschäftigungsende und Korrekturen bleiben bis zur Benennung einer
   belastbaren Quelle außerhalb des Piloten. Retention beginnt ausschließlich
   als Dry Run mit der Maßnahme `REVIEW`; es gibt keine erfundene Frist und
   keine automatische Löschung oder Anonymisierung.
6. Externe Bewerber*innen gehören nicht zum AD-Raumplaner-Pilot und werden
   erst vor der Recruitment-Migration entschieden.
7. Admin-Auskunft protokolliert nur datensparsame Metadaten über Nextclouds
   vorhandenen Loggingmechanismus und persistiert keine Berichtskopie. Eine
   eigene Audit-Tabelle oder app-spezifische Aufbewahrungsfrist entsteht erst
   nach einer fachlich und datenschutzrechtlich freigegebenen Regel.

### Zielentscheidung vom 23. August 2026

Der LocalBase-Pilot wird nicht zur dauerhaft öffentlichen Privacy-Plattform
ausgebaut. Ziel ist `filzmann_data_protection` aus ADR 0002. Andere
Nextcloud-Apps integrieren sich über einen kleinen, versionierten und lazy
registrierten Providervertrag aus
[`docs/privacy-provider-guide.md`](privacy-provider-guide.md). Sie bleiben
ohne Privacy-App fachlich standalone. Fehlende oder inkompatible Provider
werden transparent ausgewiesen; SQL-, Reflection-, Datei- oder Migrator-
Fallbacks sind verboten.

## Quellenrahmen

- [DSGVO, insbesondere Art. 5, 15 und 17](https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=de)
- [Löschkonzept des BfDI](https://www.bfdi.bund.de/SharedDocs/Downloads/DE/DokumenteBfDI/AccessForAll/2023/2021_Loeschkonzept-BfDI.html)
- [Nextcloud 34: User migration](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/user_migration.html)
- [Nextcloud: Events](https://docs.nextcloud.com/server/latest/developer_manual/basics/events.html)
- [Nextcloud 34: AppConfig](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/config/appconfig.html)

Diese Quellen begründen den Rahmen, ersetzen aber nicht die fachliche und
datenschutzrechtliche Freigabe konkreter Datenklassen, Rechtsgrundlagen,
Fristen, Empfänger oder Maßnahmen.
