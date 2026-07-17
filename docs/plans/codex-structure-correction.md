# Historischer Korrekturplan der Codex-Steuerungsstruktur

Stand: 2026-07-17  
Status: Historische Dokumentation des lokalen Korrekturlaufs. Die beschriebenen Steuerungsdateien sind im aktuellen Arbeitsstand teilweise ungetrackt; Versionierung und sauberer Release-Gate bleiben bis zu getrennten Commits offen.

Diese Datei dokumentiert den damaligen Befund und die Zuordnung der Regeln. Präsens-Aussagen in den Ergebnisabschnitten beschreiben den lokalen Arbeitsstand dieses Korrekturlaufs und sind keine Behauptung, dass Dateien bereits committed oder veröffentlicht sind.

## Ziel und feste Grenzen

Die Korrektur stellt sicher, dass jedes eigenständige Git-Repository direkt aus seinem eigenen Root sicher bearbeitet und geprüft werden kann. Parent-Regeln und Parent-Skills werden nicht als implizit geladen vorausgesetzt. Gemeinsame App-Arbeitsregeln werden im Parent kanonisch gepflegt und als normale Datei in jedes App-Repository kopiert; versioniert sind sie erst nach den getrennten Repository-Commits. Symlinks, globale Skills und Benutzerkonfiguration sind keine Laufzeitabhängigkeit.

Nicht Bestandteil sind App-Code-Refactorings, Produktionszugriffe, neue Dependencies, Commits, Releases oder Änderungen außerhalb der hier inventarisierten Repositories.

## Vollständiges Repository-Inventar und direkte Instruktionskette

| Repository | Typ | Bei direktem Start geladene Repository-Instruktion | Aktuell lokal auffindbare Repository-Skills | Befund vor Korrektur |
| --- | --- | --- | --- | --- |
| `.` | Parent/Meta | `AGENTS.md` | fünf Skills unter `.agents/skills/` | Parent-Kette funktionsfähig, Schutz und Prüfpfade unvollständig |
| `brtop` | App | `brtop/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `adplaner` | App | `adplaner/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `brstunden` | App | `brstunden/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `localbase` | App/Infrastruktur | `localbase/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `br_permission_matrix` | App | `br_permission_matrix/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `adcalendar` | App | `adcalendar/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar; Parent-Regeln fälschlich als ergänzend bezeichnet |
| `adurlaub` | App | `adurlaub/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `orgsuite` | App/Infrastruktur | `orgsuite/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `adroom` | App | `adroom/AGENTS.md` | keine | Parent-Skill-Verweise nicht auflösbar |
| `ad-suite` | Produktdokumentation | `ad-suite/AGENTS.md` | keine erforderlich | lokal selbstständig; keine Parent-Skill-Abhängigkeit |

Die allgemeine Codex-System- und Benutzerkonfiguration kann zusätzlich gelten, gehört aber nicht zu diesen Repositories und darf von der Migration weder vorausgesetzt noch verändert werden. Innerhalb jedes Repository-Roots ist die oben genannte lokale `AGENTS.md` die einzige automatisch geladene Repository-Regelquelle. Eine Parent-`AGENTS.md` außerhalb eines getrennten Kind-Repository-Roots gehört nicht zu dessen verlässlicher direkter Startkette.

## Erhaltungsmatrix: repositorybezogene Arbeitsregeln

Die folgenden Einträge beruhen auf dem Vergleich jeder geänderten `AGENTS.md` mit ihrer jeweiligen `HEAD`-Version.

