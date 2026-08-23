# Freigegebene Parent-Umsetzungsaufgaben

Stand: 23. August 2026

Diese Datei enthält ausschließlich freigegebene, noch nicht umgesetzte
Aufgaben des Parent-Repositories. App-Code und app-spezifische Teilaufgaben
bleiben in den Roadmaps der getrennten App-Repositories. Jede
Verhaltensänderung benötigt einen eigenen Auftrag und folgt in den betroffenen
Repositories dem Skill `test-driven-change`.

## PARENT-PRIVACY-ROLLOUT – Datenschutzmigration koordinieren

Status: Standalone-Zielarchitektur festgelegt; Name/App-ID und
Cross-Repository-Migration noch nicht freigegeben

Normative Quelle:

- [`docs/privacy-architecture.md`](privacy-architecture.md)
- [`docs/privacy-provider-guide.md`](privacy-provider-guide.md)
- [`ADR 0002`](architecture-decisions/0002-standalone-privacy-platform.md)

Umfang im Parent:

- Migrationsmatrix nach jedem ausdrücklich beauftragten App-Schritt gegen den
  tatsächlichen Code aktualisieren.
- Ausbauetappen, offene Architekturentscheidungen und Providerabdeckung
  nachvollziehbar halten.
- Das Vollständigkeits-/Release-Gate erst nach einem realen Pilot und einer
  realistisch erfüllbaren Migration der bestehenden Apps aktivieren.
- Keine App durch eine leere Providerregistrierung oder reine Checkliste als
  integriert ausweisen.
- Neutralen Namen, freie App-ID, Governance, Lizenz und Repository der
  Kategorie-B-Standalone-App als eigene Entscheidung vorbereiten. Vor dieser
  Freigabe wird keine neue App angelegt.
- Version 1 des öffentlichen Providervertrags mit Descriptor,
  Versionshandshake, Cursor-Paging, Coverage-Profil und Contract-Test-Kit aus
  dem verifizierten LocalBase-Pilot ableiten.
- Migration mit synthetischem Referenzprovider und danach genau einem realen
  Consumer planen; keine parallele Cross-App-Umstellung.
- LocalBase-Rückbau erst planen, wenn alle vorgesehenen Consumer migriert und
  Installation, Update, Deinstallation sowie Rückbau geprüft sind.

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
- Fehlende, deaktivierte und inkompatible Privacy-App sowie fehlende Provider
  ergeben kontrollierte Status. SQL-, Reflection-, Datei-, AppConfig- oder
  `IUserMigrator`-Fallbacks bleiben ausgeschlossen.

Umsetzungsreihenfolge und Freigabegates:

1. **Sofort planbar, Parent-only:** öffentliche Vertragsversion,
   Coverage-Modell, Contract-Test-Kit und neutrale Namens-/App-ID-Kandidaten
   ausarbeiten.
2. **Entscheidungsgate:** Name, App-ID, Repository, Lizenz, Governance und
   Nextcloud-Minimalversion ausdrücklich freigeben.
3. **Danach neuer App-Auftrag:** Standalone-App ausschließlich mit
   `create-nextcloud-app` anlegen; noch keine Fachapp migrieren.
4. **Cross-Repository-Gate:** Risiko, Dateien, Tests und Rückbau für
   Standalone-App, LocalBase und genau einen Pilotconsumer freigeben.
5. **Appweise Folgeaufträge:** weitere Provider einzeln migrieren; spätere
   Retention-Ausführung und Lifecycle bleiben getrennt genehmigungspflichtig.

## PARENT-IKT-PRIVACY-PORTFOLIO – Berechtigungsmatrix neu zuordnen

Status: Produktdomäne und Ziel-ID `filzmann_permission_matrix` festgelegt;
technische Entkopplung und App-Migration noch nicht begonnen

Normative Quelle:

- [`ADR 0003`](architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md)
  (`docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md`)

Umfang im Parent:

- die heutige `br_permission_matrix` als eigenständige Kategorie-B-App dem
  Portfolio IKT/Datenschutz zuordnen, nicht mehr als Gremien-Arbeits-App
  planen und später in die Ziel-ID `filzmann_permission_matrix` überführen.
- Eigenständigkeit gegenüber Privacy-App und OrgSuite, Datenownership,
  Providerpriorität und offene Navigationsentscheidungen sichtbar
  halten.
- Keine thematische Zuordnung als umgesetzte Navigation, Providerabdeckung,
  Rechteänderung oder App-Umbenennung darstellen.

Freigabegates für spätere App-Arbeit:

1. Vor der Umsetzung bestätigen, dass außer dem als wegwerfbar benannten
   Stagingstand kein erhaltenswerter Bestand oder veröffentlichter Vertrag
   zur Alt-ID existiert.
2. App-Auftrag für Erreichbarkeit ohne OrgSuite und neutrale Navigation mit
   Allow-/Deny-, Standalone-, UI- und Rückbautests freigeben.
3. Separaten Providerauftrag für Snapshot-, Export-, Audit- und optionale
   Benutzerlistenbezüge freigeben.
4. Retention erst nach Entscheidung zu Beweiswert, Auditaufbewahrung und
   mengenbasierter Snapshot-Historie über Dry Run hinaus erweitern.
5. Einen optionalen Portfolioadapter erst nach stabiler Standalone- und
   Privacy-Vertragsversion bewerten.

## PARENT-RC-CLEANUP – RC-Bereinigung explizit machen

Status: im aktuellen Arbeitsstand umgesetzt und im Parent-Schnelltest grün;
sauberes Delivery-Gate durch den separaten Befund
`adrecruitment/lib/Service/StatusMailService.php` blockiert

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
