# AGENTS.md – BR Nextcloud Apps

## Zweck und Reichweite

Dieses Repository ist der Parent-/Meta-Workspace für die lokale Nextcloud-Entwicklungsumgebung und gemeinsame Dokumentation eigener Nextcloud-Apps. Es enthält keinen deploybaren App-Code.

- Projektwurzel: `~/projects/br-nextcloud-apps`
- DDEV-/Nextcloud-Projekt: `~/projects/br-nextcloud-apps/nextcloud-dev`
- Human-lesbare Workspace-, App-, URL- und Mount-Dokumentation: `docs/workspace.md`
- Wiederkehrende Abläufe: `.agents/skills/`

Diese Regeln gelten im Parent. Jedes App-Verzeichnis ist ein eigenes Git-Repository mit eigener vollständiger `AGENTS.md` und lokal auflösbaren Skills. Bei einem direkten Start im App-Root wird diese Parent-Datei nicht als geladen vorausgesetzt. Normale Implementierungsarbeit beginnt deshalb im Root des konkret beauftragten App-Repositories; der Parent dient der Analyse, Koordination und Release-/Workspace-Prüfung. Der technische `workspace-write`-Zugriff erteilt keine fachliche Schreibfreigabe. Schreibende Cross-App-Arbeit ist ein ausdrücklich benannter Sonderlauf mit einzeln genannten Repositories, Regeln, Statusprüfungen und Tests.

## Repository-Grenzen

- Im Parent werden nur Meta-Dokumentation, DDEV-/Workspace-Konfiguration, app-übergreifende Regeln, Delivery-Skripte und zugehörige Tests gepflegt.
- App-Code aus `brtop/`, `adplaner/`, `brstunden/`, `localbase/`, `br_permission_matrix/`, `adcalendar/`, `adurlaub/`, `orgsuite/` und `adroom/` sowie Produktdokumentation aus `ad-suite/` wird im Parent weder geändert noch getrackt, gestaged oder committed, außer Simon beauftragt das konkrete getrennte Repository ausdrücklich.
- Vor App-Arbeit in das App-Repo wechseln, dessen `AGENTS.md` und lokal referenzierte Skills vollständig lesen und dort den Git-Status prüfen. `.gitignore` ist nur Repo-Trennung und kein Schreibschutz.
- Jede deploybare App erhält ein eigenes Git-Repository, eine eigene `AGENTS.md` und `.gitignore`. Keine Submodule, solange Simon sie nicht ausdrücklich entscheidet.
- Neue App-Repositories werden mit dem Skill `create-nextcloud-app` angelegt. Im Parent entstehen dabei nur Manifest-/Workspace-Registrierung, Ignorierregel, DDEV-Mount und gemeinsame Dokumentation.
- Gemeinsamer Code wird erst extrahiert, wenn mindestens zwei Apps ihn semantisch gleich benötigen und der Vertrag app-übergreifend testbar ist. `localbase` bleibt klein und dependency-arm; öffentliche LocalBase-Verträge sind Cross-App-Verträge.

## Dauerhafte Architekturregeln

- Controller bleiben dünn. Fachlogik, Datenzugriff, Darstellung, Dokumenterzeugung und Dateiablage bleiben getrennt.
- Datenzugriffe laufen über Repository-, Mapper-, Store- oder Service-Klassen. Services arbeiten bevorzugt mit Modellen/DTOs statt unstrukturierten Arrays.
- Modelle/DTOs verwenden app-übergreifend `get(...)` für einzelne Payloads/Rows/Objekte, `get_all([...])` für Listen und `toArray()` für Serialisierung. `save()` ist persistenten, Store-gebundenen Modellen vorbehalten; nicht persistierbare DTOs blockieren es mit klarer Fehlermeldung. `fromArray`/`fromRow` bleiben nötigenfalls interne/protected Hydrationsdetails. Neue `fromApi`-/`toApi`-Kompatibilitätsaliase sind unzulässig; bestehende PHP-`toApiArray()`-Call-sites dürfen beim ohnehin nötigen Anfassen der Schicht schrittweise auf `toArray()` migriert werden.
- JavaScript trennt API-Adapter/Repositories, Modelle/ViewModels, Workflows und Rendering/Eventbindung. Größere Templates werden in Partials zerlegt.
- DRY und KISS gelten gemeinsam. Keine vorsorgliche Abstraktion, Library oder Design-System-Schicht. Eine gemeinsame UI-Komponente braucht mindestens zwei semantisch gleiche Verwendungen einschließlich Zuständen, Events und Accessibility-Vertrag; bis dahin werden nur kleine stabile Helfer ausgelagert. Jede Auslagerung braucht konkrete Duplizierung, bessere Testbarkeit oder Wartbarkeit.
- Kommentare erklären Zweck, Zusammenspiel, Spiegelung oder Vertrag, wenn Namen und Typen nicht genügen. Bei gespiegeltem PHP-/JavaScript-Verhalten nennen beide Stellen das exakte Gegenstück und den identisch zu haltenden Vertrag. Kommentare wiederholen keine Syntax und werden mit Verhaltensänderungen gepflegt.
- Fehler werden zentral protokolliert; Nutzer*innen erhalten sichere, knappe Meldungen ohne interne Details.
- WordPress-spezifische APIs, Namenskonventionen und Kompatibilitätsschichten gehören nicht in diese Nextcloud-Apps. Produktive oder fremde Demo-Daten werden nicht übernommen.