| Repository | Alte Datei/Regel | Verbindlicher Altinhalt | Zustand vor Korrektur | Zielort und exakte neue Regel | Technische Prüfung | Status |
| --- | --- | --- | --- | --- | --- | --- |
| `brtop` | `AGENTS.md`: Git, Migration, Sandbox, Learning | Kein Commit/Push/Deploy ohne Freigabe; gezieltes Staging; Status/Diff-Liste; NC 34 ohne `occ migrations:migrate`; Docker-FD-Fehler nicht als App-Fehler; Learning nur geprüft | entfernt bzw. durch nicht lokalen Parent-Skill ersetzt | lokale `AGENTS.md`: „Allgemeine Arbeit in diesem Repository folgt dem lokal mitgeführten Skill `work-in-nextcloud-app`.“; lokaler Skill enthält die Altregeln vollständig | lokale Skill-Auflösung, Bytevergleich, App-Check | zugeordnet |
| `adplaner` | `AGENTS.md`: Git, Migration, Sandbox, Learning | wie `brtop`, einschließlich kleiner rückbaubarer Änderungen | entfernt bzw. nicht lokal auflösbar | gleiche lokale Skill-Regel und lokaler Skill | lokale Skill-Auflösung, Bytevergleich, App-Check | zugeordnet |
| `brstunden` | `AGENTS.md`: Git, Migration, Sandbox, Learning | kein Commit/Push/Deploy; gezieltes Staging; Status/Diffs; NC-34- und Sandbox-Regeln | entfernt bzw. nicht lokal auflösbar | gleiche lokale Skill-Regel und lokaler Skill | lokale Skill-Auflösung, Bytevergleich, App-Check | zugeordnet |
| `localbase` | `AGENTS.md`: Git und Stop-/Prüfverweise | kein Commit/Push/Deploy; gezieltes Staging; Status/Diffs; öffentliche Verträge nur kontrolliert | teilweise entfernt und auf Parent-Regeln verwiesen | lokale Skill-Regel; lokale Fachregel zu öffentlichen LocalBase-Verträgen bleibt in `AGENTS.md`; Stop- und Git-Regeln im lokalen Skill | lokale Skill-Auflösung, Bytevergleich, Contract-/App-Check | zugeordnet |
| `br_permission_matrix` | `AGENTS.md`: Git, Migration, Sandbox, Learning | kein Commit/Push/Deploy; kleine Änderungen; NC-34- und Sandbox-Regeln | entfernt bzw. nicht lokal auflösbar | gleiche lokale Skill-Regel und lokaler Skill | lokale Skill-Auflösung, Bytevergleich, App-Check | zugeordnet |
| `adcalendar` | `AGENTS.md`: Parent-Ergänzung, Git, Migration, Zustandsänderung, Learning | lokales Repo ist maßgeblich; keine Zustandsänderung ohne Auftrag; Git-/NC-34-/Learning-Regeln | abgeschwächt und von außerhalb abhängig | „Diese Datei und lokal referenzierte Skills sind die vollständige Repository-Steuerung bei einem direkten Start in diesem Repository.“ sowie lokale Skill-Regel | Verbot wirkungsloser Parent-Verweise, lokale Skill-Auflösung, App-Check | zugeordnet |
| `adurlaub` | `AGENTS.md`: Git und Migration | gezieltes Staging; Status/Diff-Liste; NC-34-Migrationsweg | entfernt bzw. nicht lokal auflösbar | gleiche lokale Skill-Regel und lokaler Skill | lokale Skill-Auflösung, Bytevergleich, App-Check | zugeordnet |
| `orgsuite` | `AGENTS.md`: Git | gezieltes Staging; Status/Diff-Liste | entfernt bzw. nicht lokal auflösbar | gleiche lokale Skill-Regel und lokaler Skill; lokale Navigationsverträge verbleiben in `AGENTS.md` | lokale Skill-Auflösung, Bytevergleich, App-Check | zugeordnet |
| `adroom` | `AGENTS.md`: Git und Migration | gezieltes Staging; Status/Diff-Liste; NC-34-Migrationsweg | entfernt bzw. nicht lokal auflösbar | gleiche lokale Skill-Regel und lokaler Skill | lokale Skill-Auflösung, Bytevergleich, App-Check | zugeordnet |
| `ad-suite` | `AGENTS.md`: Produkt-/Release-Grenzen | nur Dokumentation/Artefakte; keine App-Implementierung; keine Veröffentlichung ohne Freigabe | unverändert und lokal vollständig | keine Skill-Pflicht; lokale Regeln bleiben bestehen | Strukturprüfung und Delivery-Prüfung | erhalten |

## Erhaltungsmatrix: gemeinsame verbindliche Inhalte

Der kanonische Skill liegt unter `.agents/skills/work-in-nextcloud-app/SKILL.md`; jedes der neun App-Repositories erhält eine identische Kopie unter demselben relativen Pfad. App-spezifische Regeln verbleiben ausschließlich in der lokalen `AGENTS.md`.

