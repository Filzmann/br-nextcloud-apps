# Historischer Migrationsplan: Codex-Steuerungsstruktur

Stand: 17. Juli 2026

Status: Historische Dokumentation des ersten Migrationslaufs; keine Aussage in diesem Plan bestätigt einen aktuellen Tracking-, Commit- oder Freigabestatus.

> Korrekturhinweis: Die erste Umsetzung dieses Plans war nicht freigabefähig. Eine unabhängige Prüfung fand wirkungslose Parent-Skill-Verweise in direkt geöffneten App-Repositories sowie verlorene oder abgeschwächte Regeln. Die verbindliche Befund-, Erhaltungs- und Korrekturmatrix steht in `docs/plans/codex-structure-correction.md`. Aussagen dieses historischen Plans, es habe keinen Regelverlust gegeben oder App-Repositories seien nicht betroffen, gelten nicht für den fehlerhaften Zwischenstand.

## Aktueller Zustand

- Die Root-`AGENTS.md` ist mit rund 800 Zeilen zugleich Regelwerk, Betriebsanleitung, DDEV-Befehlsammlung, neue-App-Checkliste, Subagent-Handbuch und Learning-Workflow.
- Die fachlich getrennten App-Repositories besitzen eigene `AGENTS.md`. Die erste Migration änderte sie und ersetzte lokale Abläufe teilweise durch aus dem App-Root nicht auflösbare Parent-Skill-Verweise; die Korrektur stellt lokale vollständige Instruktionen und Skills wieder her.
- `.agents/` und `.codex/` existieren, enthalten aber keine versionierten Skills, Agentenrollen oder Projektkonfiguration.
- Der Parent besitzt bereits geprüfte Skripte und Shell-Contract-Tests für AD-Suite-Bau, Installation, Coverage, Release-Bereinigung und Delivery. Ein einheitlicher schneller beziehungsweise vollständiger Einstieg fehlt.
- `.gitignore` trennt App-Repositories und ignoriert übliche Build-, Backup-, Log-, Dependency- und Umgebungsdateien. Generische Archive, Dumps, Coverage-Ausgaben und weitere temporäre Varianten sind noch nicht vollständig abgedeckt.
- `docs/workspace.md` ist die human-lesbare Betriebs- und Mount-Dokumentation, ist aber bei App-Liste und DDEV-Mounts gegenüber dem tatsächlichen Workspace veraltet.
- Das Learning-Candidate-Prinzip ist vorhanden, vermischt aber verbindliche Auswahlkriterien mit einem wiederkehrenden Vorschlagsworkflow.

## Zielstruktur

```text
AGENTS.md                                  dauerhafte Parent-Regeln und Definition of Done
.agents/skills/
|-- create-nextcloud-app/SKILL.md          Workflow zum Anlegen eines getrennten App-Repos
|-- work-in-nextcloud-app/SKILL.md         gemeinsamer sicherer Ablauf in bestehenden App-Repos
|-- verify-workspace/SKILL.md              Auswahl und Ausführung schneller/vollständiger Checks
|-- build-ad-suite-release/SKILL.md         sicherer Release- und Delivery-Workflow
`-- evaluate-learning-candidate/SKILL.md   Kandidat prüfen, klassifizieren und vorschlagen
.codex/config.toml                         nur Projekt-Sandbox und Subagent-Grenzen
.codex/agents/
|-- explorer.toml                          ausschließlich lesende Kartierung
`-- reviewer.toml                          ausschließlich lesende unabhängige Prüfung
scripts/check-fast                         schneller Parent-Verifikationsweg
scripts/check-workspace-structure          vollständige Repository-/Instruktionsstruktur
scripts/check-apps                         schnelle Tests aller neun Apps
scripts/check-full                         vollständiger Parent-/App-Workspace-Verifikationsweg
scripts/check-ad-suite-delivery             echtes sauberes AD-Delivery-Gate
tests/check-codex-structure.sh             mechanische Struktur- und Schutzprüfung
config/workspace-repositories.tsv          kanonisches Inventar aller elf Git-Repositories
docs/workspace.md                          aktueller human-lesbarer Betriebsüberblick
docs/plans/codex-structure-migration.md    diese nachvollziehbare Migration
```

