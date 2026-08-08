# Freigegebene Parent-Umsetzungsaufgaben

Stand: 8. August 2026

Diese Datei enthält ausschließlich freigegebene, noch nicht umgesetzte
Aufgaben des Parent-Repositories. App-Code und app-spezifische Teilaufgaben
bleiben in den Roadmaps der getrennten App-Repositories. Jede
Verhaltensänderung benötigt einen eigenen Auftrag und folgt in den betroffenen
Repositories dem Skill `test-driven-change`.

## PARENT-PRIVACY-ROLLOUT – Datenschutzmigration koordinieren

Status: Architektur festgelegt, Folgeaufträge offen

Normative Quelle:

- [`docs/privacy-architecture.md`](privacy-architecture.md)

Umfang im Parent:

- Migrationsmatrix nach jedem ausdrücklich beauftragten App-Schritt gegen den
  tatsächlichen Code aktualisieren.
- Ausbauetappen, offene Architekturentscheidungen und Providerabdeckung
  nachvollziehbar halten.
- Das Vollständigkeits-/Release-Gate erst nach einem realen Pilot und einer
  realistisch erfüllbaren Migration der bestehenden Apps aktivieren.
- Keine App durch eine leere Providerregistrierung oder reine Checkliste als
  integriert ausweisen.

Abnahmekriterien:

- Jeder schreibende Folgeauftrag nennt die betroffenen getrennten
  Repositories, Rechte-/Datenrisiken, Tests und Rückbaugrenzen ausdrücklich.
- Runtime, Pilot und jede App-Migration folgen den in der normativen Quelle
  beschriebenen kleinen Etappen; App-Code bleibt aus dem Parent heraus.
- Die Matrix unterscheidet verifiziert, geplant, teilweise und implementiert
  und bezeichnet keine geplante Funktion als vorhanden.
- Ein späteres Gate besitzt Provider- und Consumer-Nachweise und wird nicht
  eingeführt, solange bekannte Apps den Vertrag noch nicht realistisch
  erfüllen können.

## PARENT-DOC-REFS – Technische Dokumentreferenzen prüfen

Status: bereit zur Umsetzung

Umfang:

- Eindeutig maschinenlesbare Klassen-, Background-Job-, Skript- und
  Repositorypfade aus Betriebsdokumentation gegen die technische Quelle
  prüfen.
- Bereits vorhandene Versions-, Release- und Skill-Synchronisationschecks
  wiederverwenden und nicht duplizieren.
- Semantische Aussagen, Roadmap-Inhalte und frei formulierte Beispiele nicht
  als vermeintlich exakten technischen Vertrag behandeln.

Abnahmekriterien:

- Ein zunächst roter Fixture-Test belegt mindestens eine veraltete technische
  Referenz und wird durch den kleinsten neuen Check grün.
- Exakte Referenzen blockieren bei Nichtexistenz; heuristisch erkannte
  Referenzen melden zunächst nur eine Warnung mit enger, dokumentierter
  Ausnahme.
- Der Check ist in `scripts/check-fast` eingebunden und erzeugt bei korrekter
  Dokumentation keine Warnungen.

## PARENT-RC-CLEANUP – RC-Bereinigung explizit machen

Status: bereit zur Umsetzung

Umfang:

- Den automatischen Aufruf von
  `scripts/prune-ad-suite-release-candidates.sh` aus dem Release-Builder
  entfernen.
- Die Bereinigung erst nach erfolgreichem Neubau als eigenen, bewusst
  beauftragten Schritt dokumentieren.
- Vor dem Löschen die exakt erkannten Artefakte anzeigen; nur eng validierte
  lokale RC-Namen unter dem ausdrücklich angegebenen `dist`-Root zulassen.

Abnahmekriterien:

- Ein Release-Builder-Test beweist, dass ein normaler RC-Bau ältere RCs nicht
  löscht.
- Pruner-Tests belegen Vorschau, ausdrückliche Ausführung, Erhalt des
  angegebenen RCs und aller finalen Releases sowie Ablehnung ungültiger Labels
  und Ziele.
- Release-Skill und Betriebsdokumentation nennen Löschwirkung,
  Wiederherstellungsgrenze und den getrennten Bereinigungsaufruf.
- `scripts/check-fast` und das saubere AD-Suite-Delivery-Gate sind grün.