### Suite- und Produktverträge

- `orgsuite` besitzt die gemeinsamen AD-/BR-Menüs und Einstiegsweiterleitungen. Fachapps duplizieren keine Suite-Linklisten; Navigation erteilt niemals fachliche Rechte.
- Die AD-Fachprodukte `adcalendar`, `adplaner`, `adurlaub` und `adroom` bleiben einzeln installierbar. `localbase` und `orgsuite` sind Infrastruktur, keine eigenständigen Fachprodukte.
- Jedes AD-Fachprodukt wird mit einer kompatiblen LocalBase-Version geliefert. Nextcloud 34 installiert keine App-Abhängigkeiten aus erfundenen `<app>`-Elementen in `info.xml`.
- Bei genau einem aktiven AD-Fachprodukt bleibt OrgSuite deaktiviert und das Fachprodukt stellt den Einstieg. Ab zwei Fachprodukten aktiviert der geprüfte Installer OrgSuite.
- Fachapps registrieren einen eigenen Nextcloud-Hauptnavigationseintrag nur im ausdrücklich vorgesehenen Standalone-Zustand ohne aktive OrgSuite. Neben einer aktiven OrgSuite registrieren sie keinen zusätzlichen Hauptnavigationseintrag; BR-Fachapps verwenden ausschließlich den gemeinsamen OrgSuite-Einstieg.
- Fachapps greifen nicht direkt auf Tabellen, Controller oder JavaScript-Assets anderer Fachapps zu. Optionale Integrationen verwenden kleine LocalBase-Events beziehungsweise Capability-Verträge; ein fehlender Provider ist ein gültiger Standalone-Zustand.
- Capability-Verfügbarkeit und Menüsichtbarkeit erweitern niemals Berechtigungen. Anbieter und Zielcontroller prüfen jeden Zugriff serverseitig.
- Produktarchive enthalten nur das gewählte Fachprodukt und kompatible Infrastruktur; der vollständige Suite-Build darf alle Produkte bündeln.

## Rechte und Sicherheit

- Deny by default, Least privilege und server-side first sind harte Anforderungen.
- Nextcloud-native Gruppen-, Benutzer-, Session-, AppConfig-, Share-, Datei-, Capability-, Konfigurations- und Request-Mechanismen sind verbindlich zu verwenden. Ein paralleler eigener Mechanismus ist nur zulässig, wenn die native Möglichkeit nachweislich nicht ausreicht, die Abweichung als begründete Architekturentscheidung dokumentiert ist, Auswirkungen auf Berechtigungen, Migration, Wartung und Interoperabilität geprüft sind und die Entscheidung vor der Implementierung freigegeben wurde. Fehlt eine dieser Voraussetzungen, muss Codex vor der Implementierung stoppen.
- Jeder relevante Controller, API-Endpunkt, Servicepfad sowie jede Datei- und Datenoperation prüft den Akteur, Scope und die konkrete Berechtigung serverseitig. UI-Ausblendung ist nur Komfort.
- Rechteprüfungen werden zentral in Permission-, Access-, Policy- oder Capability-Services gebündelt. Repositories und Services liefern keine unbeschränkten Daten, wenn der Aufrufkontext eingeschränkt ist.
- Nextcloud-Admin, App-Admin, Gruppenmitglied, normale Nutzer*innen, Read-only-/Bearbeitungsrolle und Hintergrundaufgabe werden nicht gleichgesetzt.
- Temporäre lokale Rechtevereinfachungen sind nur als ausdrücklich benannte Vor-Production-Grenze zulässig und dürfen eine spätere granulare Rechtearchitektur nicht verbauen.
- Requests werden validiert und typisiert, QueryBuilder-Werte gebunden, Ausgaben escaped und schreibende API-Aktionen per CSRF geschützt. `NoCSRFRequired` braucht eine bewusste Begründung.
- Aus Request-Daten werden keine SQL-Fragmente zusammengesetzt.
- Dateipfade werden normalisiert und nie ungeprüft aus Eingaben zusammengesetzt. Keine Secrets oder unnötigen personenbezogenen Daten in Repository, Logs oder Dokumenten.
- Sicherheitsrelevante Tests decken Allow-, Deny- und direkte unberechtigte API-Aufrufe ab.

