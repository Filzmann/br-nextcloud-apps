# Systemweiter Zukunftsplan

Stand: 27. November 2026

Diese Datei ist die einzige aktive systemweite Planungsquelle des Workspaces.
Sie enthält ausschließlich offene, blockierte oder vor einer Umsetzung noch zu
entscheidende systemweite Arbeit. Architekturentscheidungen, Betriebsanleitungen,
implementierter Umfang, Nachweise und abgeschlossene Planstände bleiben in ihren
jeweiligen kanonischen Quellen.

Normative Quellen sind insbesondere `AGENTS.md`,
[`docs/architecture.md`](architecture.md),
[`docs/privacy-architecture.md`](privacy-architecture.md) und die ADRs unter
[`docs/architecture-decisions/`](architecture-decisions/). App-spezifische
Aufgaben gehören ausschließlich in das `ROADMAP.md` der zuständigen App.

## Planungsgrenzen

| Inhalt | Kanonische Ablage |
| --- | --- |
| offene Plattform-, Cross-App-, Suite-, Delivery- und Rolloutarbeit | diese Datei |
| app-spezifische Produktarbeit | `ROADMAP.md` der zuständigen App |
| dauerhafte Architekturentscheidung | ADR oder Architekturdokument |
| Arbeits- und Sicherheitsregel | `AGENTS.md` oder Skill |
| implementierter Umfang und abgeschlossene Änderung | App-`README.md`, `CHANGELOG.md`, Code, Tests und Git-Historie |
| noch unbewertete Beobachtung | `docs/learning-candidates.md` |

`offen` bedeutet planbar, aber nicht automatisch freigegeben. `blockiert`
benennt ein fehlendes Entscheidungs- oder Nachweisgate. Ein konkreter Auftrag
für jedes betroffene Repository bleibt erforderlich.

## Offene Future-Readiness

| ID | Priorität | Grenze | Nächster Schritt / Gate |
| --- | --- | --- | --- |
| FR-01 | P0 | `localbase` und heutige Consumer | **Erledigt 28.11.2026:** Kategorie-A/B/C-Migration abgeschlossen, alle Consumer migriert, LocalBase-Pilotcode zurückgebaut, Test-Harness in den Parent (`test-harness/`) verschoben, GitHub-Repo archiviert (Decision Simon: Harness in Parent, Repo archivieren). Siehe datierter Änderungsbericht 2026-11-28. |
| FR-02 | P0 | Öffentliche Organisations-, Kalender-, Capability- und Katalogverträge | Für jeden Vertrag kleinste öffentliche V1-Grenze, Aktivierungs-/Versionshandshake, Fehlersemantik und additive Kompatibilität entscheiden und testbar machen. Keine zweite Datenquelle einführen. |
| FR-06 | P1 | Groupfolders-Ausnahme der Berechtigungsmatrix | **Erledigt 28.11.2026 (Decision Simon):** 22.x-Ausnahme endgültig entfernt, ADR-0003-konform; unbekannte Versionen bleiben sichtbar `UNKNOWN`. |
| FR-09 | P2 | künftige externe oder Inter-App-HTTP-APIs | **Policy entschieden 28.11.2026 (Simon):** Nextcloud-Standard zuerst; nur wenn keiner genügt, versionierter OCS-/OpenAPI-Vertrag mit Autorisierung, Fehlersemantik und Kombinations-Contracts. |

## Datenschutz, Berechtigungen und Adminzugriff