| Alte Regelquelle | Verbindlicher Inhalt | Zustand vor Korrektur | Ziel und exakte neue Formulierung | Technische Prüfung | Status |
| --- | --- | --- | --- | --- | --- |
| frühere Parent-Stop-Regeln | Dateiablage, **Dateipfade**, Uploads, Downloads und Dokumenterzeugung brauchen bei nicht bereits umfasstem Auftrag einen Stopp; unklarer Rückbauweg ist eigener Stop-Grund | Dateipfade und eigenständiger Rückbaupunkt verloren | Skill: „Vor Änderungen an Dateiablage, Dateipfaden, Uploads, Downloads oder Dokumenterzeugung stoppen …“ und eigener Punkt „Der Rückbauweg ist unklar.“ | Inhalts-Assertions im Strukturtest | zugeordnet |
| frühere Sicherheitsregeln | Nextcloud-native Gruppen-, Benutzer-, Session-, AppConfig-, Share-, Datei-, Capability- und Request-APIs bevorzugen; serverseitige Erzwingung | konkrete Primitive abgeschwächt | Skill und Parent: „Nextcloud-native Gruppen-, Benutzer-, Session-, AppConfig-, Share-, Datei-, Capability- und Request-APIs sind eigenen Parallelmechanismen vorzuziehen.“ | Inhalts-Assertions | zugeordnet |
| frühere Hosting-/Delivery-Regeln | reale `apps_paths`; Core read-only; `custom_apps` writable; CLI-PHP plus ausreichendes CLI-Memory-Limit; Static- und HTTPS-Assetprüfung | teilweise verloren | Parent und Skill nennen alle vier Anforderungen wörtlich; Delivery-Skript bleibt zustandsfrei und prüft nur vorhandene Artefakte/Verträge | Inhalts-Assertions und Delivery-Checks | zugeordnet |
| frühere Testregeln | Rot–Grün–Refactor; Regression-/Charakterisierungstest; zulässige Spikes/deklarative/NC-Integrationsausnahmen; Coverage darf nicht unbemerkt sinken; 85 % Ziel getrennt PHP/JS; keine roten Commit-/Release-Gates | verkürzt | Skill übernimmt den vollständigen Test-, Ausnahme- und Coverage-Vertrag | Inhalts-Assertions, App-Checks, Coverage-Baseline-Test | zugeordnet |
| frühere Helper-Regel | gemeinsame Helper erst bei mindestens zwei **semantisch gleichen** Verwendungen | „semantisch gleich“ gefährdet | Skill und Parent nennen die semantische Gleichheit ausdrücklich | Inhalts-Assertion | zugeordnet |
| frühere Suite-Regeln | OrgSuite besitzt gemeinsame Einstiege; Fachapps registrieren nur im Standalone-Fall eigene Navigation; Navigation erteilt keine Rechte | erhalten, muss lokal wirksam bleiben | lokale App-AGENTS behalten produktspezifische Verträge; gemeinsamer Skill verweist nicht auf Parent-Laufzeitregeln | App-Vertragsprüfungen | erhalten/zugeordnet |
| frühere Accessibility-Regeln | semantische Settings-Tabs mit Rollen/ARIA-Beziehungen; Tastatur/Fokus; Root-Scrollvertrag; innerer Horizontal-Scroll; kein globales CSS; keine rein farbliche/Hover-Bedeutung | im Parent verallgemeinert | Skill enthält die präzisen Tab-, ARIA-, Tastatur-, Scroll- und CSS-Regeln; lokale Fachabweichungen bleiben lokal | Inhalts-Assertions und bestehende UI-Smokes | zugeordnet |
| frühere externe-Dienste-Regel | unerwartet nötige externe Systeme lösen Stopp aus; ein ausdrücklich beauftragter Staging-/Hosting-Check darf dagegen im genehmigten Scope laufen | sprachlich mehrdeutig | Skill: „Unerwartet erforderliche externe Dienste … lösen einen Stopp aus. Ein ausdrücklich beauftragter Zugriff auf ein benanntes Staging-/Hosting-System ist davon nicht erfasst, bleibt aber auf den genehmigten Scope beschränkt.“ | Inhalts-Assertion | zugeordnet |
| frühere Learning-Regeln | Beobachtung nur belegt/reproduzierbar, wiederverwendbar und richtiger Ebene zugeordnet; Vorschlag vor dauerhafter Aufnahme | Apps verwiesen auf Parent-Skill | lokale Skill-Kopie enthält Kriterien und unterscheidet lokale Regel, Test/Skript und app-übergreifenden Candidate | Inhalts-Assertion | zugeordnet |
| frühere Git-Regeln | keine Commits/Pushes/Releases/Deployments ohne Freigabe; nie `git add .`; gezieltes Staging; Status, Diff-Statistik und Dateiliste; fremde Änderungen erhalten | in mehreren Apps entfernt | lokale Skill-Kopie enthält diese Regeln vollständig | Inhalts-Assertions; Vorher-/Nachher-Status | zugeordnet |

