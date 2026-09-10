# Systemischer Harness-Audit vom 9. September 2026

Begonnen am 9. September, Bereinigung und Abschluss am 10. September 2026.

## Auftrag, Methode und Beleggrenze

Parent-only: Regeln, Quellenverantwortung, Prüfeinstiege und ausgewählte
Beweisgrenzen. Kein erneuter Fachcode-Vollreview, keine App-Änderung, keine
neue Pipeline. Parent und alle 13 Subrepositories waren zu Beginn sauber.
Die Governance-Blöcke aller Subrepositories und die beiden Pflicht-Skills
aller zwölf Apps wurden gegen die bereits gelesenen kanonischen Quellen
verglichen: bytegleiche Skillkopien und identische Governance-Projektionen.
App-lokale Regeln und ausgewählte Provider-/Consumer-Tests wurden lesend
einbezogen. Eine Klassifikation auf Gruppenebene behauptet keine Prüfung
jeder einzelnen fachlichen Assertion.

Dieser datierte Bericht ist ein Auditbeleg, keine neue Regel- oder
Aufgabenquelle. Geltende Regeln bleiben in AGENTS, Architektur, ADRs und
Skills; systemweite Planung bleibt im Zukunftsplan. Der Bestandsaudit vom
5. September in [workspace.md](workspace.md) ist historische Evidenz und
wurde nicht als neuer Lauf auf aktuellen Quellen übernommen.

Die folgenden Tabellen wurden vor den Bereinigungen erstellt. Die tatsächlich
umgesetzten Änderungen und neuen Prüfergebnisse werden anschließend ergänzt.

## Harness

| Element / Fundstelle | Klassifikation | Verhinderte Fehlentscheidung / Begründung | Vorgeschlagene Aktion |
| --- | --- | --- | --- |
| AGENTS: Repositorygrenzen, Git, Stop-Regeln | KEEP | Technischen Schreibzugriff mit fachlicher Freigabe verwechseln; fremde Änderungen verlieren | Unverändert |
| Manifest, App-Dokumentstruktur, Governance-Projektion | KEEP | Zweite Repositoryliste, widersprüchliche Steuerung und funktionslose Einzel-Checkouts | Projektionen behalten; keine unabhängigen Regelkopien |
| Architektur: Entwicklungsphase; projizierter Lifecycle | KEEP | Historische Entwicklungszustände künstlich konservieren oder externe Testdaten ungesichert löschen | Bereits bedarfsgerecht; keine pauschale Upgradepflicht wieder einführen |
| AGENTS: TDD-Zusammenfassung und nachfolgende Test-first-/Spike-Wiederholung | MERGE | Fachliche Beweise vor Implementierung sichern; Detailablauf steht bereits im TDD-Skill | Redundante Ablaufzusammenfassung kürzen, Aktivierung und Beweisgrenzen behalten |
| AGENTS: nochmaliger Spike-Absatz unter der TDD-Regel | REMOVE | Bereits vollständig durch den ausdrücklich aktivierten TDD-Skill abgedeckt | Wiederholung entfernen, Skill samt Ausnahme- und Charakterisierungspflichten behalten |
| AGENTS: Negativtests, Coverage, synthetische Daten, Accessibility | KEEP | Verweigerte Pfade, Nebenwirkungen und UI-Barrieren übersehen | Eigenständige Qualitätsgrenzen behalten |
| Architektur: Schichten, kanonische Quellen, native Plattformmechanismen | KEEP | Parallelpersistenz, selbst erfundene Rechte-/Sessionmechanismen und vermischte Verantwortungen | Keine neue Abstraktion |
| Architektur: OrgSuite-Linkkonfiguration und BQ-Kalenderdetails | MOVE | Korrekte fachliche Regeln stehen bereits in den jeweiligen App-AGENTS | Im Root nur Integrationsownership und Verweis auf bestehende App-Quellen behalten |
| Security: Akteur/Scope, Allow/Deny, CSRF, SQL, Pfade, Fehlerdiagnose | KEEP | Unberechtigte Datenoperation, Injection, Pfadmissbrauch und unsichtbare Fehler | Keine Security-Prüfung entfernen |
| Privacy-by-Design: processing_id, Minimierung, Zweck, Retention, Entscheidungen | KEEP | Unbegründete Datensammlung, ungeklärte Fristen und kopierte Policywerte | Ergänzt Security; eine Zugriffskontrolle beweist keine Zweckbindung |
| Providerpflege in AGENTS und Privacy-Architektur | KEEP | Neue Nebenspeicher oder Rechte ohne Providerinventar ausliefern | Kurzregel aktiviert den ausführlichen Vertrag; keine zweite Feldliste daraus erzeugen |
| Privacy-Guide, Architektur, ADR 0002 | MERGE | Zielowner, technischer Vertrag und Rollout unterscheiden; aktuelle und historische Aussagen vermischen sich | Größere redaktionelle Konsolidierung erst mit eindeutigem Statusabgleich; aktuell behalten |
| ADR 0001: Kategorie A/B/C, Autoloading und Store-Grenzen | KEEP | Runtimeinterfaces bündeln, fremde PHP-Dateien produktiv laden oder unsichere Archive bauen | Trigger bleibt auf relevante App-Arbeit begrenzt |
| ADR 0003/0004: Matrixownership und temporärer Adminzugriff | KEEP | Matrix mit Privacy verschmelzen oder native Adminrolle zur dauerhaften Fachsuperrolle machen | Rollen- und Datenownership getrennt halten |
| LocalBase-Pilot/Retention und Standalone-V1 | KEEP | Noch konsumierte Integration als vermeintlich tote Altlast löschen | DP-01/FR-10 bleiben zuständiger kontrollierter Migrationsauftrag |
| Verify-/Delivery-/Compatibility-Skills | KEEP | Fast-Ergebnis als Release- oder Fresh-Install-Beweis ausgeben | Einen passenden umfassenden Einstieg wählen; verschiedene Runtimes separat belegen |
| Gemeinsamer App-Skill und lokale AGENTS-Zusatzregeln | MERGE | Gemeinsame Regeln sind teilweise erneut ausgeschrieben; autonome lokale Steuerung ist trotzdem erforderlich | Nur spätere ausdrücklich beauftragte App-Bereinigung; kanonische Projektionen nicht entfernen |
| Datenschutz-Center AGENTS, Definition of Done | MOVE | Verlangt zusätzlich Parent-Strukturprüfung trotz zugesagtem Einzel-Checkout ohne Parent | Lokales Routing bei nächstem App-Auftrag klären; Root nicht zur Laufzeitvoraussetzung erklären |
| Codex-Rollen und Skillselektoren | KEEP | Unbeauftragte Mutationen, Netz-/Agentenrekursion und unpassende Workflows | Keine Pflichtdelegation gefunden; keine Agenten gestartet |

