# Änderungsbericht 2026-11-27

Dokumentiert Entscheidungen und Umsetzungen vom 27. November 2026, die aus
`docs/zukunftsplan.md` bzw. `docs/learning-candidates.md` entfernt wurden.

## DP-11 — Temporärer Admin-Vollzugriff als Overlay gebündelt (umgesetzt)

Die app-lokalen Funktionen für temporären Admin-Vollzugriff (ADR 0004) wurden
in den Fachapps jeweils in einem zusammengehörigen, zugänglichen Overlay
gebündelt. Umsetzung über das neue Kategorie-A-Browser-Artefakt `overlay.js`
(`Overlay` + `AdminAccessOverlay`) aus `flz_shared` (Commit `0475be8`), das in
die app-lokalen `js/shared/`-Bundles aller betroffenen Apps übernommen wurde
mitsamt aktualisiertem `flz-shared.lock.json`. Betroffene Apps: brtop,
flzplaner, brstunden, flz_permission_matrix, flzurlaub, flzroom,
flzrecruitment, flzbqplanung, flz_data_protection, flzcalendar. Die Bündelung
verändert weder Daten- und Auditquellen noch Rollen, Höchstdauer oder
serverseitige Prüfungen aus ADR 0004; Tastatur-, Fokus-, Allow-/Deny-, CSRF-
und Manipulationsnachweise liegen in den erweiterten AdminFullAccessContract-
 bzw. admin-access-smoke-Tests der jeweiligen App.

## ZM-06 — Suiteweite Lokalisierung (entschieden)

Entscheidung: keine eigene Lokalisierungs-App, weil Nextcloud-l10n app-scoped
ist (Übersetzungsdomain = App-ID, Kataloge in `<app>/l10n/`). Sprachquelle
Deutsch (Fallback, auch bei admin/admin), Kataloge `l10n/en.*` und
`l10n/en_GB.*` je App mit englischer Übersetzung als Bonus. Alle Apps
(einschließlich `flzcalendar` und `flz_organization`) sind auf DE-Quelltexte
umgestellt; das Rohtext-Gate je App erzwingt
`<app>/tests/Ui/L10nCatalogContractTest.php`. Der verbleibende Rollout in den
BR-Apps (BRS-L10N, FLZU-L10N) wird in den jeweiligen App-`ROADMAP.md` geführt.

## ZM-02 — DPA-Fallsteuerung (Pilot freigegeben)

ZM-02 startet als erster Pilot im App-Repository `flz_dpa`. Fachowner,
Rechtsgrundlage, Datenklassen, Audit- und Retentionvertrag werden im
app-lokalen Auftrag entschieden; fehlende fachliche Festlegungen bleiben
`PRIVACY-DECISION-REQUIRED` und werden nicht durch technische Defaults ersetzt.

## DP-10 — Schreibende Matrix-Bedienoberfläche (freigegeben)

ADR 0003 wird um den schreibenden opt-in Verwaltungsvertrag erweitert und eine
Owner-/Source-of-Truth-Matrix erstellt; danach Umsetzung in
`flz_permission_matrix` mit öffentlichem Vertrag, Audit, CSRF sowie
Allow-/Deny-/Manipulations- und Lifecycle-Tests.

## Learning Candidate CDP-Browser-Smoke (freigegeben)

Der gemeinsame CDP-Browser-Smoke wird als opt-in-Testbaustein im Parent
geführt (`scripts/browser-ddev-smoke.sh` + `scripts/js/cdp-browser.mjs`);
App-Repos rufen ihn mit ihrem app-spezifischen Flow-Modul auf, ohne im
schnellen Standardlauf zu laufen. App-spezifische Flows bleiben app-lokal.