| ID | Priorität | Systemweite Aufgabe | Gate |
| --- | --- | --- | --- |
| DP-06 | P1 | Native Files-, Share-, Groupfolders- und Calendar-Rechte zuerst über öffentliche Verträge vervollständigen; Fremd-App-Abdeckung danach ausschließlich read-only inventarisieren. | **Freigegeben 28.11.2026 (Simon):** Fremd-App-Abdeckung read-only inventarisieren. Fehlende Details bleiben sichtbar `UNKNOWN`, `UNSUPPORTED`, `partial` oder `missing`. |
| DP-08 | P1 | Die noch fehlenden app-lokalen Processing-Kataloge und die systemweite Coverage-Prüfung in einzelnen, freigegebenen App-Aufträgen vervollständigen. | Fachliche Lücken bleiben `PRIVACY-DECISION-REQUIRED`; Root-Schema und app-lokaler Katalog bleiben die einzige Wertquelle. |
| DP-09 | P1 | **Beauftragt 28.11.2026 (Simon), als Nächstes:** Retention-V2-Provider mit Holds und Wiederherstellung bauen; weitere app-eigene Retention-Policies nur mit eindeutigem Trigger, Datenowner, versioniertem V2-Provider, Holds und Wiederherstellungsvertrag ergänzen. | Der auslieferbare Processing-Katalog führt Datenklassen, technische Fristen und offene lokale Entscheidungen. Kundenspezifische Rechtsgrundlagen, Vereinbarungen, Rollenbezeichnungen und Evidenz werden weder hardcodiert noch zum technischen DELETE-Gate gemacht. |
| DP-10 | P1 | Schreibende Matrix-Bedienoberfläche nach Erweiterung von [ADR 0003](architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md) (`docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md`) und einer Owner-/Source-of-Truth-Matrix umsetzen (freigegeben 27.11.2026, siehe `docs/change-report-2026-11-27.md`). | Öffentlicher opt-in Verwaltungsvertrag, Audit, CSRF sowie Allow-/Deny-/Manipulations- und Lifecycle-Tests sind vor jeder Umsetzung erforderlich. |

## Vorgemerkte Suite-Module

Diese Vorhaben sind nicht freigegeben. Vor Arbeit sind Produktgrenze, Owner,
Daten, Rechte, Datenschutz, Runtimegrenze und Releaseweg zu entscheiden.

| ID | Vorhaben | Nächste Entscheidung |
| --- | --- | --- |
| ZM-03 | Personalbedarfsprognose (flz_prognose) | **Beauftragt 28.11.2026 (Simon), als Nächstes nach DP-09:** Kennzahlen, Quelle, Zeitbezug, Mindestmengen und Fehlinterpretationsschutz festlegen. |
| ZM-04 | Kapazitäts-/Fallbackplanung (flz_kapazitaet) | **Entschieden 28.11.2026 (Simon):** Kategorie-B-eigene App gemäß ADR 0001 (Vorschlag freigegeben); Kopplung zu anderen Apps ausschließlich über öffentliche V1-Verträge. |
| ZM-05 | Aggregierte Berichte (flz_berichte) | **Datenschutzkonzept verbindlich entschieden 28.11.2026 (Simon):** Empfänger = Gruppen mit Berichtspflicht; k≥5-Mindestschwelle pro Aggregationszelle; Export nur PDF/CSV direkt im Browser (kein Massenexport/E-Mail); keine Persistenz (pro Aufruf neu berechnet); Audit-Log nur Berichtstyp + Empfängergruppe. |
| ZM-07 | Recruitment–BQ-Integration | **Entschieden 28.11.2026 (Simon):** minimaler einseitiger Vertrag beibehalten — recruitment schreibt in `bq_hiring_event_inbox` (flzbqplanung), read-only, kein Rückkanal. |
| ZM-08 | BR-Sprechstundenbuch | **Umgesetzt bis v0.1.0-dev.9 (28.11.2026):** Datenmodell, 7 konfigurierbare Bereiche, fail-closed BR-Rechtevertrag, Art. 6 Abs. 1 lit. c + Retention (Amtszeitende + 1 Jahr, RetentionJob) plus manuelles Löschrecht (dev.9), Sprechstundenbericht an brtop (V1); Store-Release manuell durch Simon. |

ZM-02 (DPA-Fallsteuerung) ist als erster Pilot freigegeben (27.11.2026) und wird
im zuständigen App-Repository `flz_dpa` geführt; ZM-06 (Suiteweite
Lokalisierung) ist entschieden — siehe `docs/change-report-2026-11-27.md`.
ZM-08 (BR-Sprechstundenbuch) wurde am 28.11.2026 beauftragt und wird im eigenen
App-Repository `brsprechstundenbuch` geführt (Scaffold auf main, fachliche
Funktionen nach `brsprechstundenbuch/ROADMAP.md`).

Für alle personalbezogenen Zukunftsmodule gelten vor einer Produktentscheidung:
keine Leistungs- oder Vermittlungsscores, Rankings, Blacklists, Diagnosen,
individuellen Ausfallprognosen oder automatischen Personalentscheidungen.