Eine zusätzliche normative GAP-Regel ist nicht belegt: Die unten genannten
Lücken betreffen Umsetzung und technische Nachweise bereits geltender Regeln.

## Tests

| Test / Testgruppe | Geschützte Invariante | Klassifikation | Überschneidung / tatsächliche Grenze | Vorgeschlagene Aktion |
| --- | --- | --- | --- | --- |
| check-codex-structure + check-parent-governance-contract | Repositoryinventar, lokale Steuerung und exakte kanonische Projektion | KEEP | Strukturauflösung und Inhaltsgleichheit sind verschiedene Fehlerklassen | Beide behalten |
| check-apps: innerer Instruktions-/Skillcheck | Reguläre AGENTS, Skillname, Nicht-Ignorierung | REMOVE | Bereits stärker in check-workspace-structure geprüft; check-full übergibt diesen Nachweis | Nur diese Schleife entfernen; App-Suiten behalten |
| Markdown-Inventarfixture, Dokumentreferenzfixture, reale Dokumentprüfung | Prüfer erkennt Fehler; tatsächliche Dateien sind gültig | KEEP | Fixturetest und produktiver Scan haben verschiedene Inputs | Nicht zusammenstreichen |
| Architektur-/Privacy-/Portfolio-/Admin-Textchecks | Referenzen und markante Vertragsbestandteile fehlen nicht | WEAK-TEST | Schlüsselwörter beweisen weder Bedeutung noch wirksame Rechte; Negation/Kommentar kann grün bleiben | Als Dokument-Wiring behalten, nicht als Sicherheitsbeweis bezeichnen |
| privacy-processing-metadata-schema-contract | Wesentliche JSON-Schema-Bestandteile bleiben erhalten | KEEP | Prüft das Schema selbst, keine realen Kataloginstanzen | Nicht als vollständige Katalogvalidierung ausgeben |
| ProcessingMetadataCatalog + app-lokale Katalogtests + Root-Schema | Schema und Runtime akzeptieren denselben zulässigen Vertrag | GAP | PHP-Validierung unabhängig codiert; kein Schema-/Runtime-Differenztest im gelesenen Prüfeinstieg | G1, REVIEW-DECISION-REQUIRED |
| processing-metadata-provider-v1-contract | Reale Consumer passen zu echten DTOs/Eventregistry; inkompatible Provider isoliert | KEEP | App-Tests verwenden teilweise Stubs; zentrale Typidentität ist eigener Nachweis | Behalten |
| Derselbe Test: manuelle processingIds-Listen | App-eigene Verarbeitungsinventare vollständig | MERGE | Fachlisten stehen zusätzlich in App-Tests, z. B. Raumplaner | Später app-eigene Szenarien gegen echte Contracts verwenden; keine blinde Ableitung der erwarteten IDs aus dem Testobjekt |
| Derselbe Test: handle(new Event()) und fremde leere Registry | Fremdes Event hat keine Nebenwirkung | WEAK-TEST | Die geprüfte Registry wurde gar nicht an den Listener übergeben; belegt vor allem fehlenden Fatal Error | App-eigenen Side-Effect-Probe bei Listenerarbeit ergänzen |
| privacy-provider-v1-contract | Echte Interfaceidentität, Descriptor, unsupported Subject, Registry und Cursorfortschritt | KEEP | Konstruktorlose Instanzen und Repository-Fake beweisen weder DI noch SQL-Subjectfilter | Bewusst behalten, Beweisgrenze offen benennen |
| permission-provider-v1-contract | Reale Provider implementieren Matrix-V1 und liefern Regeln | KEEP | Nichtleere Regel und Operator sind kein Allow/Deny-Nachweis | App-lokale Policy-/Consumer- und Runtime-Rechtetests bleiben erforderlich |
| Datenschutz-Center: ProviderContractSemantics, DiscoveryCoverage, Aggregation | Cursorzuordnung, Seitengrenze, Fehlereingrenzung, erwartete Coverage | KEEP | Synthetische Provider testen den Aggregator; reale Consumer testen eine andere Grenze | Keine Zusammenlegung mit fachlichen Projektionstests |
| Lokale Privacy-/Permission-/Controller-Tests | Datenminimierung, Subjectfilter, Rechte und verbotene Nebenwirkungen | KEEP | Filterlogik, öffentlicher Output und authentifizierter Request sind verschiedene Grenzen | Keine pauschale Entfernung anhand gleicher Rollen-/Feldnamen |
| check-nextcloud-compatibility-runner: ungültige Majorfolge | Lückenlose Plattformmatrix | WEAK-TEST | Bisheriger Fall nutzt zugleich ein falsch bezeichnetes Archiv; scheitert bereits an version.php | Major-Mismatch und echte Lücke mit gültigen Archiven getrennt prüfen |
| Derselbe Runnervertrag: Pinning, Driverfehler und DDEV-Wiederherstellung | Exakte Quellen; sichere Rückgabe reservierter Registrierung | KEEP | Fake-DDEV prüft Orchestrierung, nicht Nextcloud-Installation | Behalten; kein aktueller Runtimebeleg |
| Compatibility-Driver: Fresh, DI, Jobs, HTTP/Assets, Rechteprobe | Tatsächliche Komponenteninteraktion nach Installation | KEEP | Nicht durch Unit-, Installer- oder occ-Statuschecks ersetzbar | Bestehenden Driver weiterverwenden; G2/G3 beachten |
| HTTP-/API-Smokes des Drivers | Erreichbarkeit und erwarteter HTTP-Status | WEAK-TEST | Kein Body-/Feldfilterbeweis und kein vollständiger visueller Accessibility-Test | Als Smoke behalten; keine pauschale Datenminimierungsbehauptung |
| check-privacy-app-compatibility | Physisch fehlende/inkompatible optionale Privacy-App ohne Fatal Error | KEEP | Mount-/Classloaderzustand ist stärker als deaktivierte App oder Fakeinterface | Nicht durch Fresh Install ersetzen |
| Delivery RUN_DDEV_CHECKS | Instanz-/Appstatus | KEEP | Kein leeres Schema, keine Reinstallation | Nicht als Reinstallbeleg zählen |
| Integrations-/Rechtematrix-Smokes | Echte DB-, Job-, DAV-, Rollen- und Requestinteraktion | KEEP | Ergänzen reine Unit-/Providercontracts | Nicht automatisch für jede Dokumentänderung starten |
| Urlaub MigrationSchemaSmoke | Schema/Indizes/Integrität und historische Datenübernahme | KEEP | Fresh-Anteil bleibt relevant; Altdatenanteil muss nach Lifecycle separat bewertet werden | Keine ganze Suite als Legacy entfernen |
| Installer-/Paket-/Archiv-/Staging-/ACL-Verträge | Abhängigkeiten, saubere Artefakte, reproduzierbare Archive, Installation und Unix-Zugriff | KEEP | Synthetische Paket-/ACL-Inputs sind keine Provider- oder DB-Nachweise | Bestehenden Parent-Fast-Pfad verwenden |
| PHP-/JS-Coverage, unterschiedliche CI-Runtimes | Ausführungsabdeckung, Runtimeunterschiede, Consumer gegen neuen Providerstand | KEEP | Keine zweite identische Phase belegt; Coverage ersetzt keine Assertion | Keine alten Reports ohne passende Quellenprovenienz wiederverwenden |