Es wird bewusst kein Implementierungs-Subagent angelegt: Der Parent enthält keinen deploybaren App-Code, App-Änderungen benötigen den expliziten Auftrag für das jeweilige getrennte Repository, und zwei lesende Rollen decken die sinnvollen Parent-Aufgaben ohne Rollenvervielfachung ab.

## Geplante Dateiänderungen und Begründungen

| Ursprünglicher Ort/Inhalt | Ziel oder Entscheidung | Inhaltliche Auswirkung |
| --- | --- | --- |
| `AGENTS.md`: Projektgrenze, Repo-Trennung, Architektur-, Rechte-, UI-, Sicherheits-, Test- und Git-Regeln | komprimiert in `AGENTS.md` und im lokal synchronisierten App-Skill erhalten | Die erste Kürzung lockerte einzelne Regeln; Dateipfad-Stop, Nextcloud-native Primitive, Hosting-/CLI-Anforderungen, präzise Accessibility und weitere Erhaltungsregeln werden durch die Korrektur ausdrücklich wiederhergestellt. |
| `AGENTS.md`: vollständige App-/Mount-Listen und häufige DDEV-Befehle | `docs/workspace.md` | Betriebswissen bleibt human-lesbar, belastet aber nicht jede Codex-Sitzung. |
| `AGENTS.md`: Checkliste „Anlegen neuer Apps“ | Skill `create-nextcloud-app` | Wiederholbarer, klar auslösbarer Workflow; Repo-Trennung bleibt zusätzlich als Root-Invariante erhalten. |
| `AGENTS.md`: DDEV-/`occ`-Prüfabfolge und Testauswahl | Skill `verify-workspace`, `scripts/check-fast`, `scripts/check-full` | Einheitliche ausführbare Einstiege; Zustandsänderungen bleiben freigabepflichtig. |
| `AGENTS.md`: AD-Produkt-/Release-Ablauf und Hosting-Abnahme | Skill `build-ad-suite-release`; dauerhafte Lieferinvarianten bleiben in `AGENTS.md` | Der konkrete Ablauf verweist auf bestehende Skripte, ohne Produktionsfreigaben zu automatisieren. |
| `AGENTS.md`: ausführliches Subagent-Handbuch, Rollen, Standardprompt | `.codex/config.toml`, zwei `.codex/agents/*.toml`; kurze dauerhafte Delegationsregel in `AGENTS.md` | Maximal zwei direkte, read-only Rollen; keine Rekursion und keine schreibenden Subagents. |
| `AGENTS.md`: Learning-Vorschlagsformat und Klassifikationsablauf | Skill `evaluate-learning-candidate`; dauerhafte Candidate-Kriterien in `AGENTS.md` | Kandidaten bleiben getrennt von verbindlichen Regeln und benötigen weiter ausdrückliche Freigabe. |
| `AGENTS.md`: ausführliche Abschlussbericht- und Parent-Arbeitsabläufe | kompakte Definition of Done plus Skill `verify-workspace` | Gleiche Nachweise, weniger Wiederholung. |
| `.gitignore`: bestehende Schutzmuster | gezielt um Dumps, Archive, Coverage, Cache und Patch-Reste ergänzen | Nur eindeutig generierte/sensible Artefakte werden ausgeschlossen; Quellformate bleiben zulässig. |
| keine technische Strukturprüfung | `tests/check-codex-structure.sh` | Prüft TOML-/Skill-/Shell-Grundstruktur, Repo-Grenzen, ausführbare Checkskripte und verdächtige getrackte Artefakte. |

## Erhaltene bestehende Regeln

Insbesondere erhalten bleiben:

- Parent und jedes App-Verzeichnis sind getrennte Git-Repositories; App-Code wird nie im Parent verwaltet.
- Keine Commits, Pushes, Deployments oder pauschales `git add .` ohne ausdrückliche Freigabe.
- DDEV wird aus `nextcloud-dev` gesteuert; zustandsändernde DDEV-/`occ`-Aktionen benötigen einen konkreten Auftrag oder eine Freigabe.
- Nextcloud 34 verwendet für App-Migrationen `app:enable` beziehungsweise `upgrade`, nicht `migrations:migrate`.
- Architektur-, LocalBase-, Standalone-Produkt-, Rechte-, Sicherheits-, Accessibility-, Scroll-, Testdaten- und TDD-Verträge bleiben verbindlich.
- Riskante Änderungen stoppen vor der Umsetzung, sofern der konkrete Auftrag sie nicht bereits ausdrücklich freigibt.
- App-spezifische Fachlogik und Gruppen-/Rechteverträge bleiben ausschließlich in den jeweiligen App-`AGENTS.md`.
- Learning Candidates werden nicht automatisch zu Regeln; nur verifizierte, wiederverwendbare Erkenntnisse werden mit Einordnung vorgeschlagen.
- Release- und Delivery-Erfolg umfasst neben App-Aktivierung auch Asset-/HTTP-Smokes auf Hosting-Umgebungen mit getrennten Auslieferungsbenutzern.

## Zusammengeführte oder entfernte Regeln

- Doppelte App-Listen, DDEV-Befehle, Mountblöcke und Git-Kommandolisten werden in `docs/workspace.md` zusammengeführt und aus `AGENTS.md` entfernt.
- Die wiederholten Aussagen „Controller bleiben dünn“, „keine vorsorgliche Abstraktion“ sowie Modell-/Repository-Vorgaben werden je einmal als gemeinsame Architekturregel erhalten.
- Credit-/Modellwahl-Empfehlungen werden entfernt: Sie sind weder projektspezifische Architektur noch zuverlässig durch dieses Repository steuerbar. Die projektbezogenen Teile (gezielte Suche, gebündelte teure Checks, keine unnötige Delegation) bleiben als Arbeitsgrundsatz beziehungsweise Konfiguration erhalten.
- Der lange Subagent-Standardprompt wird durch ausführbare Grenzen und zwei Rollen ersetzt. Eine schreibende Implementierungsrolle wird nicht übernommen.
- Konkrete Testbefehle werden aus dem dauerhaften Regeltext in den Verifikations-Skill und die Checkskripte verschoben; die Qualitäts- und Abdeckungsanforderungen bleiben bestehen.
- Ziel bleibt, keinen fachlich relevanten Inhalt ersatzlos zu entfernen. Die erste Umsetzung verfehlte dieses Ziel; die Korrekturmatrix weist jeden festgestellten Verlust samt Zieltext und technischer Prüfung nach.

## Technische Schutzmaßnahmen

- `scripts/check-fast` bündelt lokale Parent-Shellsyntax, Strukturtest und vorhandene schnelle Contract-Tests.
- `scripts/check-full` führt zuerst den schnellen Parent-Weg und danach alle neun lokalen App-Schnelltests aus. Es behauptet ausdrücklich kein Delivery-Urteil.
- `scripts/check-ad-suite-delivery` ist der saubere Delivery-Pfad. Nur der explizite Schalter `--diagnostic` akzeptiert schmutzige Repositories und endet ohne Release-Urteil. DDEV-, HTTP- und Rechtematrix-Smokes bleiben bewusst opt-in.
- `tests/check-codex-structure.sh` prüft das vollständige Repository-Manifest, lokale `AGENTS.md` und Skills, kanonische Synchronität, Frontmatter/Name/Ordner/Eindeutigkeit, Symlinks, Tracking-/Ignore-Status, TOML-/Agentpfade, technische Read-only-Sandboxes und direkte Standalone-Verwendbarkeit. Python 3 und `tomllib` werden als verständliche Voraussetzung geprüft.
- `.gitignore` erhält nur eindeutige Muster für generierte Archive, Datenbank-Dumps, Coverage-/Cache-Ausgaben und Patch-Reste. Es wird kein Git-Hook eingeführt: Hooks wären lokal nicht automatisch aktiv und würden ohne vorhandenes Hook-Management einen Scheinschutz erzeugen.
- Es werden keine neuen Dependencies installiert.