Bei jeder neuen Funktion müssen Sichtbarkeit, Ausführung, Lese-/Schreibscope, relevante Gruppen/Rollen/Shares/Capabilities, serverseitige Erzwingung und positive wie negative Tests geklärt sein.

## UI und Accessibility

- Deutsche Benutzertexte verwenden echte Umlaute und `ß`; technische IDs, URLs und Maschinenverträge bleiben unverändert.
- Oberflächen verwenden semantisches HTML, vollständige Tastaturbedienung, sichtbare Fokuszustände, sprechende Labels, verständliche Fehler und keine ausschließlich farbliche oder Hover-basierte Bedeutung.
- Persönliche Einstellungen liegen in navigierbaren Fachapps ausschließlich in einem eigenen semantischen Tab `Einstellungen`; dauerhafte App-, Gruppen-, Rechte- und Standardwerte werden nicht in Hauptansichten oder fachfremden Dialogen versteckt. App-spezifische Administration liegt im Adminabschnitt der Fachapp, app-übergreifende Organisationskonfiguration im zuständigen Suite-Adminabschnitt. Kontextuelle Kleinstoptionen dürfen direkt an ihrer einzelnen Aktion liegen. Endpunkte bleiben unabhängig von UI-Sichtbarkeit serverseitig geschützt.
- Tab-Oberflächen verwenden konsistent `role="tablist"`, `role="tab"` und `role="tabpanel"`, eindeutige `aria-controls`-/`aria-labelledby`-Beziehungen, gepflegte `aria-selected`-Zustände, Tastaturaktivierung und sichtbaren Fokus.
- Der direkte App-Root ist der vertikale Scrollcontainer mit `height: 100%`, `min-height: 0`, `overflow-y: auto`, `box-sizing: border-box` und deckendem Nextcloud-Hintergrund. Breite Tabellen scrollen horizontal nur in einem inneren Wrapper und dürfen die verfügbare App-Breite ohne künstliches globales `max-width` nutzen; je nach Inhalt gelten `width: 100%`, `min-width: 100%` oder `width: max-content`. Flex-/Grid-Kinder erhalten erforderliches `min-height: 0` beziehungsweise `min-width: 0`.
- Apps überschreiben weder `body` noch globale Nextcloud-Core-Selektoren.
- Neue Apps und größere UI-/Layoutänderungen benötigen passende Accessibility-, Tab- und Scroll-Smokes. Nicht geprüfte Punkte werden offen als nicht vollständig verifiziert gemeldet.

## Test- und Qualitätsregeln