## Echte Lücken und konkrete Grenzen

**G1 – Schema-/Runtime-Drift, reproduziert.** Im Root-Schema verlangt
`$defs.processing.properties.recipients.oneOf[0].uniqueItems` eindeutige
Empfängerobjekte. Die reale Klasse
`filzmann_data_protection/lib/PublicApi/V1/ProcessingMetadataCatalog.php`
akzeptiert zwei identische synthetische Empfängerobjekte. Ein CLI-Aufruf mit
dem Raumplanerkatalog als neutraler Strukturvorlage und ersetzt synthetischen
Empfängern bestätigt dies ohne Dateiänderung. Der Root-Test prüft ausgewählte
Schemafelder, der zentrale Provider-Test nutzt den PHP-Validator; damit fehlt
gerade der Abgleich dieser beiden Wahrheiten. Kein nachgewiesener Datenabfluss,
aber ein konkreter öffentlicher Contractdefekt. Die bestehende technische
Root-Ownership-Regel reicht; eine weitere allgemeine Regel würde nichts lösen.

**G2 – Fresh-Install-Aufnahme unvollständig.** Der Driver verlangt in
`validate_app_smoke_contracts` für jede App außer LocalBase eine lokale Datei
namens `nextcloud-compatibility-smoke.php`. Sie fehlt bei BRTop, BRStunden und
dem Datenschutz-Center. Eine aktuelle vollständige Zwölf-App-Matrix würde
vor der Installation stoppen. LocalBase ist ausdrücklich ausgenommen und
wird deshalb nicht allein wegen der fehlenden Datei als Lücke gezählt.
Der historische NC-34-/SQLite-Lauf vom 5. September ist kein Ersatz für
diese aktuelle Runnerabdeckung.

