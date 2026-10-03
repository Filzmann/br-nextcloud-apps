# App-übergreifende Datenschutzarchitektur

Diese Datei ist die normative Root-Quelle für den app-übergreifenden
Datenschutzvertrag. Sie beschreibt dauerhafte Grenzen, Rollen und öffentliche
Providerregeln. Aktive Rolloutarbeit steht ausschließlich im
[`Zukunftsplan`](zukunftsplan.md); fachliche Werte stehen ausschließlich in
den app-lokalen Processing-Katalogen.

## Verantwortungs- und Datenbesitzgrenze

Jede Fachapp bleibt alleinige Eigentümerin ihrer Fachdaten und fachlichen
Wahrheit. Die zentrale Datenschutzkomponente hat deshalb keinen direkten SQL-Zugriff
auf fremde Tabellen und liest oder verändert weder fremde
Entitäten, Volltexte, Dateien noch Konfigurationen.
Sie registriert Provider, ruft sie isoliert auf, aggregiert zulässige Antworten
und weist Vollständigkeit und Fehler sichtbar aus.

Die Datenowner-App entscheidet und testet selbst Datenklassen,
Personenreferenzen, zulässige Auskunft, Drittpersonenschutz, Trigger,
Sperren, Löschung, Anonymisierung, Personenentkopplung sowie Artefakte in
Datenbank, Konfiguration, AppData, Dateien, Shares, Exporten, Indizes und
Caches. Der auslieferbare app-lokale Processing-Katalog führt Datenklassen,
Zwecke, Empfänger, technische Fristen und offene Entscheidungsbedarfe. Die
konkrete kundenlokale Rechtsgrundlage, Vereinbarung, DPO-/Betriebsratsfreigabe,
verantwortliche Organisation und zugehörige Evidenz bleiben außerhalb des
Produktpakets; Root und Runtime duplizieren sie nicht.

`Datenschutzbeauftragte` verantwortet den zentralen Auskunftsdienst und darf
die zentrale Admin-Auskunft nutzen. Native Nextcloud-Administration erteilt
keinen fachlichen Bypass. IT-Administration verantwortet nur Plattform und
Backups. Jede Fachapp führt technisch aktivierte Maßnahmen ausschließlich auf
ihren eigenen Daten selbst aus.

Die Berechtigungsmatrix bleibt gemäß
[ADR 0003](architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md)
(`docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md`)
eine getrennte IKT/Datenschutz-App; sie wird nicht in die Privacy-Runtime
verschmolzen.

Nach [ADR 0001](architecture-decisions/0001-shared-code-runtime-and-app-store.md)
(`docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`) ist
die Privacy-Plattform eine eigenständige Kategorie B-Nextcloud-App.
[ADR 0002](architecture-decisions/0002-standalone-privacy-platform.md)
(`docs/architecture-decisions/0002-standalone-privacy-platform.md`)
legt `filzmann_data_protection` als neutrale Standalone-App und Runtime-Owner
fest. Fachapps bleiben ohne aktive Privacy-App fachlich standalone. Fehlende
oder inkompatible Provider werden sichtbar und nutzen keinen Daten-Fallback
über SQL, Reflection, Dateien, Konfiguration oder `IUserMigrator`. Der
Integrationsvertrag steht in [`privacy-provider-guide.md`](privacy-provider-guide.md)
(`docs/privacy-provider-guide.md`).
Das harte Prinzip lautet: kein Daten-Fallback.

## Processing-Metadata-Vertrag

Es gilt „Central governance, decentralized ownership“: Root besitzt das
Schema [`privacy-processing-metadata.schema.json`](contracts/privacy-processing-metadata.schema.json),
die Datenowner-App genau ihren Katalog
`resources/privacy-processing.json`. Jede neue oder geänderte
personenbezogene Verarbeitung bestimmt dort eine `processing_id`. Der Katalog
ist die einzige Quelle für fachliche Werte; ein optionaler
`ProcessingMetadataProvider` projiziert nur den erforderlichen Ausschnitt.
Root, Registry und Privacy-Runtime führen keine zentrale Kopie;
personenbezogene Laufzeitdaten bleiben bei der Datenowner-App.
Fehlende Zwecke, Erforderlichkeit, Empfänger, Zugriff,
Rechtsgrundlage, Retention- oder Backupentscheidung werden als
`PRIVACY-DECISION-REQUIRED` ausgewiesen. Dieser fachliche Status bleibt für
Betreiber transparent, ist aber nicht automatisch ein technisches
DELETE-Gate. Destruktive Ausführung richtet sich ausschließlich nach dem
ausdrücklich versionierten technischen Vertrag der jeweiligen Policy.

