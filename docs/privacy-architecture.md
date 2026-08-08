# App-übergreifende Datenschutzarchitektur

Stand: 8. August 2026

Dieses Dokument ist die normative Root-Quelle für app-übergreifende
Datenschutzauskunft, Datenlebenszyklen, Aufbewahrung, Löschung und
Anonymisierung. Es legt Verträge und Ausbauplanung fest, implementiert aber
noch keine zentrale Runtime und trifft keine pauschale Aussage, der Workspace
oder eine App sei DSGVO-konform.

Die Inventur beschreibt den aktuellen Arbeitsbaum einschließlich noch nicht
committeter Änderungen. App-spezifische Entscheidungen und Implementierungen
bleiben Aufgaben der getrennten App-Repositories und brauchen jeweils einen
eigenen Auftrag.

## Verifizierter Ausgangsstand

- Keine App implementiert derzeit einen `PersonalDataProvider`,
  `RetentionProvider` oder `SubjectLifecycleProvider`.
- Es gibt keine aggregierte Self-Service- oder Admin-Auskunft.
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
Nextcloud-Laufzeit-App der Kategorie B, keine gebundelte Hilfsbibliothek. Die
öffentlichen DTOs, Provider-Verträge, Registry und Aggregation werden in einer
späteren Ausbaustufe in LocalBase umgesetzt. OrgSuite darf bei aktiver Suite
einen Navigation- oder Adminadapter anbieten, ist aber weder
Fachdatenbesitzerin noch notwendige Runtime der Auskunft.