**G3 – Providerregistrierung nach Fresh Install nicht explizit nachgewiesen.**
Die Driverphase `registrations` löst XML-Jobs, Kommandos und Settings auf.
Sie dispatcht keine Privacy-/Processing-/Permission-Registry und prüft deren
erwartete Provider nicht. Die gelesenen App-Smokes prüfen Zugriff/UI/APIstatus.
Die zentralen Contract-Tests registrieren Provider manuell oder rufen
Listener direkt auf. Ein fehlendes Bootstrapping des echten Nextcloud-
Eventlisteners kann dadurch unentdeckt bleiben. Die Ergänzung gehört in den
bestehenden Runtimepfad, mit app-eigenen Erwartungen, nicht in eine neue
globale Reinstallpipeline.

**G4 – Bereits bekannte fachliche Vollständigkeitslücken bleiben offen.**
DP-01/FR-10 (Retention-Ownerwechsel), DP-03 (LocalBase-Personenwerte), DP-04
(Adminhistorie-Retention), DP-06/DP-08 (Abdeckung und Katalogrollout) sowie
externe Subjectidentifikation stehen bereits im Zukunftsplan bzw. der
Privacy-Migrationsmatrix. Sie sind keine neu entdeckten Implementierungsfehler
dieses Audits. `partial`, `missing`, `UNKNOWN`, `UNSUPPORTED` und
`PRIVACY-DECISION-REQUIRED` dürfen deshalb nicht wegoptimiert werden.

