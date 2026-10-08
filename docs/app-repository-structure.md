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
| `<app>/docs/manual-acceptance.md` | versioniertes Formular und aktueller Nachweis der manuellen Abnahme, keine Aufgabenplanung |
| `<app>/.agents/skills/*/SKILL.md` | synchronisierte wiederholbare Arbeitsabläufe |
| `<app>/appinfo/info.xml` | App-Metadaten und deklarierter Nextcloud-Supportbereich |
| `<app>/.gitignore` | ausschließlich lokale und generierte Artefakte |

## Bedingter Processing-Katalog

Eine App mit eigener personenbezogener Verarbeitung hält ihre kanonischen
fachlichen Processing-Metadaten in
`<app>/resources/privacy-processing.json`. Die Datei folgt dem Root-Schema
`docs/contracts/privacy-processing-metadata.schema.json`, bleibt Teil des
jeweiligen App-Repositories und enthält keine personenbezogenen Laufzeitdaten.
App-lokale Provider, Retention-Policies und Contract-Tests leiten ihre
benötigten Projektionen daraus ab, statt Zweck, Empfänger oder Retention
unabhängig ein zweites Mal zu pflegen.

Apps ohne eigene personenbezogene Verarbeitung erzeugen keinen leeren
Katalog. Sie begründen die Nichtanwendbarkeit in `docs/architecture.md` und
bewerten sie bei einer Scopeänderung neu. Ein Katalog ist im App-`README.md`
als technischer Vertrag einzuordnen, aber keine zusätzliche Planungs- oder
Steuerungsdatei.

`README.md` und `CHANGELOG.md` dürfen den aktuellen beziehungsweise
historischen Stand beschreiben, aber keine konkurrierende Aufgabenliste
führen. `ROADMAP.md` enthält keine als erledigt, umgesetzt oder abgeschlossen
markierten Checklisten. Sobald eine Aufgabe abgeschlossen ist, wird ihr
Ergebnis in `README.md` und ihre Änderung in `CHANGELOG.md` dokumentiert; nur
eine tatsächlich verbleibende Restaufgabe bleibt in der Roadmap.

## Lebenszyklus der manuellen Abnahme

`<app>/docs/manual-acceptance.md` ist zugleich wiederverwendbare
Prüfvorschrift und versionierter aktueller Abnahmenachweis. Ausgefüllte Kopfdaten, markierte
`[x]`-Ergebnisse und datensparsame Belege bleiben deshalb in derselben Datei
und werden mit dem geprüften Stand committed. Eine getrennte historische
Abnahmedatei oder eine generierte HTML-Kopie wird nicht als zweite Wahrheit
geführt; die Git-Historie bewahrt frühere Stände.

Bei einer Codeänderung werden vor dem Commit genau die Ergebnisfelder und
Belege der davon fachlich oder technisch betroffenen Prüffälle zurückgesetzt.
Ergebnisse zu nachweislich unverändertem Verhalten bleiben erhalten. Die
erneute manuelle Prüfung füllt die zurückgesetzten Felder wieder aus; der
zugehörige Code-Commit enthält das aktualisierte Formular immer mit. Das
Zurücksetzen des gesamten Formulars ist nur erforderlich, wenn die Änderung
tatsächlich alle Prüffälle entwertet.

Damit das Formular im Parent-Viewer ohne verlustbehaftete Markdown-
Rückkonvertierung bearbeitbar bleibt, verwendet es diese stabilen Strukturen:

- Checkboxen werden als `[ ]` beziehungsweise `[x]` geschrieben. Mehrere
  Checkboxen in derselben Zeile bilden eine Auswahlgruppe.
- Einzeilige Listenfelder verwenden `- Feldname: Wert`.
- Kopfdaten verwenden eine zweispaltige Tabelle `Feld | Eintrag`.
- Prüftabellen besitzen eine stabile `ID` und können die Spalten `Ergebnis`
  sowie `Warum/Beleg/Abweichung`, `Notiz` oder `Kommentar` enthalten.

Der Viewer verändert beim Speichern ausschließlich die erkannten Feldspannen.
Freier mehrzeiliger Text und Pipe-Zeichen in Tabellenfeldern werden abgewiesen,
damit Tabellenstruktur und übrige Quelldatei bytegenau erhalten bleiben.

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
Checklisten in Roadmaps. Markierte Checkboxen und ausgefüllte Nachweise im
manuellen Abnahmeformular sind ausdrücklich gültige versionierte Evidenz. Der
Referenzcheck prüft relative Markdown-Links. Die inhaltliche Richtigkeit und
Vollständigkeit bleibt zusätzlich Gegenstand der app-lokalen Reviews und
Tests.