Eine Fachapp, die einen Provider nutzt, muss vor ihrer Anbindung die
kompatible LocalBase-Laufzeitvoraussetzung dokumentieren, kontrolliert prüfen
und in ihrem Standalone-Liefervertrag als getrennte App erhalten. Es wird
keine automatische App-zu-App-Installation oder unkontrollierte
Versionsauflösung vorausgesetzt. Die aktuell noch nicht überall ausdrücklich
abgesicherte Abhängigkeit ist eine Migrationsaufgabe und kein Grund für
versteckte Klassenkopien.

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
typisierten LocalBase-Registrierungsvertrag. Eine Registrierung enthält eine
stabile App-ID, Anzeigename, unterstützte Subject-Typen und die tatsächlich
angebotenen Providerfähigkeiten. Doppelte App-IDs oder widersprüchliche
Fähigkeiten werden abgelehnt.

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
    public function appId(): string;
    public function supportedSubjectTypes(): array;
    public function collect(PersonalDataRequest $request): PersonalDataReport;
}
```

`PersonalDataRequest` enthält ausschließlich die typisierte betroffene Person,
Sprache, Ausgabezweck und technische Begrenzungen. Ein Providerbericht enthält
strukturierte Kategorien und menschenlesbare Einträge mit mindestens:

- Datenkategorie und konkrete zulässige personenbezogene Daten;
- Verarbeitungszweck;
- Herkunft, soweit bekannt;
- Empfänger oder Empfängerkategorie, soweit relevant;
- Aufbewahrungsregel oder nachvollziehbare Kriterien;
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

Die Ansicht zeigt je App Kategorien, konkrete zulässige Daten, Zweck,
Herkunft, Empfängerkategorien, Aufbewahrungskriterien und Providerstatus. Sie
bietet eine verständliche HTML-Ansicht und einen strukturierten Download. Der
Gesamtstatus nennt erfolgreiche, teilweise, fehlende und fehlgeschlagene
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

## Ausbauetappen

### Etappe 1 – Architekturvertrag

Mit diesem Dokument umgesetzt:

- aktuelles Dateninventar und Identifier-Grenzen;
- Provider-, Registry-, Retention- und Lifecycle-Zielvertrag;
- Rechte- und Aggregationsgrenzen;
- Root-Migrationsmatrix und schrittweise Ausbauplanung;
- Root-Prüfvertrag für die normative Quelle.

Noch nicht umgesetzt sind PHP-Verträge, Registry, UI, Jobs oder App-Provider.

### Etappe 2 – Zentrale Basis

Eigener Cross-Repository-Auftrag für `localbase` und genau eine Pilot-App:

1. LocalBase-Laufzeitabhängigkeit und Standalone-Paketvertrag klären.
2. DTOs, Provider-Registry und Aggregator test-first implementieren.
3. Retention-Policy-Modell und ausschließlich Dry-Run-Infrastruktur ergänzen.
4. Self-Service- und Admin-Grundansicht mit getrennten Rechtepfaden bauen.
5. Providerfehler, Teilantworten und strukturierte Exporte testen.

Es entsteht keine universelle Runtime ohne gleichzeitig angebundene reale
Pilot-App.

### Etappe 3 – Pilot-App

`adroom` ist der bevorzugte Pilot: Buchungen besitzen eine klare
Nextcloud-UID, überschaubare Felder sowie einen bereits gekapselten
Repository-/Servicepfad. Der Pilot muss PersonalDataProvider, Self-Service,
Admin-Auskunft, Retention-Kandidaten, Dry Run und eine freigegebene Lösch- oder
Anonymisierungsaktion vollständig demonstrieren. Frist und konkrete Maßnahme
werden erst im Pilotauftrag entschieden.

### Etappe 4 – Appweise Migration

Jede App erhält einen einzelnen Auftrag in ihrem Repository:

1. Dateninventar gegen den dann aktuellen Code bestätigen.
2. Subject-Typen und Drittpersonensicht entscheiden.
3. `PersonalDataProvider` implementieren und testen.
4. Policies, Trigger und Sperren fachlich freigeben.
5. `RetentionProvider`, Dry Run und Maßnahmen test-first implementieren.
6. Dateien und Nebenspeicher einbeziehen.
7. Self-Service-, Admin-, Provider- und Negativfälle prüfen.
8. Matrixstatus aktualisieren; erst danach die nächste App beginnen.

Kein Big-Bang und keine leeren Provider.

### Etappe 5 – Lifecycle-Ereignisse

Erst nach Benennung einer belastbaren Beschäftigungsdatenquelle:

- `SubjectLifecycleProvider` implementieren;
- Ereigniskorrekturen, fehlende und widersprüchliche Daten behandeln;
- Austrittsfristen zunächst nur im Dry Run prüfen;
- Rechteentzug, Zuständigkeitsübergabe und nachgelagerte Retention getrennt
  testen.

### Etappe 6 – Vollständigkeits- und Release-Gates

Ein Gate wird erst aktiviert, wenn Pilot und realistisch migrierbare Apps die
Verträge erfüllen. Es prüft dann mechanisch, ob als personenbezogen
klassifizierte Apps Provider und Datenschutzmetadaten besitzen. Bis dahin
meldet der Root-Check nur Planungs- und Dokumentkonsistenz; er behauptet keine
Runtime-Vollständigkeit.

## Migrationsmatrix

`nötig` bedeutet geplant, nicht implementiert. Fristen und Maßnahmen sind
bewusst nicht vorweggenommen.

| App | personenbezogene Daten laut aktuellem Code | PersonalDataProvider nötig | RetentionProvider nötig | Lifecycle-Abhängigkeit | Anonymisierung sinnvoll | Priorität | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `brtop` | Nextcloud-UIDs, Namen und E-Mails von Mitgliedern/Empfänger*innen, Vertretungen, Abwesenheiten, personenbezogene TOP-/Protokollinhalte, Dokument- und Anhangspfade | ja | ja | Konto, Mitgliedschaft und später Beschäftigungsende; Legislaturende ist ein eigener Fachtrigger | für einzelne historische Referenzen möglich; Ladungs- und Dokumentnachweise brauchen Fachentscheidung | hoch | Inventar verifiziert; kein Provider, keine Policy |
| `adplaner` | Assistenz- und Bearbeiter-UIDs, Schichtwünsche/-zuweisungen, freie Tagesnotizen | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | bei historischen Zuweisungen und Bearbeiterreferenzen prüfbar | hoch | Inventar verifiziert; kein Provider, keine Policy |
| `brstunden` | Mitglieds- und Bearbeiter-UIDs, Monats-/Fortbildungsminuten, freie Notizen | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | Aggregaterhalt mit entfernter Personenreferenz denkbar, fachlich offen | hoch | Inventar verifiziert; fachliche Einzellöschung vorhanden, keine Retention |
| `localbase` | persönliche Adminlayout-/Zoomwerte und Registry synthetischer Demokonten; Organisationssnapshot selbst enthält keine Mitgliederlisten | ja für app-eigene Personenwerte | zu prüfen: native UserConfig-Bereinigung versus Demo-Registry | Kontolebenszyklus für persönliche Werte; kein Beschäftigungsende | für Demo-Registry nicht der primäre Weg; persönliche Werte eher löschen | mittel | Inventar verifiziert; öffentliche Privacy-Verträge fehlen |
| `br_permission_matrix` | Snapshot-/Export-Ersteller-UIDs, Audit-UIDs und optional Benutzerlisten bei `include_users=true` | ja | ja | Kontolebenszyklus und eigener Auditnachweis | für ältere Ersteller-/Auditbezüge prüfbar; Beweiswert beachten | mittel | Mengenbasierte Snapshot-Retention vorhanden; kein Privacy-Provider |
| `adcalendar` | Mitarbeiter- und Ersteller-UIDs, Dienste/Termine/Titel, persönliche Filter/Dienststandards, externe Verbindungskonfiguration, erzeugte DAV-/Providerkalender | ja | ja | Beschäftigungs-/Kontolebenszyklus sowie Entzug externer Verbindungen; derzeit keine Beschäftigungsquelle | für historische Dienste/Termine möglich; Secrets werden gelöscht, nicht ausgegeben | sehr hoch | Inventar verifiziert; einzelne Opt-out-Löschwege, keine Retention |
| `adurlaub` | Mitarbeiter- und Ersteller-UIDs, Urlaubszeiträume, Status und freie Notiz | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | für Personenreferenzen möglich, Notiz kann Drittpersonen enthalten | sehr hoch | Inventar verifiziert; kein Provider, keine Policy |
| `orgsuite` | keine eigenen Fachdaten oder App-Tabellen; Navigation und LocalBase-Adminadapter | derzeit nein | derzeit nein | keine eigene Quelle | nicht anwendbar | niedrig | Kein eigener Provider erforderlich; bei neuen Personenwerten neu bewerten |
| `adroom` | Buchungs-UID, Zweck, freier Titel und Zeitraum | ja | ja | Beschäftigungs-/Kontolebenszyklus; derzeit keine Quelle | neutraler Platzhalter oder entfernte Buchungsreferenz gut als Pilot prüfbar | hoch, Pilot | Inventar verifiziert; kein Provider, keine Policy |
| `adrecruitment` | interne Bewerber-ID, Namen/Kontakt, Bewerbung und Statushistorie, Interviews/Antworten, BQ-Bewertung, Einstellungsdaten, Nachrichten, Anhänge in AppData, Kommentare, Feldnachweise sowie Beschäftigten-UIDs in Bearbeitung/Audit | ja, getrennte Subject-Typen | ja | Prozessabschluss für Bewerbungen; Beschäftigungs-/Kontolebenszyklus für interne Akteur*innen; keine Beschäftigungsquelle | nur differenziert: Akteur*innenreferenzen eventuell, Bewerbungsakte überwiegend löschen/sperren nach Fachentscheidung | sehr hoch | Umfangreiches Inventar verifiziert; `retention_state` ohne ausführende Policy |

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
8. Welche Provider-, Rechte-, Negativ- und Grenztests belegen den Vertrag?

Die Antworten müssen beim ersten Scaffold noch nicht implementiert sein. Eine
konkrete App-Aufgabe wird aber aufgenommen, sobald personenbezogene Daten zum
Scope gehören.

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

## Offene Architekturfragen

Vor Etappe 2 zu entscheiden:

1. Wie wird die LocalBase-Laufzeitabhängigkeit für alle Pilot-/Consumer-Apps
   deklarativ und im Standalone-Paket abgesichert?
2. Dürfen Nextcloud-Admins Admin-Auskunft automatisch lesen oder benötigen
   auch sie die dedizierte Datenschutzrolle?
3. Welches strukturierte Exportformat ergänzt JSON, ohne Inhalte oder
   Drittpersonen unzulässig zu vervielfältigen?
4. Wie wird eine erwartete Providerabdeckung zur Laufzeit deklariert, bevor
   das spätere Release-Gate aktiv ist?
5. Welche Stelle liefert künftig Beschäftigungsende und Korrekturen mit
   belastbarer Semantik?
6. Wie werden externe Bewerber*innen identifiziert und Auskünfte sicher
   zugestellt, ohne sie künstlich zu Nextcloud-Konten zu machen?
7. Welche Aufbewahrung benötigt das Audit der Admin-Auskunft selbst?

## Quellenrahmen

- [DSGVO, insbesondere Art. 5, 15 und 17](https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=de)
- [Löschkonzept des BfDI](https://www.bfdi.bund.de/SharedDocs/Downloads/DE/DokumenteBfDI/AccessForAll/2023/2021_Loeschkonzept-BfDI.html)
- [Nextcloud 34: User migration](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/user_migration.html)
- [Nextcloud: Events](https://docs.nextcloud.com/server/latest/developer_manual/basics/events.html)
- [Nextcloud 34: AppConfig](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/config/appconfig.html)

Diese Quellen begründen den Rahmen, ersetzen aber nicht die fachliche und
datenschutzrechtliche Freigabe konkreter Datenklassen, Rechtsgrundlagen,
Fristen, Empfänger oder Maßnahmen.
