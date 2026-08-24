# Freigegebene Parent-Umsetzungsaufgaben

Stand: 23. August 2026

Diese Datei enthält ausschließlich freigegebene, noch nicht umgesetzte
Aufgaben des Parent-Repositories. App-Code und app-spezifische Teilaufgaben
bleiben in den Roadmaps der getrennten App-Repositories. Jede
Verhaltensänderung benötigt einen eigenen Auftrag und folgt in den betroffenen
Repositories dem Skill `test-driven-change`.

## PARENT-PRIVACY-ROLLOUT – Datenschutzmigration koordinieren

Status: Standalone-Zielarchitektur und Runtime umgesetzt; alle sechs
AD-Fachapps und beide BR-Apps besitzen app-lokal getestete
`PersonalDataProvider` und `PermissionProvider`; die laufende
Vollständigkeit, bekannte Eigen-App-Lücken, Retention und das
Vollständigkeits-/Release-Gate bleiben offen

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
- Governance, Maintainerkreis und nachzuweisenden Nextcloud-Zielkorridor der
  angelegten Kategorie-B-Standalone-App als eigene Entscheidungen
  vorbereiten.
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

1. **Umgesetzt:** Produktname `Data Protection Center`/`Datenschutz-Center`,
   App-ID `filzmann_data_protection`, AGPL-Lizenz und eigenes Repository sind
   entschieden; die App wurde mit `create-nextcloud-app` angelegt.
2. **Umgesetzt:** Vertragsversion, Coverage-Modell,
   Contract-Test-Kit, lazy Event-Registrierung und sitzungsgebundene
   Self-Service-API im Standalone-Repository sowie der erste reale
   `adroom`-Consumer. Der aktive und deaktivierte Stand wurde in lokaler
   Nextcloud-Laufzeit geprüft. Der opt-in DDEV-Check
   `scripts/check-privacy-app-compatibility` belegt am unabhängigen
   Matrix-Consumer zusätzlich eine physisch fehlende sowie eine von
   Nextcloud real als inkompatibel abgewiesene Privacy-App samt Rückbau. Jeder
   weitere Consumer behält seinen eigenen Vertrags- und Startnachweis.
3. **Offenes Entscheidungsgate:** Governance, Maintainerkreis und
   Nextcloud-Minimalversion vor einem öffentlichen Release freigeben.
4. **Umgesetzt:** Cross-Repository-Pilot für Standalone-App, unveränderten
   LocalBase-Regressionspfad und genau einen Pilotconsumer (`adroom`).
5. **Umgesetzt:** `filzmann_permission_matrix` als zweiten Provider mit
   subjectgebundener, datensparsamer Projektion und optionaler Runtimegrenze
   angebunden sowie lokal in Nextcloud verifiziert.
6. **Umgesetzt:** Die sechs AD-Fachapps und zwei BR-Apps liefern ihre
   personenbezogenen Nextcloud-UID-Bezüge über den Privacy-V1-Vertrag und
   ihre kanonischen Fachberechtigungen über den Permission-V1-Vertrag. Der
   Parent-Vertragstest lädt alle acht Berechtigungsprovider gegen die echten
   öffentlichen V1-Klassen.
7. **Nächste Eigen-App-Aufgaben:** LocalBase-eigene persönliche UI-Werte und
   Demo-Registry vollständig inventarisieren; für
   `filzmann_data_protection` die Nichtpersistenz eigener Subjectdaten sowie
   den erforderlichen Berechtigungsprovider belegen; begründete
   Nichtanwendbarkeit von OrgSuite bei Scopeänderungen neu prüfen.
8. **Danach – native Nextcloud-Rechte:** Files einschließlich Shares,
   Groupfolders, Files Access Control und externen Speichern sowie Calendar
   zuerst über öffentliche Schnittstellen vervollständigen. Eine aktivierte
   App ohne Gruppenbeschränkung wird für alle erfassten Gruppen als verfügbar
   ausgewiesen; App-Verfügbarkeit und Detailabdeckung bleiben getrennt.
   Dateiinhalte und Kalender-/Termininhalte bleiben ausgeschlossen.
9. **Anschließend – Fremd-App-Coverage:** weitere installierte fremde Apps und
   ihre offiziellen Schnittstellen read-only inventarisieren. Adapter,
   Upstream-Anfragen und versionsgebundene Ausnahmen bleiben je App eigene
   Entscheidungen. Fehlende oder inkompatible Verträge bleiben sichtbar;
   unsichere Fallbacks sind ausgeschlossen.
10. Retention-Ausführung, Lifecycle und das Runtime-Coverage-/Release-Gate
   bleiben getrennt genehmigungspflichtig.

## PARENT-PROVIDER-CONTINUITY – Provider bei Eigen-App-Änderungen mitpflegen

Status: als dauerhafte Cross-App-Regel freigegeben; AD-/BR-Ausgangsstand und
Permission-V1-Integrationsvertrag sind grün, übrige Eigen-App-Lücken bleiben
in der vorstehenden Reihenfolge offen

- Jede Änderung an personenbezogenen Daten, Subject-Typen, Nebenspeichern,
  Fachberechtigungen oder Scopes aktualisiert im selben App-Auftrag das
  Inventar, den anwendbaren Provider und seine Vertrags-/Negativtests.
- Neue Apps treffen die Providerentscheidung beim Scaffold und liefern den
  Provider spätestens mit dem ersten betroffenen Feature; ein bloßer
  Roadmap-Eintrag ist kein Fertigstellungsnachweis.
- Begründete Nichtanwendbarkeit ist zulässig, wird aber aus dem App-Zweck
  hergeleitet und bei Scopeänderungen erneut geprüft.
- Bekannte Auslassungen bleiben `partial`, `UNKNOWN`, `UNSUPPORTED` oder
  `missing`; sie werden nicht als vollständige Daten- oder Rechteabdeckung
  ausgegeben.

## PARENT-IKT-PRIVACY-PORTFOLIO – Berechtigungsmatrix neu zuordnen

Status: Produktdomäne, Ziel-ID `filzmann_permission_matrix`, technische
Entkopplung von OrgSuite und eigenständige Navigation umgesetzt

Normative Quelle:

- [`ADR 0003`](architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md)
  (`docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md`)

Umfang im Parent:

- `filzmann_permission_matrix` als eigenständige Kategorie-B-App dem Portfolio
  IKT/Datenschutz zuordnen und nicht mehr als Gremien-Arbeits-App führen.
- Eigenständigkeit gegenüber Privacy-App und OrgSuite, Datenownership,
  Providerpriorität und offene Navigationsentscheidungen sichtbar
  halten.
- Die weiterhin offene Providerabdeckung und Retention nicht durch die
  umgesetzte Identitäts- und Navigationsänderung als gelöst darstellen.

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