## Technische Zielstruktur

1. Das für die Versionierung vorgesehene Repositorymanifest ist die einzige technische Quelle für Parent, neun Apps und `ad-suite`.
2. `tests/check-codex-structure.sh` vergleicht diese Liste mit allen gefundenen Git-Roots und prüft pro App eine lokale `AGENTS.md`, lokale referenzierte Skills, Skill-Frontmatter, Name/Ordner-Konsistenz, eindeutige Namen, fehlende oder unerlaubte Symlinks, Tracking-/Ignore-Status, TOML und Agentpfade sowie die direkte Standalone-Verwendbarkeit.
3. Die kanonische Parent-Version von `work-in-nextcloud-app` und alle neun lokalen Kopien müssen bytegleich sein. Bewusste app-spezifische Abweichungen gehören in die lokale `AGENTS.md`, nicht in eine abweichende Skill-Kopie.
4. `scripts/check-fast` prüft den Parent schnell und zustandsfrei. `scripts/check-apps` prüft alle neun App-Repositories. `scripts/check-full` kombiniert den vollständigen Workspace-Struktur- und App-Prüfpfad, behauptet aber keine AD-Delivery-Abnahme.
   Im Delivery-Pfad führt der Wrapper den strikten Parent-Fast-Pfad genau einmal aus; `verify-ad-suite-delivery.sh` wiederholt dessen Contract-Tests nicht.
5. `scripts/check-ad-suite-delivery` ist der echte saubere Delivery-Gate. Ein expliziter Diagnosemodus darf mit schmutzigen Repositories laufen, endet aber exakt mit `DIAGNOSE ABGESCHLOSSEN – KEIN RELEASE-URTEIL` und niemals mit einem Release-OK.
6. Python und `tomllib` werden vor dem Strukturtest verständlich als Voraussetzung geprüft; fehlt `tomllib`, wird eine klare Fehlermeldung ausgegeben statt eines irreführenden Folgfehlers.
7. Parent-Standardzugriff wird in `.codex/config.toml` read-only. Schreibende App-Arbeit startet aus dem App-Root; ein app-übergreifender Schreiblauf ist ein ausdrücklich benannter Sonderlauf und wird nicht durch `.gitignore` als Schutz simuliert.

## Freigabekriterien für die Korrektur

- Jedes Repository besteht die Prüfung aus seinem eigenen Root.
- Alle neun Apps lösen `work-in-nextcloud-app` ausschließlich lokal auf.
- Repository-Inventar, Kompatibilitätshinweis und Workspace-Dokumentation sind vollständig und widerspruchsfrei.
- `git diff --check`, `bash -n scripts/*.sh tests/*.sh`, Workspace-Strukturprüfung, alle App-Schnelltests und der AD-Delivery-Check liefern nachvollziehbare Einzelresultate.
- Diagnose und Release-Gate sind sprachlich und technisch getrennt.
- Vorher-/Nachher-Status jedes Repositorys wird berichtet; keine fremde Änderung wird gestaged, verworfen oder überschrieben.

## Entscheidung nach Phase 1

Alle in den `HEAD`-Versionen vorhandenen verbindlichen Regeln konnten einem der oben beschriebenen Zielorte und einer technischen Prüfung eindeutig zugeordnet werden. Es gibt keinen ungeklärten Regelverlust. Phase 2 wird deshalb ohne Zwischenstopp ausgeführt.

## Historisches Ergebnis der Phase 2 im lokalen Arbeitsstand