## Größte Ballastquellen und Kosten

1. **Kontextmenge:** 928 Zeilen Privacy-Architektur mit Normen, Pilotgeschichte,
   detaillierten App-Inventaren und Migrationsstand. Zusammen mit Guide,
   ADRs und lokalen Regeln entsteht wiederholter Lesebedarf. Maßgebliche
   Abschnitte gezielt lesen; nicht bei jeder App-Änderung das gesamte Dokument.
2. **Breiter Parent-Fast-Pfad:** scannt alle Repositorywurzeln und Markdown-
   Dateien, führt viele Git-Abfragen und Paket-/Staging-Fixtures aus.
   Das globale `glob('**/.git')` entdeckt auch unregistrierte Repositories;
   nur Manifestpfade zu prüfen würde diese Fehlerklasse verlieren.
3. **Belegte lokale Doppelarbeit:** check-apps wiederholt nach der Struktur
   zwölf Subshells, 36 grep-Aufrufe und 24 git-check-ignore-Aufrufe bei den
   heutigen zwölf Apps mit je zwei Skills. Diese Wiederholung kann entfallen.
4. **Fachinventare im Root-Test:** neun Processing-ID-Listen benötigen bei
   Appänderungen zusätzliche Rootpflege. Migration auf app-eigene öffentliche
   Testszenarien braucht einen zusammenhängenden Auftrag; automatische
   Sollwertableitung aus Istwerten würde die Beweiskraft verringern.
5. **Schon behobene Doppelarbeit:** check-full überspringt bereits die zweite
   vollständige Strukturprüfung; Delivery startet Fast einmal und verwendet
   den geprüften Paketbau. Keine erneute Einsparung dafür behaupten.

## Risikobewertung und Entscheidungen

| Entscheidung | Problem / aktueller Zustand | Vorschlag und Nutzen | Risiko / Alternative |
| --- | --- | --- | --- |
| REVIEW-DECISION-REQUIRED: Schema und Runtime | G1: zwei nicht gekoppelte Validatoren | Root-Schema bleibt kanonisch; im benannten Auftrag für Parent und Datenschutz-Center vorhandene Contracttests mit gemeinsam geprüften positiven/negativen Fällen verbinden, Runtime korrigieren | Öffentliche Akzeptanzgrenze; weitere legitime V1-Fälle berücksichtigen. Alternative: zunächst Differenzcharakterisierung ohne Runtimeänderung; keine Schemaabschwächung |
| REVIEW-DECISION-REQUIRED: Runtimeabdeckung | G2/G3: drei fehlende App-Smokes und keine explizite Fresh-Registry-Prüfung | Bestehenden Driver mit app-lokalen Probes und Erwartungen erweitern; dieselbe leere Installation nutzen | App-Aufträge sowie konkrete isolierte DDEV-Matrix und Rückgabe der Registrierung erforderlich. Alternative: engere belegte Appmatrix, ausdrücklich kein Vollständigkeitsurteil |
| REVIEW-DECISION-REQUIRED: zentrale Fachlisten | Root-Tests kennen processingIds und private Testkonstruktoren | App-eigene Szenarien gegen echte öffentliche Contracts ausführen | Wegfall unabhängiger Erwartungen kann Tests tautologisch machen. Alternative: vorhandene Listen vorerst behalten |
| REVIEW-DECISION-REQUIRED: breite Harnesskürzung | Normen, Appprojektionen, Keywordtests und Statusgeschichte hängen zusammen | Später Norm/Projektion/Status gezielt konsolidieren; nur kontextbezogen laden | Bloße Links brechen Einzel-Checkout; Keywordentfernung kann Routing verlieren. Alternative: kleine Rootkürzung dieses Audits |

Die risikoarmen Vorschläge verändern weder öffentliche Runtimecontracts noch
Berechtigungen, Datenbestand oder Installationszustand. Rückbau erfolgt durch
Zurücknehmen der einzelnen Parent-Diffs. Keine großflächige Testlöschung.

## Umsetzung und Abschlussvalidierung

### Tatsächlich geändert