- Neue Entwicklung und Bugfixes folgen grundsätzlich Rot – Grün – Refactor. Bugfixes beginnen mit einem Regressionstest; Refactorings mit Charakterisierungstests.
- Ausnahmen vom Test-first-Einstieg sind zeitlich begrenzte explorative Spikes, rein deklarative Texte/Metadaten/triviale Darstellungsänderungen und schwer isolierbare Nextcloud-Integration, bei der ein gröberer Integrationstest den realen Vertrag zuverlässiger abbildet. Spike-Code wird verworfen oder vor der Übernahme charakterisiert; deklarative Änderungen erhalten passende Syntax-, Contract-, Layout- oder Sichtprüfungen.
- Fachlogik, Berechtigungen, Hierarchien, Konflikte und Validierungen werden test-first entwickelt. API-Änderungen prüfen Erfolg, Validierungsfehler und typische Allow-/Deny-Fälle.
- Ausführbare UI-Logik wird test-first entwickelt; Layout, Accessibility und Nextcloud-Integration werden zusätzlich durch passende Smoke- oder Browsertests abgesichert.
- App-übergreifende Verträge werden auf beiden Seiten durch Contract-Tests abgesichert. Migrationen und Repository-Verhalten erhalten Integrations-/DDEV-Tests, wenn Unit-Tests den realen Vertrag nicht abdecken.
- Schnelle App-Einstiege sind `php tests/run.php` und `node tests/run-js.mjs`. Dependency-arme PHP-Smokes laufen isoliert in getrennten Prozessen.
- Nach LocalBase-Änderungen laufen LocalBase-Tests und alle betroffenen App-Contract-/Smoke-Tests.
- Gemeinsame Test-Helper werden erst eingeführt, wenn mindestens zwei Repositories dieselben Assertions, Fakes, Fixtures oder Setup-Schritte semantisch gleich benötigen. Sie bleiben klein, dependency-arm, test-only und werden in LocalBase selbst getestet.
- In der lokalen Vor-Production dürfen App-Tests gemeinsame LocalBase-Test-Helper pragmatisch über relative Repo-Pfade nutzen. Eine schwerere Packaging-/Autoload-Struktur oder ein größeres Testframework wird erst eingeführt, wenn Pfade, Runner, Assertions, Mocks oder Fixtures spürbar dupliziert werden oder die Lesbarkeit beeinträchtigen.
- Bekannte Gesamt- und App-Coverage darf nicht unbemerkt sinken. Coverage ist ein Warn- und Lieferindikator, ersetzt aber keine fachlich belastbaren Assertions.
- Für neuen oder wesentlich geänderten ausführbaren Code werden mindestens 85 Prozent Line-Coverage angestrebt. PHP und JavaScript werden getrennt ausgewiesen; Sicherheitsinvarianten sind unabhängig von Prozentwerten vollständig abzudecken.
- Testdaten, Fixtures, Screenshots, Logs, Beispiele und Dokumentation sind synthetisch, neutral und datenschutzarm; echte Beschäftigten-, BR-, Kund*innen-, Mail-, Gesundheits-, Konflikt-, Beschluss- oder interne Dokumentdaten sind unzulässig. Tests dürfen keine ungeklärte Fachentscheidung stillschweigend festschreiben.
- Die Auswahl des schnellen oder vollständigen Parent-Prüfwegs erfolgt mit dem Skill `verify-workspace`.

## DDEV, Installation und Delivery