- Das Manifest enthält exakt Parent, neun App-Repositories und `ad-suite`; die Strukturprüfung vergleicht es rekursiv mit allen gefundenen Git-Roots.
- Alle neun Apps besitzen eine lokale, reguläre und bytegleiche Kopie von `work-in-nextcloud-app`. Ihre `AGENTS.md` erklärt die vollständige direkte Repository-Steuerung und verweist auf keinen Parent-Skill oder globalen Skill.
- Dateipfad-Stop, eigenständiger unklarer Rückbauweg, Nextcloud-native Primitive, TDD-Ausnahmen, Coverage, semantisch gleiche Helper, OrgSuite-/Standalone-Navigation, präzise Tabs/ARIA/Scrollregeln, reale `apps_paths`, Core-read-only/`custom_apps`-writable, CLI-PHP/-Memory, Static-Webserver-Kontext sowie Asset-/HTTPS-Abnahme sind wiederhergestellt.
- Parent, Explorer und Reviewer sind für lokale Datei- und Kommandozugriffe read-only konfiguriert. Dieser Sandboxwert sperrt externe Connector-/MCP-Systeme nicht technisch; deren Nutzung wird separat durch Auftrag und Rollenregeln begrenzt. Schreibende App-Arbeit beginnt im App-Root; Cross-App-Schreiben ist ein ausdrücklich beauftragter Sonderlauf. Temporäre Verifikationsartefakte begründen keine Repository-Schreibrechte.
- Struktur-, App-, Full-Workspace- und AD-Delivery-Prüfung sind getrennte ausführbare Pfade. Das echte Delivery-Gate verweigert schmutzige Repositories; sein Diagnosemodus ist nur über `--diagnostic` erreichbar und endet exakt ohne Release-Urteil. Der interne alte Gate-Einstieg verweigert direkte Aufrufe.
- Tracking ist technisch geprüft: Im Arbeitsstand endet der Diagnosemodus bei ungetrackten Pflichtdateien mit `STRUKTUR VORHANDEN – NICHT FREIGABEFÄHIG: PFLICHTDATEIEN UNGETRACKT`; `REQUIRE_TRACKED_STRUCTURE=1` macht dies zum harten Gate. Das saubere Delivery-Gate verlangt zusätzlich den strikten Trackingmodus.

## Prompt für die unabhängige Read-only-Nachprüfung

```text
Validiere die korrigierte Codex-Steuerungsstruktur dieses Workspaces unabhängig und ausschließlich read-only. Nimm keine Änderungen, Staging-, Commit-, DDEV-, Netzwerk- oder externen Systemaktionen vor.

Prüfe aus dem Parent und zusätzlich aus jedem der elf in config/workspace-repositories.tsv gelisteten Git-Roots: tatsächlich geladene Instruktionskette, lokale Skill-Auflösung, Skill-Selektierbarkeit, AGENTS-Diffs gegen HEAD, erhaltene fachliche Regeln, Repository-Grenzen, Subagent-Sandboxen, Symlinks, Tracking-/Ignore-Status und TOML-/Agentpfade. Verifiziere besonders, dass jede der neun Apps ohne Parent- oder globale Skill-Abhängigkeit direkt startfähig ist und ihre lokale work-in-nextcloud-app-Kopie exakt der kanonischen Parent-Fassung entspricht.

Führe nur gefahrlose Lese-, Syntax- und Prüfkommandos aus. Prüfe scripts/check-workspace-structure, scripts/check-fast, scripts/check-apps und scripts/check-full. Prüfe das saubere scripts/check-ad-suite-delivery nur auf seine erwartete Dirty-Ablehnung; führe einen Paketbau nur aus, wenn ausdrücklich als lokale Diagnose beauftragt, und akzeptiere dann ausschließlich den Abschluss DIAGNOSE ABGESCHLOSSEN – KEIN RELEASE-URTEIL.

Berichte nach Priorität: kritische Fehler, relevante Schwächen, optionale Verbesserungen, bestätigte funktionierende Teile, geladene Instruktionsketten je Repository, ausgeführte/ausgelassene Checks, Dirty-/Tracking-Risiken und abschließendes Urteil freigabefähig, freigabefähig mit Korrekturen oder nicht freigabefähig.
```
