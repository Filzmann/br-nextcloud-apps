# AGENTS.md – BR Nextcloud Apps

## Zweck und Routing

Dieses Repository ist der Parent-/Meta-Workspace für DDEV, gemeinsame
Dokumentation, app-übergreifende Verträge und Workspace-/Delivery-Prüfungen.
Es enthält keinen deploybaren App-Code.

- Menschlicher Einstieg: `README.md`
- Workspace und DDEV: `docs/workspace.md`
- App-übergreifende Architektur: `docs/architecture.md`
- Wiederholbare Abläufe: `.agents/skills/`
- Repositoryinventar: `config/workspace-repositories.tsv`
- Unverbindliche Beobachtungen: `docs/learning-candidates.md`

Jede App und `ad-suite` sind getrennte Git-Repositories mit eigener
`AGENTS.md`. Normale App-Arbeit beginnt im Root des konkret beauftragten
Repositories und folgt dem dort lokal mitgeführten Skill
`work-in-nextcloud-app`. Der technische `workspace-write`-Zugriff erteilt
keine fachliche Schreibfreigabe.

## Harte Repositorygrenzen

- Im Parent werden nur Meta-Dokumentation, DDEV-/Workspace-Konfiguration,
  app-übergreifende Regeln, Delivery-Skripte und zugehörige Tests gepflegt.
- App-Code und Produktdokumentation werden im Parent weder geändert noch
  getrackt, gestaged oder committed. Änderungen an getrennten Repositories
  brauchen einen ausdrücklichen Auftrag für jedes betroffene Repository.
- Schreibende Cross-App-Arbeit ist ein ausdrücklich benannter Sonderlauf mit
  einzeln genannten Repositories, lokalen Regeln, Statusprüfungen und Tests.
- Vor Arbeit in einem App-Repository werden dessen vollständige `AGENTS.md`,
  lokal referenzierte Skills und `git status --short` gelesen.
- Jede deploybare App besitzt ein eigenes Git-Repository, eine eigene
  `AGENTS.md`, `.gitignore` und lokal auflösbare Steuerung. Neue Apps werden
  ausschließlich mit dem Skill `create-nextcloud-app` registriert.
- `config/workspace-repositories.tsv` ist die einzige manuell gepflegte
  Repositoryliste. Andere Inventare werden daraus erzeugt oder dagegen
  geprüft.
- Keine Submodule, solange Simon sie nicht ausdrücklich entscheidet.

## Unverzichtbare Architektur- und Sicherheitsgrenzen

Die vollständigen Verträge stehen in `docs/architecture.md` und für direkte
App-Arbeit selbstständig im lokalen Skill `work-in-nextcloud-app`.

- Controller bleiben dünn; Fachlogik, Datenzugriff, Darstellung,
  Dokumenterzeugung und Dateiablage bleiben getrennt.
- Gemeinsamer Code wird erst extrahiert, wenn mindestens zwei Apps ihn
  semantisch gleich benötigen und der Vertrag app-übergreifend testbar ist.
  LocalBase bleibt klein und dependency-arm.
- Deny by default, Least privilege und server-side first sind harte
  Anforderungen. Navigation oder UI-Sichtbarkeit erteilen niemals Rechte.
- Nextcloud-native Gruppen-, Benutzer-, Session-, AppConfig-, Share-, Datei-,
  Capability-, Konfigurations- und Request-Mechanismen sind verbindlich zu
  verwenden. Ein paralleler eigener Mechanismus ist nur zulässig, wenn die
  native Möglichkeit nachweislich nicht ausreicht, die Abweichung als
  begründete Architekturentscheidung dokumentiert ist, Auswirkungen auf
  Berechtigungen, Migration, Wartung und Interoperabilität geprüft sind und
  die Entscheidung vor der Implementierung freigegeben wurde. Fehlt eine
  Voraussetzung, muss Codex vor der Implementierung stoppen.
- Jeder relevante Controller, API-Endpunkt, Servicepfad sowie jede Datei- und
  Datenoperation prüft Akteur, Scope und konkrete Berechtigung serverseitig.
- Requests werden validiert und typisiert, QueryBuilder-Werte gebunden,
  Ausgaben escaped und schreibende API-Aktionen per CSRF geschützt. Aus
  Requestdaten werden keine SQL-Fragmente zusammengesetzt.
- Dateipfade werden normalisiert und nie ungeprüft aus Eingaben
  zusammengesetzt. Secrets und unnötige personenbezogene Daten bleiben aus
  Repository, Logs, Tests und Dokumentation.

## Suite- und Produktverträge

- OrgSuite besitzt die gemeinsamen AD-/BR-Einstiege; Fachapps duplizieren
  keine Suite-Linklisten.
- `adcalendar`, `adplaner`, `adurlaub` und `adroom` bleiben einzeln
  installierbar. LocalBase und OrgSuite sind Infrastruktur.