## Learning-Candidate-Einordnung

| Übernommener Candidate | Einordnung | Begründung |
| --- | --- | --- |
| Getrennte PHP-FPM-, Static-Webserver- und CLI-Benutzer bei Hosting-Panels | dauerhafte Lieferregel in `AGENTS.md` plus Release-Skill | Verifiziertes, wiederkehrendes Sicherheits-/Abnahmerisiko; der Ablauf gehört in den Skill, die Erfolgsbedingung in die Definition of Done. |
| Persönliche Einstellungen als eigener Tab, Fachadmin versus Suite-Admin | dauerhafte `AGENTS.md`-Regel | Stabile app-übergreifende UI-/Rechtearchitektur, kein bloßer Ablauf. |
| Nextcloud-App-Root als expliziter Scrollcontainer | dauerhafte `AGENTS.md`-Regel | Stabile app-übergreifende Layoutinvariante mit überprüfbarer DoD. |
| AD-Fachprodukte bleiben standalone, OrgSuite ab zwei Produkten | dauerhafte Architekturregel plus Release-Skill/Contract-Tests | Produkt- und Integrationsvertrag; bereits technisch durch Tests und Installer gestützt. |
| Isolierte PHP-Smoke-Prozesse und schnelle Repo-Einstiege | Testregel plus Verifikations-Skill | Qualitätsinvariante mit wiederkehrendem Ausführungsworkflow. |
| Neue Beobachtungen zuerst als Learning Candidate behandeln | Candidate-Kriterien in `AGENTS.md`, Workflow im Learning-Skill | Verhindert automatische Regelakkumulation und trennt Entscheidung von Ausführung. |

## Risiken

- Eine starke Kürzung könnte eine seltene Randregel verdecken. Deshalb werden fachliche Invarianten abschnittsweise übernommen und die Verschiebungsmatrix sowie Diff-Prüfung als Nachweis genutzt.
- Projektlokale `.codex/config.toml` wird nur in als vertrauenswürdig markierten Projekten geladen. Die Root-`AGENTS.md` bleibt deshalb für Sicherheits- und Repo-Grenzen maßgeblich.
- `check-full` hängt von allen getrennten App-Repositories und deren lokalen PHP-/Node-Einstiegen ab; fehlende Voraussetzungen müssen als nicht vollständig verifiziert gemeldet werden.
- App-`AGENTS.md` und lokale Skill-Kopien werden in der Korrektur bewusst in ihren eigenen Repositories geändert, weil nur so direkte Starts ohne Parent-Laufzeitabhängigkeit sicher sind. Die technische Synchronitätsprüfung verhindert unbeabsichtigte Abweichungen.

## Prüfstrategie

1. Zuerst `scripts/check-fast`.
2. Danach `scripts/check-full`, sofern alle App-Repositories und lokalen Laufzeiten verfügbar sind; bei Delivery-Arbeit getrennt `scripts/check-ad-suite-delivery` beziehungsweise explizit dessen Diagnosemodus.
3. Zusätzlich `git diff --check`, vollständige Status-/Diff-Liste und Syntaxprüfung für TOML, YAML, Markdown und Shell.
4. Prüfung der Ausführungsbits neuer Skripte.
5. Suche in getrackten Dateien nach Secrets, Dumps, Backups, Caches und temporären Artefakten.
6. Vergleich der Root-Regeln mit allen vorhandenen App-`AGENTS.md` auf widersprüchliche Reichweiten.
7. Abschließender Lesetest aus Sicht eines neuen Codex-Chats: Einstieg, Repo-Routing, Skills, Agentengrenzen und Verifikationswege müssen ohne Sessionwissen verständlich sein.

## Offene Grundsatzentscheidungen