| Datei | Ergebnis |
| --- | --- |
| [AGENTS.md](../AGENTS.md) | 15 Zeilen weniger: TDD-Ablauf-/Spike-Wiederholungen im vorhandenen Skill zusammengeführt; Aktivierung, Beweisgrenzen, Negativfälle und Coveragepflicht bleiben |
| [architecture.md](architecture.md) | 6 Zeilen weniger: OrgSuite-/BQ-Fachdetails auf bereits vorhandene App-Regeln verwiesen; öffentliche Integrationsownership bleibt im Root |
| [check-apps](../scripts/check-apps) | 14 Zeilen weniger: redundante lokale Instruktionsschleife entfernt; der reguläre Einstieg prüft weiterhin zuerst die umfassende Struktur, check-full übernimmt seinen unmittelbar davor geprüften Fast-Nachweis |
| [check-nextcloud-compatibility-runner.sh](../tests/check-nextcloud-compatibility-runner.sh) | Vorhandenen Test verbessert: gültige 33-/35-Archive erzwingen den Lückenfall, getrennter Archiv-Mismatch, konkrete Fehlerursache und ausbleibender Driveraufruf geprüft |
| [workspace.md](workspace.md) | Bericht im vorhandenen Kosten-/Prüfindex eingeordnet |
| Dieser Bericht | Audit vor Bereinigung, Entscheidungen und tatsächliche Ergebnisse |

Keine Appdateien, Skills, Governance-Projektionen, öffentlichen APIs,
Runtime-Provider, Daten oder DDEV-Konfigurationen wurden verändert. Keine
fachliche Testsuite wurde entfernt. Gegenüber dem Ausgangsstand sind die
geänderten bestehenden Regeln/Prüfer netto um 17 Zeilen kürzer; der separate
Auditbericht und sein sechszeiliger Indexeintrag kommen als Dokumentation
hinzu. Das Repository insgesamt wird dadurch nicht kleiner. Die laufenden
Instruktionen werden kleiner und die Testaussage wird stärker.

### Beweisführung und Tests

Invariante der Testverbesserung: Nur eine lückenlose Majorfolge aus jeweils
gültigen Archiven darf Runtime-Stufen starten. Testebene: vorhandener
Shell-Orchestrierungsvertrag mit synthetischen Archiven und Fake-Driver.
Er beweist keine reale Nextcloud-Kompatibilität und keine HTTPS-/UI-Abnahme.

Der Skill `test-driven-change` wurde angewandt. Begründete Abweichung: keine
Produktiv-Verhaltensänderung; Regelkürzung und Entfernen einer vollständig
überdeckten Prüfschleife sind mechanische Bereinigung. Der verstärkte Test
charakterisiert eine bestehende korrekte Runner-Invariante. Ein künstlicher
Produktivdefekt wurde ausschließlich in einer temporären Kopie erzeugt:

1. In der Kopie von `scripts/verify-nextcloud-compatibility` ausschließlich
   den dreizeiligen Guard für `major != previous_major + 1` entfernt.
2. Alten `tests/check-nextcloud-compatibility-runner.sh` dagegen ausgeführt:
   Exit 0, obwohl die Lückensperre fehlte; ursprüngliche Testschwäche belegt.
3. Verbesserten Test gegen dieselbe Kopie ausgeführt: Exit 1 mit
   `A non-consecutive server major was accepted.`; zusätzliche Fehlerklasse
   zuverlässig erkannt.
4. `bash tests/check-nextcloud-compatibility-runner.sh` gegen den unveränderten
   echten Runner: Exit 0. Keine Produktivimplementierung erforderlich.

| Aufruf / Nachweis | Ergebnis |
| --- | --- |
| `scripts/check-full` | Exit 1 durch bekannte UID-Sandbox-Grenze: `setfacl: Invalid argument`. Zuvor Struktur, Governance, Dokumente, Privacy-/Admin-/Portfolio-, Coverage-Baseline-, CI-, Installer-, Compatibility-, Pruning- und Archivcontracts grün |
| `bash tests/check-teamcloud-staging-gate.sh` mit gezielter Sandbox-Eskalation | Exit 0; unveränderter Zwei-UID-ACL-Vertrag bestanden |
| `bash tests/check-staging-deployment.sh` | Exit 0; verbleibender Parent-Test mit synthetischem Staging, kein externes Deployment |
| `scripts/check-apps --skip-structure` | Exit 0; Struktur des unmittelbar vorherigen Full-Laufs übernommen; PHP und JavaScript aller zwölf Apps sowie Permission-, Privacy-, Processing-Metadata- und Organisationscontracts grün |
| Schema-/Runtime-Gegenbeispiel G1 | Root verlangt eindeutige Empfänger; reale PHP-Klasse akzeptiert das synthetische Duplikat. Offener Befund, kein grünes Konformitätsurteil |

