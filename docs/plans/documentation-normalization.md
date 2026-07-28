# Ausführungsplan: Dokumentations- und Steuerungsnormalisierung

Stand: 27. Juli 2026
Status: abgeschlossen und historisch; Anwendungscode war ausdrücklich nicht
Bestandteil dieses Laufs.

## Ziel

Verbindliche Aussagen werden ohne Bedeutungs- oder Stärkeverlust jeweils
einer kanonischen Quelle zugeordnet. Roadmaps enthalten nur Zukunft,
Learning Candidates bleiben unverbindlich und historische Pläne bleiben
historisch.

## Scope

- Parent- und App-`AGENTS.md`
- Parent- und lokale `SKILL.md`
- `README.md`, Roadmaps und Architekturdokumentation
- öffentliche AD-Suite-Betriebs- und Releaseunterlagen
- keine PHP-/JavaScript-/CSS-/Template-/Migrationsänderungen
- keine Änderungen an Prüf-, Erzeugungs-, Installations- oder Release-Skripten
- keine DDEV-, `occ`-, Netzwerk-, Commit-, Push- oder Releaseaktion

## Erhaltungsregeln

1. Die freigegebene Inhaltsmatrix des Read-only-Audits ist die
   Zuordnungsgrundlage.
2. Normative Stärke wird nicht reduziert.
3. App-spezifische Fach-, Rechte-, Zustands- und Sicherheitsinvarianten bleiben
   im jeweiligen App-Repository.
4. Gemeinsame Regeln bleiben für einen direkten App-Start vollständig lokal
   auflösbar.
5. Bestehende uncommitted Fachänderungen in LocalBase, AD Kalender, AD Urlaub
   und AD Raumplaner werden in der Dokumentationsmigration erhalten.
6. Zum damaligen Stand offene Learning Candidates wurden ausschließlich in
   `docs/learning-candidates.md` konserviert und nicht als Regeln übernommen.

## Abschlusskriterien

- Root- und App-Einstiege sind eindeutig.
- Es gibt keine als `Learnings` bezeichneten bereits geltenden Verträge.
- Roadmaps enthalten keinen Abschnitt `Umgesetzt`.
- Zustandsmodell- und Migrationsregeln gelten auch bei direktem App-Start.
- Lokale Skillkopien bleiben bytegleich.
- `scripts/check-workspace-structure` und `scripts/check-fast` sind grün.
- Alle betroffenen Repositories bestehen `git diff --check`; vollständige
  Status- und Änderungsliste werden berichtet.

## Nachgelagerte Entscheidung

Die acht Learning Candidates wurden am 27. Juli 2026 einzeln freigegeben und
in konkrete repository-eigene Roadmap-Aufgaben verschoben. Der
Verschiebungsnachweis steht im datierten Änderungsbericht
`docs/plans/learning-candidate-decisions-2026-07-27.md`, die Parent-Aufgaben
in `docs/implementation-tasks.md`. Diese spätere Entscheidung ändert den
historischen Scope dieses Normalisierungslaufs nicht und erteilt weiterhin
keine pauschale Implementierungs-, Commit- oder Releasefreigabe.
