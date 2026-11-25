# Unverbindliche Learning Candidates

Stand: 25. November 2026

Diese Datei enthält ausschließlich offene, noch nicht entschiedene Learning
Candidates. Nach einer Entscheidung wird der Candidate entfernt.
Freigegebene Umsetzungsaufgaben werden ausschließlich im tatsächlich
zuständigen Repository gepflegt. Relevante abgeschlossene Entscheidungen
werden in einem datierten Änderungsbericht dokumentiert. Bewertung und
Verschiebung folgen dem Skill `evaluate-learning-candidate`.

## Offene Candidates

### CDP-Browser-Smoke als wiederverwendbarer Baustein für alle registrierten App-Repos

Mögliches Learning:
- Ebene: Parent (gemeinsamer Testbaustein, perspektivisch `localbase/tests/Support` oder Parent-`scripts/`)
- Ziel: Script/Test-Vertrag – heute app-lokal in `flzplaner/tests/browser-ddev-smoke.sh` + `flzplaner/tests/js/browser-ddev-smoke.mjs` sowie adaptiert in `flz_akut/tests/browser-ddev-smoke.sh` + `flz_akut/tests/js/browser-ddev-smoke.mjs`
- Status: verifiziert (flzplaner gegen laufendes DDEV, selbstbereinigend; flz_akut gegen laufendes DDEV mit Overlay-/Nachtschicht-E2E)
- Evidenz: `flzplaner/tests/browser-ddev-smoke.sh` startet headless Chrome (`--remote-debugging-port=0`), das mjs steuert per CDP (WebSocket, `Runtime.evaluate`/`Page.navigate`) Login und App-Flow gegen `https://nextcloud-dev.ddev.site`; `localbase/tests/Support/js-runner.mjs` ist bereits ein gemeinsamer JS-Test-Runner
- Grund: Jede App mit Web-UI braucht denselben authentifizierten DDEV-Browser-Smoke (Login, CSRF, DOM, API-E2E); die CDP-Client-Logik liegt derzeit app-lokal und wird kopiert
- Vorgeschlagener Inhalt: gemeinsamer CDP-Client + Chrome-Starter als wiederverwendbarer, dependency-freier Test-Helper (Node ≥ 22, globaler WebSocket, host-seitiger Chrome), den alle registrierten App-Repos per opt-in-Skript (nicht im schnellen Standardlauf) aufrufen; app-spezifische Flows bleiben app-lokal. Cross-App-Lesezugriffe auf fremde Repo-Dateien bleiben verboten, daher Extraktion in einen gemeinsamen Baustein statt Referenz.