Der vollständige lokale Prüfumfang wurde damit aus dem abgebrochenen Wrapper
und den gezielt fortgesetzten Teilprüfungen zusammengesetzt. Es wird kein
ununterbrochen grüner `check-full`-Aufruf behauptet. Gleiche bereits grüne
Teilprüfungen wurden nach dem Sandboxfehler nicht nochmals gestartet.
Runtime dieses Laufs: PHP 8.5.4 CLI, Node 24.18.0. Lokale Logs liegen unter
`/tmp/harness-audit-20260910-check-full.log`,
`/tmp/harness-audit-20260910-staging.log` und
`/tmp/harness-audit-20260910-apps.log`; Mutationsevidenz unter
`/tmp/harness-major-mutation-lxg1ixyd/`. Diese temporären Artefakte sind keine
dauerhafte oder plattformübergreifende Releaseevidenz.

Nach Ergänzung dieses Berichts bestanden die gezielte Prüfung mit
`scripts/check-document-references docs/harness-audit-2026-09-09.md`, die
Kontrolle seiner relativen Links/Codefences und `git diff --check`.
Alle 13 Subrepositories sind weiterhin sauber. Die vollständige Parent-
Änderungsliste umfasst die sechs oben genannten Dateien, davon der neue
Bericht ungetrackt. Kein neuer Coverage-Lauf: keine neue App-Logik;
bestehende Coverage-Baseline-Contracts liefen im Parentpfad.

Reale DDEV-/HTTP-/Permission-/Integrations-Smokes und die Fresh-Install-Matrix
wurden nicht gestartet: keine Runtimeänderung im Auftrag, keine konkret
ausgewählte Installationsmatrix, zudem G2/G3. Die bestehenden Pfade wurden
auf ihre Beweiskraft geprüft; frühere Runtimeberichte werden nicht als
aktuelle Ausführung ausgegeben. Runtime insgesamt **nicht vollständig
verifiziert**. Kein Release- oder Deploymenturteil.

### Antworten auf die Abschlussfragen

1. **Harness kleiner oder klarer?** Beides: 21 Zeilen weniger in Root-Regeln
   und Architektur; klare Grenze zwischen Contract-, Struktur- und Runtimebeweis.
2. **Jede wesentliche Regel eigenständig?** Die beibehaltenen Regelgruppen
   haben einen benannten Zweck. Weitere redaktionelle Wiederholungen zwischen
   Privacy-Architektur, Guide und App-Steuerung bleiben bewusst sichtbar;
   keine pauschale Behauptung vollständiger Redundanzfreiheit.
3. **Jeder wesentliche Test mit Fehlerklasse?** Für die auditierten Gruppen
   ist sie zugeordnet. Keywordchecks, HTTPstatus und manuelle Registrierung
   bleiben begrenzte Nachweise; nicht jede fachliche Assertion wurde neu geprüft.
4. **Weitere unnötige Mehrfachprüfungen?** Die konkrete check-apps-Schleife
   entfällt. Root-Fachlisten und redaktionelle Mehrfachpflege bleiben;
   Unit-/Contract-/Runtime-Ebenen werden nicht als Doppelung entfernt.
5. **Grenzen konsistent?** Ownership, Security, optionaler Providerbetrieb
   und App-Trennung bleiben erhalten. Vollständige Contractkonsistenz wird
   wegen der reproduzierten Schema-/Runtime-Abweichung G1 nicht behauptet.
6. **Echte Schutzlücken verblieben?** Ja: G1 sowie die Nachweislücken G2/G3;
   außerdem die bereits dokumentierten offenen Verarbeitungs-/Retentionfragen.

Keine neuen Learning Candidates oder dauerhaften Zusatzregeln angelegt.
Die expliziten Reviewentscheidungen stehen oben; laufende fachliche Aufgaben
bleiben in den bestehenden zuständigen Planungsquellen. Kein Commit, Push,
Release oder Deployment. Commit-Freigabe bleibt bei Simon.
