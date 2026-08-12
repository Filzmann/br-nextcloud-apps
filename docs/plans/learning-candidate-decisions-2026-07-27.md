# Änderungsbericht: Learning-Candidate-Entscheidungen

Stand: 27. Juli 2026
Status: abgeschlossen und historisch

## Zweck

Dieser Bericht dokumentiert die am 27. Juli 2026 abgeschlossenen
Learning-Candidate-Entscheidungen. Er ist keine aktive Aufgabenliste.
Offene Candidates stehen ausschließlich in `docs/learning-candidates.md`;
freigegebene Umsetzungen werden im jeweils zuständigen Repository geführt.

## In Aufgaben verschobene Candidates

| Learning | Repository-eigene Aufgaben |
| --- | --- |
| Kanonischer AD-Produkt- und Menükatalog einschließlich AD Recruitment | Parent `PARENT-AD-CATALOG`; LocalBase `LB-AD-CATALOG`; OrgSuite `ORGS-AD-CATALOG`; AD Recruitment `RECR-AD-CATALOG`; AD-Suite-Dokumentation `ADS-AD-CATALOG-DOCS` |
| Berechtigungsmatrix aus Organisationssnapshot, globale App-Nutzung und konkrete Ordnerrechte | LocalBase `LB-AD-ORG-SNAPSHOT`; Berechtigungsmatrix `BPM-AD-ORG-SNAPSHOT` und `BPM-FOLDER-RIGHTS` |
| Gemeinsamer BR-Gruppenvertrag | LocalBase `LB-BR-GROUPS`; BRTop `BRT-BR-GROUPS`; BRStunden `BRS-BR-GROUPS` |
| Administrierbare AD-Kalenderdefaults | AD Kalender `ADC-ADMIN-DEFAULTS` |
| BR-Dokumentstammdaten und versionierte Vorlagen | BRTop `BRT-DOCUMENT-CONFIG`; BRStunden `BRS-DOCUMENT-CONFIG` |
| Locale-fähige Datumsnamen und Nextcloud-l10n | die lokale `*-L10N`-Aufgabe in jedem App-Repository |
| Technische Dokumentreferenzen prüfen | Parent `PARENT-DOC-REFS` |
| Explizite RC-Bereinigung | Parent `PARENT-RC-CLEANUP` |

Die damalige Einordnung der L10N-Beobachtung als lokale App-Aufgaben wurde am
9. August 2026 aufgehoben. Der appübergreifende L10N-Rollout ist seither nur
in `docs/ad-suite-zukunftsplanung.md` vorgemerkt, nicht freigegeben und nicht
zu implementieren. Lokale `*-L10N`-Einträge begründen keine freigegebene
Aufgabe und sind bei der nächsten ausdrücklich beauftragten Roadmap-Pflege in
dieselbe Zukunftsplanung einzuordnen.

Die Parent-Aufgaben stehen in `docs/implementation-tasks.md`. App-Aufgaben
stehen jeweils in der lokalen `ROADMAP.md`; die AD-Suite-Roadmap enthält die
Produktdokumentationsaufgabe und eine Routingübersicht. Die Verschiebung ist
keine Implementierung, Commit- oder Releasefreigabe. Jede Aufgabe benötigt
weiterhin einen eigenen Auftrag, die lokalen Stop-Gates und die angegebenen
Tests.

## Ohne neue Aufgabe eingeordnete Beobachtungen

| Beobachtung | Entscheidung und dauerhafte Quelle |
| --- | --- |
| Kalenderkontext und gemeinsamer Ferien-/Feiertagsvertrag | Kein Candidate; der vorhandene Arbeitsstand benötigt Abnahme. Code, Tests und Abnahmeergebnis bilden den Nachweis. |
| Providerendpunkte, HTTPS-/SSRF-Grenzen und Runtime-Sicherheitslimits | Kein Candidate; bereits bestehende Code- und Sicherheitsverträge bleiben die maßgebliche Quelle. |
| Externe OrgSuite-Links | Kein Candidate; das zuständige Roadmapziel bleibt die Aufgabenquelle. |
| Konfigurierbare Organisations-, Schicht- und Sitzungsdefaults | Kein Candidate; vorhandene Bootstrapkonfiguration und ihre Tests bleiben die maßgebliche Quelle. |
| Einzelner verbleibender Hardcode | Kein dauerhaftes Learning; als konkreter Code-/Testbefund nur im zuständigen Arbeitskontext zu behandeln. |

Diese Einordnungen werden nicht erneut in
`docs/learning-candidates.md` geführt.
