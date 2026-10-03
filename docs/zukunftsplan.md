# Systemweiter Zukunftsplan

Stand: 2. Oktober 2026

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
| FR-01 | P0 | `localbase` und heutige Consumer | Die Kategorie-A/B/C-Migration aus ADR 0001 je eindeutigem Vertrag fortsetzen. Für jeden Pilot und Consumer sind Owner, Installations-, Update-, Deinstallations- und Rückbauverhalten sowie Provider-/Consumer-Contracts nachzuweisen. |
| FR-02 | P0 | Öffentliche Organisations-, Kalender-, Capability- und Katalogverträge | Für jeden Vertrag kleinste öffentliche V1-Grenze, Aktivierungs-/Versionshandshake, Fehlersemantik und additive Kompatibilität entscheiden und testbar machen. Keine zweite Datenquelle einführen. |
| FR-06 | P1 | Groupfolders-Ausnahme der Berechtigungsmatrix | Die private 22.x-Ausnahme nur mit dem in ADR 0003 vorgesehenen Quellkompatibilitäts- und Negativgate fortführen. Unbekannte Versionen bleiben `UNKNOWN`. |
| FR-09 | P2 | künftige externe oder Inter-App-HTTP-APIs | Vor Veröffentlichung prüfen, ob ein Nextcloud-Standard genügt; andernfalls versionierten OCS-/OpenAPI-Vertrag mit Autorisierung, Fehlersemantik und Kombinations-Contracts freigeben. |

## Datenschutz, Berechtigungen und Adminzugriff

| ID | Priorität | Systemweite Aufgabe | Gate |
| --- | --- | --- | --- |
| DP-06 | P1 | Native Files-, Share-, Groupfolders- und Calendar-Rechte zuerst über öffentliche Verträge vervollständigen; Fremd-App-Abdeckung danach ausschließlich read-only inventarisieren. | Fehlende Details bleiben sichtbar `UNKNOWN`, `UNSUPPORTED`, `partial` oder `missing`. |
| DP-07 | P0 | Den implementierten öffentlichen V2-DELETE-Piloten mit technischer Operatoraktivierung für die empfohlenen `adroom`- und Adminhistorien-Policies auf der unterstützten realen Nextcloud-/Datenbankmatrix abnehmen. | Standard bleibt deaktiviert. Migration, Job-Wiederanlauf, Providerkompatibilität, Policy-/Kandidatenintegrität, Holds, Atomarität, Nebenläufigkeit, Backup-/Restore-Prüfstatus und Restore-Quarantäne müssen fail-closed nachgewiesen werden. Rechtsgrundlage, BV, DPO-/BR-Bestätigung und Kundenevidenz bleiben Betreiber-Governance außerhalb des Pakets und sind keine technischen Aktivierungsfelder. Ein globaler Beschäftigten-Lifecycle bleibt davon getrennt. |
| DP-08 | P1 | Die noch fehlenden app-lokalen Processing-Kataloge und die systemweite Coverage-Prüfung in einzelnen, freigegebenen App-Aufträgen vervollständigen. | Fachliche Lücken bleiben `PRIVACY-DECISION-REQUIRED`; Root-Schema und app-lokaler Katalog bleiben die einzige Wertquelle. |
| DP-09 | P1 | Weitere app-eigene Retention-Policies nur mit eindeutigem Trigger, Datenowner, versioniertem V2-Provider, Holds und Wiederherstellungsvertrag ergänzen. | Der auslieferbare Processing-Katalog führt Datenklassen, technische Fristen und offene lokale Entscheidungen. Kundenspezifische Rechtsgrundlagen, Vereinbarungen, Rollenbezeichnungen und Evidenz werden weder hardcodiert noch zum technischen DELETE-Gate gemacht. |
| DP-10 | P1 | Eine mögliche schreibende Matrix-Bedienoberfläche erst nach Erweiterung von [ADR 0003](architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md) (`docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md`) und einer Owner-/Source-of-Truth-Matrix bewerten. | Öffentlicher opt-in Verwaltungsvertrag, Audit, CSRF sowie Allow-/Deny-/Manipulations- und Lifecycle-Tests sind vor jeder Umsetzung erforderlich. |

## Vorgemerkte Suite-Module

Diese Vorhaben sind nicht freigegeben. Vor Arbeit sind Produktgrenze, Owner,
Daten, Rechte, Datenschutz, Runtimegrenze und Releaseweg zu entscheiden.

| ID | Vorhaben | Nächste Entscheidung |
| --- | --- | --- |
| ZM-01 | Schichtvermittlung | Bedarf, Rollen, Zustände, Eskalation und kleinster optionaler Vertrag |
| ZM-02 | DPA-Fallsteuerung | Fachowner, Rechtsgrundlage, Datenklassen, Audit- und Retentionvertrag |
| ZM-03 | Personalbedarfsprognose | Kennzahlen, Quelle, Zeitbezug, Mindestmengen und Fehlinterpretationsschutz |
| ZM-04 | Kapazitäts-/Fallbackplanung | Lokale Erweiterung, gebundelte Bibliothek oder Laufzeit-App gemäß ADR 0001 |
| ZM-05 | Aggregierte Berichte | Empfänger, Granularität, Export, Aufbewahrung und Reidentifikationsrisiko |
| ZM-06 | Suiteweite Lokalisierung | Nach erneuter Freigabe: Pilot-App, Locales, Sprachquelle, Fallback und Rohtext-Gate |
| ZM-07 | Recruitment–BQ-Integration | Minimaler Datenumfang, Zustände, Autorisierung, Wiederholung und Rückbau |

Für alle personalbezogenen Zukunftsmodule gelten vor einer Produktentscheidung:
keine Leistungs- oder Vermittlungsscores, Rankings, Blacklists, Diagnosen,
individuellen Ausfallprognosen oder automatischen Personalentscheidungen.