Keine für diese Migration. Bewusst nicht umgesetzt werden:

- Änderungen an `~/.codex`; App-Repositories werden dagegen im ausdrücklich freigegebenen Korrekturscope angepasst;
- neue Produktions- oder Entwicklungsdependencies;
- ein schreibender Implementierungs-Subagent;
- automatisch aktive Git-Hooks ohne bestehendes Hook-Management;
- DDEV-, Server-, Datenbank- oder Netzwerkmutationen;
- Commits, Pushes oder Releases.

## Nachreview: Migrationsübersicht der App-AGENTS.md

Die App-Repositories wurden am 17. Juli 2026 einzeln und ohne Anwendungscodeänderungen geprüft. Die Behauptung der ersten Fassung, ausschließlich semantisch identische Abläufe seien ohne Abschwächung ausgelagert worden, war zu stark: lokale Git-, Stop-, Migrations-, Sandbox- und Learning-Regeln wurden teilweise durch nicht lokale Verweise ersetzt. Die Korrektur führt deshalb einen vollständigen lokalen Skill je App ein und weist die wiederhergestellten Inhalte in `codex-structure-correction.md` nach.

| Kategorie | Gemeinsames Ziel | Lokal beibehalten |
| --- | --- | --- |
| Git-Ablauf | `work-in-nextcloud-app`: Status vor Arbeit, gezieltes Staging, keine Commits/Pushes/Deployments ohne Freigabe, Vor-Commit-Diffs, keine Commits mit roten schnellen Tests | Repository-Identität, fremde Repository-Grenzen, LocalBase-Neutralität, OrgSuite-Verbot von Fachdaten, Produktveröffentlichung in `ad-suite` |
| DDEV/`occ` und Sandbox | `work-in-nextcloud-app`: Steuerung aus `nextcloud-dev`, eng begrenzte Eskalation, Freigabe für Zustandsänderungen, Nextcloud-34-Migrationsweg | App-Mounts, LocalBase-Voraussetzungen, konkrete App-/DI-/Job-/Scan-/HTTP-/Rechtematrix-/Integrationskommandos |
| Learning Candidates | `work-in-nextcloud-app` plus `evaluate-learning-candidate`: Evidenz, Ziel Parent/App, Freigabe vor dauerhafter Regel | Fachliche Kandidaten zielen weiterhin auf die jeweilige App-Datei; bestehende fachliche Learnings und Navigationsverträge bleiben dort |
| Tests und JavaScript | Root-`AGENTS.md` hält Test-first mit Ausnahmen, Coverage-, Helper- und allgemeine Schichtungsverträge | App-spezifische Fast-Suites, Fach-/Rechte-/Contract-Smokes, HTTP/DDEV-Prüfungen, konkrete Komponenten und Datenflüsse |
| Fachliche Regeln | keine Auslagerung | Sämtliche Ziele, Fachmodelle, Gruppen/Rechte, Hierarchien, Standalone-Zustände, Navigation, Datenschutz, Accessibility und app-spezifische Architektur |

Einzelergebnis:

- `brtop`, `adplaner`, `brstunden`, `br_permission_matrix` und `adcalendar`: allgemeine Git-, Sandbox-, Migrations- und Learning-Abläufe durch Skill-Verweis ersetzt; Fach-, Navigations-, Architektur- und Testverträge erhalten.
- `localbase`: nur allgemeiner Git-/Sandboxrahmen ausgelagert; Multiplikator-, Test-Helper- und Cross-App-Verträge ausdrücklich lokal beibehalten und `verify-workspace` zusätzlich verlangt.
- `adurlaub`, `orgsuite` und `adroom`: nur die wenigen allgemeinen Git-/Migrationspunkte ersetzt; kompakte lokale Fach- und Prüfverträge beibehalten.
- `ad-suite`: nicht verändert, weil es kein App-Repository ist und seine Release-/Publikationsregeln spezifisch sind; der gemeinsame Releaseablauf liegt bereits in `build-ad-suite-release`.
