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
| `<app>/docs/manual-acceptance.md` | versionierte, unausgefüllte Prüfvorschrift; lokale Einträge besitzen keinen Freigabestatus |
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

`<app>/docs/manual-acceptance.md` ist ausschließlich die versionierte
Prüfvorschrift. Ein Candidate übernimmt ihren Inhalt und SHA-256-Digest in
seine unveränderliche Protokollbindung. Ausgefüllte Abnahmen werden weder in
diese Datei noch nachträglich in den geprüften Code-Commit geschrieben.

Der lokale Viewer darf die Vorlage für Entwurf, Vorschau oder lokale
Erprobung bearbeiten. Solche Änderungen erzeugen keinen Status und sind keine
Freigabe-Evidence. READY, Candidate-Erzeugung und Staging-Deployment hängen
niemals von einer lokal ausgefüllten Abnahme ab. Für die endgültige Integration
eines abnahmepflichtigen Candidates zählt ausschließlich gültige
Staging-PASS-Evidence aus dem zentralen Evidence-Store.

PASS und FAIL bleiben unveränderlich an Work Order, Candidate, Source-Commit,
Build-Digest, aktives Staging-Deployment sowie Protokollversion und -digest
gebunden. Ein FAIL wird nicht überschrieben; eine Änderung oder erneute
Prüfung benötigt einen neuen Candidate.

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
Checklisten in Roadmaps. Markierte Checkboxen oder lokale Einträge im
manuellen Abnahmeformular sind keine Release- oder Integrations-Evidence. Der
Referenzcheck prüft relative Markdown-Links. Die Staging-Evidence wird vom
separaten Harness gegen ihren Candidate-Vertrag validiert.