- Bei genau einem aktiven AD-Fachprodukt bleibt OrgSuite deaktiviert; ab zwei
  Fachprodukten aktiviert der geprüfte Installer OrgSuite.
- Fachapps greifen nicht direkt auf Tabellen, Controller oder
  JavaScript-Assets anderer Fachapps zu. Optionale Integrationen verwenden
  kleine LocalBase-Events oder Capability-Verträge; ein fehlender Provider ist
  ein gültiger Standalone-Zustand.
- Produktarchive enthalten nur das gewählte Fachprodukt und kompatible
  Infrastruktur; der vollständige Suite-Build darf alle Produkte bündeln.

## Test-, UI- und Datenqualität

### Testgetriebene Verhaltensänderungen

Neue Funktionen, Fehlerkorrekturen und sonstige Änderungen beobachtbaren
Verhaltens folgen grundsätzlich Red–Green–Refactor:

1. Vor der Implementierung fachliche Invariante, beobachtbares Zielverhalten
   und geeignete Testebene benennen.
2. Zuerst einen kleinen aussagekräftigen Test schreiben.
3. Ihn vor der Produktivcodeänderung ausführen und bestätigen, dass er aus dem
   erwarteten fachlichen Grund fehlschlägt.
4. Nur die kleinste zur Erfüllung notwendige Implementierung vornehmen.
5. Den neuen Test und die relevante Regressionstestsuite ausführen.
6. Erst danach ohne Verhaltensänderung refaktorieren.
7. Nach dem Refactoring die relevanten Tests erneut ausführen.
8. Jede Abweichung ausdrücklich und sachlich begründen.

Ein sofort grüner Test ist kein TDD-Nachweis; ausdrücklich als solcher
benannt darf er ein Charakterisierungstest sein. Reine Dokumentations-,
Formatierungs-, generierte oder mechanische Änderungen ohne sinnvoll
testbares Verhalten erhalten statt eines künstlichen TDD-Zyklus die passende
maschinelle Prüfung. Der vollständige Ablauf steht ausschließlich im Skill
`test-driven-change`.

- Fachlogik, Berechtigungen, Hierarchien, Konflikte und Validierungen werden
  test-first entwickelt. Cross-App-Verträge erhalten Provider- und
  Consumer-Contract-Tests.
- Zulässige Einstiegsausnahmen sind zeitlich begrenzte Spikes, rein
  deklarative Änderungen und schwer isolierbare Nextcloud-Integration.
  Übernommener Spike-Code wird zuvor charakterisiert; deklarative Änderungen
  erhalten passende Syntax-, Contract-, Layout- oder Sichtprüfungen.
- Für neuen oder wesentlich geänderten ausführbaren Code werden mindestens
  85 Prozent Line-Coverage angestrebt. PHP und JavaScript werden getrennt
  ausgewiesen; Sicherheitsinvarianten sind vollständig abzudecken.
- Testdaten, Fixtures, Screenshots, Logs, Beispiele und Dokumentation sind
  synthetisch, neutral und datenschutzarm.
- Oberflächen bleiben semantisch, per Tastatur bedienbar und besitzen
  sichtbare Fokuszustände. Bedeutung wird nicht ausschließlich farblich,
  per Hover oder per Zeigerinteraktion vermittelt.
- App-Roots, Tabs, Einstellungen, Adminbereiche und Tabellen erfüllen die in
  `docs/architecture.md` beschriebenen Accessibility- und Scrollverträge.

## Zustandsmodelle und Migrationen

- Vor Funktionen, die persistente Fachobjekte verändern, werden erlaubte und
  verbotene Ausgangszustände, Vorbedingungen, Zielzustand, Nebenwirkungen,
  Fehlerzustände, Wiederholungsverhalten und Nebenläufigkeitskonflikte
  bestimmt.
- Fachlich eingeschränkte Statusübergänge dürfen nicht über frei verwendbare
  allgemeine Setter erfolgen. Sie werden im Fachmodell oder einem eindeutig
  zuständigen Anwendungsservice gekapselt und positiv, negativ sowie im
  Fehlerfall getestet.
- Bei Datenbankänderungen mit möglichen Bestandsdaten werden altes und neues
  Schema, Transformationsregeln, Bestandsvarianten, Integritätsbedingungen,
  Transaktionsgrenze, Fortsetzbarkeit und Rollbackgrenzen dokumentiert.
- Erforderlich sind Tests der frischen Installation, des Upgrades aus der
  relevanten Vorversion mit synthetischen Bestandsdaten, der fachlichen Daten-
  und Beziehungsintegrität, ungültiger beziehungsweise widersprüchlicher
  Altdaten und der Anwendung auf dem migrierten Schema.
- Veröffentlichte Migrationen werden nicht nachträglich verändert.
  Korrekturen erfolgen durch eine neue Migration.