## Provider-Registry und Auskunft

Aktivierte Fachapps registrieren konkrete Provider über einen kleinen,
typisierten und versionierten Vertrag. Ein Versionshandshake prüft vor dem
Abruf Vertragsversion, Subject-Typen, Fähigkeiten und Paginggrenzen. Ein
Descriptor enthält mindestens
stabile App-ID, Anzeigename, Vertragsversion, Subject-Typen, Fähigkeiten und
Paginggrenzen. Doppelte IDs oder inkompatible Versionen werden abgelehnt oder
als Coverage-Fehler ausgewiesen. Der Snapshot einer Anfrage bleibt fest;
Providerfehler werden je Provider isoliert.

Statuswerte sind `complete`, `partial`, `not_applicable`, `failed` und
`missing`. Vollständigkeit bezieht sich ausschließlich auf den registrierten
Scope und behauptet keine globale Abdeckung ohne eigenes Coverage-Gate.

Ein `PersonalDataProvider` liefert nur die für das Subject zulässige Sicht.
Sein Bericht enthält verständliche Kategorien, Zweck, Herkunft und
Empfängerkategorie soweit bekannt, Aufbewahrungsregel oder Kriterien,
Drittpersonenschutz sowie Status und Einschränkungen. Passwörter, Tokens,
verschlüsselte Zugangsdaten, interne Hashes und andere Geheimnisse werden nie
ausgegeben. Cursor-Paging darf weder still auslassen noch doppeln;
veränderliche Stände werden stabilisiert oder als `partial` gekennzeichnet.

Self-Service bindet das Subject serverseitig an die aktive Nextcloud-Sitzung;
eine Browser-UID ist nie autoritativ. Admin-Auskunft verwendet einen getrennten
serverseitigen Zugriffspfad und ausschließlich die Gruppe
`Datenschutzbeauftragte`. Berichte werden nicht als zentrale zweite
personenbezogene Kopie persistiert. Auditdaten bleiben datensparsam und
enthalten keine Berichtsinhalte.

## Retention und Lifecycle

Ein `RetentionProvider` bleibt Owner der Kandidatensuche, Triggerauswertung,
Sperrprüfung und Maßnahme. Die zentrale Runtime übergibt nur Policy,
Bewertungszeitpunkt, Batchgrenze und gegebenenfalls ein geprüftes
Lifecycle-Ereignis; sie übergibt nie SQL oder fremde Primärschlüssel zur
zentralen Löschung.

Der öffentliche V1-Vertrag bleibt read-only und besitzt keine
`execute()`-Methode. Der getrennte V2-Vertrag registriert ausführende
DELETE-Provider versioniert und lazy. Automatische Ausführung startet nach
Installation oder Upgrade deaktiviert. Nur native Nextcloud-Administration
darf sie über eine revisionsgesicherte technische Option aktivieren oder
deaktivieren; sie wählt dabei weder Personen noch einzelne Datensätze aus.
Rechtsgrundlage, Vereinbarung, DPO-/Betriebsratsbestätigung und
Kundenevidenz sind keine Aktivierungsfelder oder technischen DELETE-Gates.
Ihre Prüfung bleibt Betreiber-Governance außerhalb des Pakets.

Policies besitzen stabile IDs, Datenklasse, Zweck, Rechtsgrundlagenhinweis,
Trigger, Dauer oder Termin, Konfigurationsgrenzen, Maßnahme, Sperr-/Reviewregel
und Version. Unterstützte Trigger sind `CREATED_AT`, `COMPLETED_AT`,
`SUBJECT_EVENT`, `FIXED_DATE` und `NO_AUTO_ACTION`; Maßnahmen sind
`DELETE`, `ANONYMIZE`, `REMOVE_PERSON_REFERENCE` und `REVIEW`. Platzhalter
dürfen keine Rückauflösung, Rechte oder Zuständigkeiten erzeugen.

`NO_AUTO_ACTION` bedeutet „Keine automatische Aktion“. Die Migrationsmatrix
einer App dokumentiert nur die Überführung eigener Provider- und
Policyversionen; sie erzeugt keine zentrale Kopie oder Fremd-App-Mutation.

Ausführung benötigt einen passenden Dry Run sowie unveränderten Policy- und
Previewstand. Ungültige, fehlende oder deaktivierte technische Aktivierung,
künftig datierte Backup-/Restore-Prüfzeitpunkte, ein fälliger oder veralteter
nächster Prüftermin, unbekannte oder inkompatible Provider, unbekannte
Ereignisse, Holds, Integritäts- und Nebenläufigkeitskonflikte oder
widersprüchliche Daten bewirken keine destruktive Aktion. Löschung und
minimaler technischer Nachweis müssen je Datensatz atomar sein; Läufe müssen
gebatcht, wiederholbar, nebenläufigkeitssicher und fehlerisoliert sein.