- DDEV wird ausschließlich aus `nextcloud-dev` gesteuert. Lokale PHP-/Node-Prüfungen werden bevorzugt und DDEV-Prüfungen gebündelt.
- DDEV-Pfade, DDEV-Benutzer, Containerpfade, PHP-Binaries, Datenbankzugänge und sonstige lokale Annahmen dürfen niemals auf eine Produktiv- oder Hostingumgebung übertragen werden. Produktionspfade, Benutzer, `apps_paths`, PHP-Binary und CLI-Memory-Limit werden in der Zielumgebung separat ermittelt. Ein Wechsel zwischen DDEV und Produktion ist eine Umgebungsgrenze und muss ausdrücklich benannt werden. Bei unklarer Zielumgebung muss Codex stoppen.
- Docker-/Stream-FD-Fehler im normalen Codex-Sandboxkontext sind kein App- oder DDEV-Projektfehler. Rein lesende Diagnose- und Testbefehle dürfen mit eng begrenztem eskaliertem Zugriff wiederholt werden.
- Zustandsändernde Befehle wie `ddev start|stop|restart`, `occ app:enable`, `occ upgrade`, Migrationen, Installationen oder Bereinigungen benötigen einen konkreten Auftrag oder eine ausdrückliche Freigabe.
- Nextcloud 34 besitzt lokal keinen `occ migrations:migrate`-Befehl. App-Migrationen laufen über `occ app:enable <app-id>` beziehungsweise `occ upgrade`, wenn `needsDbUpgrade: true` gemeldet wird.
- Bei Hosting-Panels werden Nextcloud-Root, Domainbenutzer, Static-Webserver-Kontext und CLI-PHP aus der realen Konfiguration ermittelt. Keine ungeprüften Annahmen wie `/var/www/nextcloud`, `www-data` oder System-`php`.
- Das tatsächlich verwendete CLI-PHP und sein für `occ`, Installation und Migration ausreichendes CLI-Memory-Limit werden separat geprüft.
- Die realen Nextcloud-`apps_paths` werden geprüft: Core-Pfade bleiben read-only, der vorgesehene `custom_apps`-Pfad ist für den erforderlichen Runtime-/CLI-Kontext writable, und konfigurierte Pfade werden nicht stillschweigend ersetzt.
- Separat wird geprüft, dass der PHP-FPM-/Domainbenutzer nur erforderliche Pfade schreiben kann und der Static-Webserver Assets lesen sowie nötige Verzeichnisse traversieren kann. Ein HTTPS-Asset-`403` wird anhand realer Pfade, Besitzer, Gruppen, Traversal- und Serverkontexte diagnostiziert statt durch pauschal breitere Rechte kaschiert.
- Berechtigungsprobleme werden mit dem kleinsten passenden Besitzer-/Gruppen-/Moduswechsel behoben; niemals pauschal mit `chmod 777`.
- Eine Installation gilt erst als geliefert, wenn neben `occ`-Status und Migration mindestens je ein CSS-/JavaScript-Asset im Static-Webserver-Kontext und über HTTPS mit richtigem Content-Type sowie die sichtbare Oberfläche geprüft wurden.
- Kein Release erfolgt mit rotem Delivery-Gate.
- AD-Suite-Bau und -Abnahme erfolgen ausschließlich über den Skill `build-ad-suite-release` und die vorhandenen Delivery-Skripte.

## Stop-Regeln

Vor der Umsetzung stoppen, Risiko, betroffene Dateien, Tests und Rückbauweg nennen und Freigabe einholen, wenn der konkrete Auftrag die Änderung nicht bereits ausdrücklich umfasst:

- Datenbankschema, Migrationen oder bestehende produktive Daten;
- Berechtigungen, Gruppenlogik, Rollen, CSRF, Authentifizierung oder Zugriffsschutz;
- öffentliche LocalBase-Verträge oder mehrere App-Repositories;
- Dateiablage, Dateipfade, Uploads, Downloads oder Dokumenterzeugung;
- Löschung, Umbenennung oder Verschiebung größerer Codebereiche;
- DDEV-, Docker-, Nextcloud- oder `occ`-Konfiguration;
- breite Refactorings zur Reparatur roter Tests;
- der Rückbauweg ist unklar.

Ohne Freigabe sind in diesen Fällen nur Lesen, Analyse und ein minimaler Änderungsplan erlaubt. Auch mit Freigabe bleiben Änderungen klein, testbar und rückbaubar.

Sofort stoppen, wenn Produktionssysteme, unerwartet erforderliche externe Dienste, Git-Historienumschreibung, neue Produktionsdependencies, Änderungen außerhalb des beauftragten Repositories oder der Verlust fachlich relevanter Regeln erforderlich würden. Ein ausdrücklich beauftragter Zugriff auf ein benanntes Staging-/Hosting-System ist nicht unerwartet, bleibt aber auf den genehmigten System-, Operations- und Credential-Scope begrenzt.

## Codex-Konfiguration und Delegation