## DDEV, Hosting und Delivery

- DDEV wird ausschließlich aus dem dokumentierten `nextcloud-dev`-Root
  gesteuert. Zustandsändernde DDEV-, Docker-, Nextcloud-, `occ`-, Migrations-,
  Installations- oder Bereinigungsbefehle brauchen einen konkreten Auftrag
  oder ausdrückliche Freigabe.
- DDEV-Pfade, DDEV-Benutzer, Containerpfade, PHP-Binaries, Datenbankzugänge
  und sonstige lokale Annahmen dürfen niemals auf eine Produktiv- oder Hostingumgebung übertragen
  werden. Produktionspfade, Benutzer,
  `apps_paths`, PHP-Binary und CLI-Memory-Limit werden separat ermittelt.
  Ein Wechsel zwischen DDEV und Produktion ist eine Umgebungsgrenze. Bei unklarer Zielumgebung muss Codex stoppen.
- Nextcloud 34 besitzt lokal keinen `occ migrations:migrate`-Befehl.
  App-Migrationen laufen über `occ app:enable <app-id>` beziehungsweise
  `occ upgrade`, wenn `needsDbUpgrade: true` gemeldet wird.
- Core-App-Pfade bleiben read-only. Der vorgesehene `custom_apps`-Pfad ist
  nur für den erforderlichen Runtime-/CLI-Kontext writable. Rechte werden
  minimal korrigiert; niemals pauschal mit `chmod 777`.
- Eine Installation ist erst geliefert, wenn Status, Migration, mindestens
  je ein CSS-/JavaScript-Asset im Static-Webserver-Kontext und über HTTPS mit
  richtigem Content-Type sowie die sichtbare Oberfläche geprüft wurden.
- Kein Release erfolgt mit rotem Delivery-Gate. AD-Suite-Bau und -Abnahme
  folgen ausschließlich dem Skill `build-ad-suite-release`.

## Stop-Regeln

Wenn der konkrete Auftrag den Risikobereich nicht bereits ausdrücklich
umfasst, vor der Umsetzung Risiko, Dateien, Tests und Rückbau nennen und
Freigabe einholen bei:

- Datenbankschema, Migrationen oder bestehenden produktiven Daten;
- Berechtigungen, Gruppenlogik, Rollen, CSRF, Authentifizierung oder
  Zugriffsschutz;
- öffentlichen LocalBase-Verträgen oder mehreren App-Repositories;
- Dateiablage, Dateipfaden, Uploads, Downloads oder Dokumenterzeugung;
- Löschung, Umbenennung oder Verschiebung größerer Codebereiche;
- DDEV-, Docker-, Nextcloud- oder `occ`-Konfiguration;
- breiten Refactorings zur Reparatur roter Tests;
- unklarem Rückbauweg.

Ohne Freigabe sind dort nur Lesen, Analyse und ein minimaler Änderungsplan
zulässig. Sofort stoppen, wenn Produktionssysteme, unerwartete externe
Dienste, Git-Historienumschreibung, neue Produktionsdependencies, Änderungen
außerhalb des Auftrags oder der Verlust fachlicher Regeln erforderlich werden.

## Learning Candidates

Beobachtungen werden nicht automatisch verbindlich. Candidates müssen
reproduzierbar oder belegt, wiederverwendbar und der richtigen Ebene
zugeordnet sein. Sie bleiben bis zur ausdrücklichen Freigabe unverbindlich.
Bewertung und Vorschlagsformat folgen dem Skill
`evaluate-learning-candidate`; die aktuelle Prüfliste steht ausschließlich in
`docs/learning-candidates.md`.

## Git und Definition of Done

- Keine Commits, Pushes, Releases oder Deployments ohne ausdrückliche
  Freigabe durch Simon; niemals `git add .`.
- Bestehende fremde Änderungen bleiben unangetastet. Keine versionierten
  Backupkopien, kein `git reset --hard`, `git clean`, Force-Push oder
  History-Rewrite.
- Vor einem Commit werden aus dem tatsächlich betroffenen Repository
  `git status --short`, `git diff --stat` und `git diff --name-only` gezeigt.
- Relevante lokale Tests und der passende Pfad aus `verify-workspace` sind
  grün. Ein Diagnosemodus ist niemals ein Releaseurteil.
- Der Abschlussbericht nennt Scope, geänderte Dateien, Checks und Ergebnisse,
  ausgelassene Checks mit Grund, Risiken, verbleibende Candidates und die
  Commit-Frage. Nicht ausführbare Prüfungen werden als `teilweise geprüft`
  oder `nicht vollständig verifiziert` benannt.

Die Datei `00_ki_projektkonfiguration_br_nextcloud_apps.md` ist nur ein
Kompatibilitätshinweis und keine zweite Regelquelle.
