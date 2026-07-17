# KI-Projektkonfiguration - BR Nextcloud Apps

Diese Datei bleibt als Einstiegspunkt fuer aeltere Codex-/KI-Workflows erhalten.

Die verbindlichen Regeln stehen jetzt hier. Die folgende Liste ist eine nicht-kanonische Lesehilfe; das vollständige Repositoryinventar wird ausschließlich aus `config/workspace-repositories.tsv` abgeleitet:

- `AGENTS.md`: Arbeitsregeln fuer Codex im Parent-/Meta-Workspace.
- `.agents/skills/`: wiederkehrende, klar abgegrenzte Codex-Arbeitsabläufe.
- `.codex/config.toml` und `.codex/agents/`: projektbezogene Codex- und read-only Agentenkonfiguration.
- `docs/workspace.md`: human-lesbare Workspace-Dokumentation zu Repo-Trennung, DDEV, Mounts und neuen Apps.
- `brtop/AGENTS.md`: app-spezifische Regeln fuer BRTop.
- `adplaner/AGENTS.md`: app-spezifische Regeln fuer AdPlaner.
- `brstunden/AGENTS.md`: app-spezifische Regeln fuer BRStunden.
- `localbase/AGENTS.md`: app-spezifische Regeln fuer LocalBase.
- `br_permission_matrix/AGENTS.md`: app-spezifische Regeln fuer die Berechtigungsmatrix.
- `adcalendar/AGENTS.md`: app-spezifische Regeln fuer AD Kalender.
- `adurlaub/AGENTS.md`: app-spezifische Regeln fuer AD Urlaub.
- `orgsuite/AGENTS.md`: app-spezifische Regeln fuer OrgSuite.
- `adroom/AGENTS.md`: app-spezifische Regeln fuer AD Raum.
- `ad-suite/AGENTS.md`: Regeln fuer Produktdokumentation und Release-Unterlagen.
- `config/workspace-repositories.tsv`: vollstaendige technisch gepruefte Liste aller elf Git-Repositories.

Kurzfassung:

- Der Parent `~/projects/br-nextcloud-apps` ist nur Meta-/DDEV-/Dokumentationskontext.
- App-Code aus `brtop/`, `adplaner/`, `brstunden/`, `localbase/`, `br_permission_matrix/`, `adcalendar/`, `adurlaub/`, `orgsuite/` und `adroom/` sowie Produktdokumentation aus `ad-suite/` wird im Parent nicht getrackt.
- App-Code wird nur im jeweiligen App-Repo geaendert und nur nach ausdruecklichem Auftrag.
- Jede neue deploybare Nextcloud-App bekommt ein eigenes Git-Repo, eigene `AGENTS.md`, eigene `.gitignore`, eine reguläre lokale Kopie des Pflicht-Skills und einen Manifest-Eintrag.
- Jede bestehende App fuehrt den referenzierten Skill `work-in-nextcloud-app` lokal mit; direkte App-Starts haengen nicht vom Parent oder globalen Skills ab.
- Parent-Aenderungen betreffen gemeinsame Regeln, DDEV, Mounts, neue-App-Checklisten, technische Synchronisationsquellen und app-uebergreifende Learnings.

Diese Datei soll keine zweite Regelquelle werden. Wenn Inhalte abweichen, gelten `AGENTS.md` und die jeweilige App-`AGENTS.md`.