- `.codex/config.toml` enthält ausschließlich Codex-Einstellungen. Fachliche Regeln stehen hier, Abläufe in Skills.
- Der Parent startet technisch mit `sandbox_mode = "workspace-write"` und `approval_policy = "on-request"`, damit ausdrücklich beauftragte Änderungen sowie gezieltes Staging und Committen innerhalb des Workspaces möglich sind. Der technische Schreibzugriff ersetzt weder Auftrag noch Repository-Grenzen oder Git-Freigabe. Schreibende Cross-App-Arbeit bleibt ein ausdrücklich benannter Sonderlauf.
- Allgemeine Arbeit in einem getrennten App-Repository folgt ausschließlich dessen lokal mitgeführtem Skill `work-in-nextcloud-app`; app-spezifische Fach-, Rechte-, Test- und Integrationsregeln bleiben in dessen lokaler `AGENTS.md`. Der Parent-Skill ist nur die kanonische Synchronisationsquelle und keine Laufzeitabhängigkeit.
- Subagents sind optional und nur für unabhängige, klar begrenzte Analysearbeit zulässig. Standard ist kein Subagent.
- Es dürfen höchstens zwei direkte Subagents parallel arbeiten; rekursive Erzeugung ist verboten. Explorer und Reviewer sind durch `sandbox_mode = "read-only"` für lokale Datei- und Kommandozugriffe technisch read-only. Externe Connector-/MCP-Systeme werden durch diesen Sandboxwert nicht technisch gesperrt; deren Nutzung oder Änderung verbieten die Rollenregeln ausdrücklich. Die Sprachregeln ergänzen die lokale technische Sperre und begrenzen externe Werkzeuge separat.
- Der Hauptagent liest die maßgeblichen `AGENTS.md`, trifft Entscheidungen, implementiert Änderungen und bewertet alle Ergebnisse selbst.

## Learning Candidates

- Beobachtungen werden nicht automatisch verbindliche Regeln. Ein Candidate muss reproduzierbar oder belegt, künftig wiederverwendbar und der richtigen Ebene zuordenbar sein.
- Keine Candidates sind einmalige Zustände, Vermutungen, temporäre Workarounds, aufgabenspezifische To-dos, sensible Inhalte oder Details, die besser in Tests, Codekommentare oder normale Dokumentation gehören.
- Kandidaten bleiben bis zur ausdrücklichen Freigabe von Simon unverbindlich. Plausible, aber unbestätigte Kandidaten werden als `unbestätigt` markiert.
- Die Prüfung, Klassifikation und Formulierung erfolgt mit dem Skill `evaluate-learning-candidate`. Nur der Hauptagent darf eine dauerhafte Ergänzung vorschlagen oder nach Freigabe einarbeiten.

## Git-Regeln

- Keine Commits, Pushes, Releases oder Deployments ohne ausdrückliche Freigabe durch Simon.
- Kein Commit wird mit roten schnellen Tests vorbereitet oder ausgeführt.
- Nie `git add .`; Dateien werden gezielt gestaged.
- Schreibende Git-Befehle laufen aus dem Root des tatsächlich betroffenen Repositories.
- Vor einem Commit immer `git status --short`, `git diff --stat` und `git diff --name-only` zeigen.
- Bestehende fremde Änderungen bleiben unangetastet. Keine Backupkopien versionierter Dateien, kein `git reset --hard`, `git clean`, Force-Push oder History-Rewrite.

## Definition of Done

Eine Änderung ist nur fertig, wenn:

1. ausschließlich das beauftragte Repository und der beauftragte Scope geändert wurden;
2. Rechte-, Sicherheits-, Accessibility-, Datenschutz- und Repo-Grenzen geprüft wurden, soweit sie betroffen sind;
3. relevante schnelle Tests grün sind und bei app-übergreifender Arbeit `scripts/check-apps` beziehungsweise `scripts/check-full`, bei Delivery-Arbeit dagegen ausdrücklich `scripts/check-ad-suite-delivery` lief;
4. `scripts/check-fast` für Parent-Syntax, Codex-/Workspace-Struktur, `git diff --check`, Parent-Contract-Tests sowie getrackte verbotene Backup-, Dump-, Coverage-, Cache-, Build- und eindeutige Secret-/Private-Key-Dateien erfolgreich war; ein Diagnosemodus ist niemals ein Release-Urteil;
5. manuell der vollständige Git-Status und die vollständige Änderungsliste geprüft wurden und eine inhaltliche Secret-Prüfung erfolgt ist; die Dateinamenprüfung ist ausdrücklich kein umfassender Secret-Scanner;
6. nicht ausführbare Prüfungen und verbleibende Risiken ausdrücklich als `teilweise geprüft` oder `nicht vollständig verifiziert` benannt werden;
7. der Abschlussbericht Anforderungen, geänderte Dateien, Checks mit Ergebnissen, ausgelassene Checks mit Grund, Risiken, Learning Candidates und die Commit-Frage enthält.

Die alte Datei `00_ki_projektkonfiguration_br_nextcloud_apps.md` ist nur ein Kompatibilitätshinweis und keine zweite Regelquelle.
