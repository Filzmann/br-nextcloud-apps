# Verbindliche Struktur der App-Repositories

Alle im Workspace registrierten Nextcloud-Apps verwenden dieselben
Dokumentations- und Steuerungsquellen. Zusätzliche Dateien sind nur erlaubt,
wenn sie einen klar benannten, app-spezifischen Vertrag enthalten und in
`README.md` eingeordnet sind. Eine zweite Planungsdatei ist nicht zulässig.

## Pflichtdateien und Zuständigkeit

| Datei | Ausschließliche Zuständigkeit |
| --- | --- |
| `<app>/README.md` | aktueller nutzbarer Umfang, Installation, Betrieb, Tests und Dokumentationsindex |
| `<app>/ROADMAP.md` | ausschließlich offene, zurückgestellte oder freigabepflichtige Arbeit und Entscheidungen |
| `<app>/CHANGELOG.md` | erledigte, veröffentlichte oder für die nächste Version vorbereitete Änderungen |
| `<app>/LICENSE` | vollständiger Lizenztext passend zur Deklaration in `appinfo/info.xml` |
| `<app>/AGENTS.md` | verbindliche app-lokale Arbeits-, Sicherheits-, Architektur- und Prüfregeln |
| `<app>/docs/architecture.md` | geltende fachliche und technische Architektur, Daten- und Integrationsgrenzen |
| `<app>/docs/manual-acceptance.md` | wiederholbare manuelle Abnahme, keine Aufgabenplanung |
| `<app>/.agents/skills/*/SKILL.md` | synchronisierte wiederholbare Arbeitsabläufe |
| `<app>/appinfo/info.xml` | App-Metadaten und deklarierter Nextcloud-Supportbereich |
| `<app>/.gitignore` | ausschließlich lokale und generierte Artefakte |

`README.md` und `CHANGELOG.md` dürfen den aktuellen beziehungsweise
historischen Stand beschreiben, aber keine konkurrierende Aufgabenliste
führen. `ROADMAP.md` enthält keine als erledigt, umgesetzt oder abgeschlossen
markierten Checklisten. Sobald eine Aufgabe abgeschlossen ist, wird ihr
Ergebnis in `README.md` und ihre Änderung in `CHANGELOG.md` dokumentiert; nur
eine tatsächlich verbleibende Restaufgabe bleibt in der Roadmap.

## Zulässige zusätzliche Dokumente

App-spezifische Vertragsdokumente wie Datenschutzinformationen,
Drittanbieterhinweise, Lokalisierungsinventare oder ausführliche
Produktprozesse bleiben zulässig, wenn ihr Zweck nicht von einer Pflichtdatei
abgedeckt wird. Sie werden im Dokumentationsindex der App benannt und dürfen
keine zweite Roadmap, kein zweites Changelog und keine zweite
Repository-Steuerung bilden.

## Durchsetzung

`tests/check-codex-structure.sh` prüft die Pflichtdateien, lokale Skills,
Symlinkfreiheit, Dokumentationsindizes und offensichtliche erledigte
Checklisten in Roadmaps. Der Referenzcheck prüft relative Markdown-Links. Die
inhaltliche Richtigkeit und Vollständigkeit bleibt zusätzlich Gegenstand der
app-lokalen Reviews und Tests.