Die technische Aktivierung akzeptiert eine reguläre Backup-Aufbewahrung von 1
bis 365 ganzen Kalendertagen und 0 bis 5 Tage technischen Puffer. Sie verlangt
bereits vergangene Backup- und Restore-Prüfzeitpunkte sowie einen zukünftigen,
spätestens innerhalb eines Jahres fälligen nächsten Prüftermin. Fehlende oder
veraltete technische Prüfwerte blockieren fail-closed. Die App prüft damit
nur Konsistenz und Aktualität der Betreiberangabe; sie untersucht keine
externen Sicherungsmedien und behauptet keinen vollständigen Entfall aus
Backups. Nach Restore werden Aktivierung, Policy, Providerkompatibilität,
Trigger und Holds erneut geprüft; abgelaufene Daten dürfen nicht dauerhaft
reaktiviert und fachliche Adminfreigaben niemals wieder wirksam werden.

Der erste Produktvorschlag umfasst `P1Y` ab Buchungsende für Raumbuchungen und
`P6M` ab tatsächlichem Ende für Adminfreigabehistorien. Das sind technische
Standardvorschläge, keine Rechts- oder Beteiligungsentscheidung des
Softwareherstellers.

`SubjectLifecycleProvider` liefert nur geprüfte Ereignisse mit Subject,
Ereignistyp, Zeitpunkt, Quelle, Vertrauensstatus und stabiler ID. Eine
Kontodeaktivierung oder -löschung wird nicht als Beschäftigungsende abgeleitet.
Unbekannte, fehlende oder widersprüchliche Ereignisse lösen höchstens
`REVIEW` aus. Ohne belastbare Beschäftigungsquelle und getesteten
Ausführungsvertrag gibt es keinen globalen Lifecycle-Lauf und keine globale
automatische Retention. Diese Grenze verhindert keine app-lokale, technisch
aktivierte Löschung mit einem fachlich unabhängigen Trigger wie Buchungsende
oder tatsächlichem Ende einer Adminfreigabe.

## Test- und Änderungsvertrag

Bei jeder relevanten Weiterentwicklung einer personenbezogenen Verarbeitung
werden Provider, Processing-Katalog, Retention-/Lifecycle-Auswirkungen,
Drittpersonen und Consumer im selben App-Auftrag geprüft. Öffentliche Verträge erhalten Provider- und
Consumer-Contract-Tests. Relevante Negativfälle umfassen manipuliertes Subject,
fremde Inhalte, Teilantworten, Providerfehler, Sperren, Wiederholung und
ausbleibende Nebenwirkungen. Restore darf abgelaufene Daten oder Berechtigungen
nicht unkontrolliert reaktivieren.

Dies gilt bei jeder relevanten Weiterentwicklung.

## Unentschiedene Grenzen

Offene Entscheidungen und ihre Reihenfolge stehen im
[`Zukunftsplan`](zukunftsplan.md): erwartete Providerabdeckung, belastbare
Beschäftigungsquelle, sichere externe Subjects, Audit-Aufbewahrung,
Runtime-Abnahme und Erweiterung der Retention-Ausführung sowie ein möglicher
stabiler Upstream-Vertrag.

Fremd-App-Coverage bleibt read-only: Fehlt ein kompatibler öffentlicher
Provider, lautet der Status `missing`, `partial`, `UNKNOWN` oder `UNSUPPORTED`;
aus einer App-Installation wird weder Dateninhalt noch Berechtigung abgeleitet.

## Quellenrahmen

- [DSGVO, insbesondere Art. 5, 15 und 17](https://eur-lex.europa.eu/eli/reg/2016/679/oj?locale=de)
- [Löschkonzept des BfDI](https://www.bfdi.bund.de/SharedDocs/Downloads/DE/DokumenteBfDI/AccessForAll/2023/2021_Loeschkonzept-BfDI.html)
- [Nextcloud: User migration](https://docs.nextcloud.com/server/stable/developer_manual/digging_deeper/user_migration.html)
- [Nextcloud: Events](https://docs.nextcloud.com/server/latest/developer_manual/basics/events.html)

Diese Quellen begründen den Rahmen, ersetzen aber keine fachliche oder
datenschutzrechtliche Freigabe konkreter Verarbeitungen.
