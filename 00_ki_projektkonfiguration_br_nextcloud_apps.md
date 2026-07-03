# KI-Projektkonfiguration - BR Nextcloud Apps

Diese Datei bleibt als Einstiegspunkt fuer aeltere Codex-/KI-Workflows erhalten.

Die verbindlichen Regeln stehen jetzt hier:

- `AGENTS.md`: Arbeitsregeln fuer Codex im Parent-/Meta-Workspace.
- `docs/workspace.md`: human-lesbare Workspace-Dokumentation zu Repo-Trennung, DDEV, Mounts und neuen Apps.
- `brtop/AGENTS.md`: app-spezifische Regeln fuer BRTop.
- `adplaner/AGENTS.md`: app-spezifische Regeln fuer AdPlaner.
- `brstunden/AGENTS.md`: app-spezifische Regeln fuer BRStunden.

Kurzfassung:

- Der Parent `~/projects/br-nextcloud-apps` ist nur Meta-/DDEV-/Dokumentationskontext.
- App-Code aus `brtop/`, `adplaner/`, `brstunden/` und weiteren App-Repos wird im Parent nicht getrackt.
- App-Code wird nur im jeweiligen App-Repo geaendert und nur nach ausdruecklichem Auftrag.
- Jede neue deploybare Nextcloud-App bekommt ein eigenes Git-Repo, eigene `AGENTS.md` und eigene `.gitignore`.
- Parent-Aenderungen betreffen gemeinsame Regeln, DDEV, Mounts, neue-App-Checklisten und app-uebergreifende Learnings.

Diese Datei soll keine zweite Regelquelle werden. Wenn Inhalte abweichen, gelten `AGENTS.md` und die jeweilige App-`AGENTS.md`.
