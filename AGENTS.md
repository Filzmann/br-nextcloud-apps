# AGENTS.md – BR Nextcloud Apps

## Zweck und Routing

Dieses Repository ist der Parent-/Meta-Workspace für DDEV, gemeinsame
Dokumentation, app-übergreifende Verträge und Workspace-/Delivery-Prüfungen.
Es enthält keinen deploybaren App-Code.

- Menschlicher Einstieg: `README.md`
- Workspace und DDEV: `docs/workspace.md`
- App-übergreifende Architektur: `docs/architecture.md`
- Datenschutzarchitektur und Rollout: `docs/privacy-architecture.md`
- Nicht freigegebene AD-Suite-Zukunftsplanung:
  `docs/ad-suite-zukunftsplanung.md`
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
- Für jede relevante fachliche oder technische Information wird die bereits
  kanonische Quelle identifiziert und wiederverwendet. Abgeleitete
  Darstellungen werden daraus erzeugt oder dagegen geprüft; Rollen,
  Berechtigungen, Konfiguration, Status-, Schema-, Versions-, Navigations- und
  Repositoryinformationen erhalten keine zweite unabhängig gepflegte
  Wahrheit. Bewusst unabhängige lokale Daten bleiben lokal; diese Regel
  erzwingt weder formale DRY-Abstraktionen noch eine voreilige Auslagerung.
- Gemeinsamer Code wird erst extrahiert, wenn mindestens zwei Apps ihn
  semantisch gleich benötigen und der Vertrag app-übergreifend testbar ist.
  LocalBase bleibt klein und dependency-arm.
- Die verbindliche Einteilung in gebundelte Bibliothek, eigenständige
  Nextcloud-Laufzeit-App oder bewusst lokalen Code sowie der Vertrag für
  App-Store-Releases stehen ausschließlich in
  `docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`.
  Neue Apps erhalten keine automatische LocalBase-Abhängigkeit.
- PHP-Klassen werden nicht über verteilte relative `require`-Ketten geladen.
  Produktivcode nutzt den Nextcloud-PSR-4-Autoloader oder für gebündelte
  Kategorie-A-Abhängigkeiten genau einen reproduzierbar erzeugten,
  app-lokalen und namespace-isolierten Composer-Autoloader. PHP-Tests nutzen
  einen zentralen app-lokalen Test-Bootstrap. Bei der nächsten schreibenden
  PHP-Arbeit an einer noch nicht migrierten App wird deren vollständige
  Autoload-Migration als eigener, mitgeprüfter Schritt umgesetzt; der
  verbindliche Migrations- und Ausnahmevertrag steht ausschließlich in der
  genannten ADR 0001.
- Jede Bewertung oder Verschiebung von Code zwischen bestehenden Apps,
  LocalBase und einer gemeinsamen Bibliothek folgt aus dem Root dem Skill
  `classify-shared-code`. Er ersetzt keine app-lokalen Schreibfreigaben.
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
- Neue oder geänderte Fehlerzustände verwenden vorhandene Nextcloud- oder
  Projektmechanismen für Fehlerbehandlung und Logging. Sie bleiben durch
  spezifische Exceptions, stabile fachliche Fehlerzustände oder angemessen
  strukturierte, datensparsame Logkontexte erkennbar und eingrenzbar;
  Exceptions werden nicht still verschluckt. Eine neue Logging- oder
  Telemetriearchitektur entsteht nur aus einem konkreten Bedarf.
- Apps mit personenbezogenen Daten liefern Auskunft und Retention nur über die
  öffentlichen Provider-Grenzen aus `docs/privacy-architecture.md`. Eine
  zentrale Komponente liest oder verändert niemals Tabellen, Entitäten oder
  Dateien einer Fachapp direkt. Neue oder wesentlich erweiterte Datenklassen
  erhalten spätestens vor fachlicher Fertigstellung eine konkrete
  Provider-, Retention-, Drittpersonen- und Testaufgabe.

## Suite- und Produktverträge

- OrgSuite besitzt die gemeinsamen AD-/BR-Einstiege; Fachapps duplizieren
  keine Suite-Linklisten.
- `adcalendar`, `adplaner`, `adurlaub`, `adroom` und `adrecruitment` bleiben
  einzeln installierbar. LocalBase und OrgSuite sind Infrastruktur.
- Bei genau einem aktiven AD-Fachprodukt bleibt OrgSuite deaktiviert; ab zwei
  Fachprodukten aktiviert der geprüfte Installer OrgSuite.
- Fachapps greifen nicht direkt auf Tabellen, Controller oder
  JavaScript-Assets anderer Fachapps zu. Optionale Integrationen verwenden
  kleine LocalBase-Events oder Capability-Verträge; ein fehlender Provider ist
  ein gültiger Standalone-Zustand.
- Produktarchive enthalten nur das gewählte Fachprodukt und kompatible
  Infrastruktur; der vollständige Suite-Build darf alle Produkte bündeln.

## Test-, UI- und Datenqualität

### Testgetriebene Funktionserweiterungen und Verhaltensänderungen

Bei jeder neuen Funktion, Fehlerkorrektur oder sonstigen Änderung des
beobachtbaren Verhaltens muss der Skill `test-driven-change` verwendet werden.
Vor der Produktivcodeänderung werden fachliche Invariante, beobachtbares
Zielverhalten, geeignete Testebene, was der geplante Test beweist und
ausdrücklich nicht beweist sowie relevante negative Fälle und Grenzfälle
bestimmt.

Der Skill erzwingt Red–Green–Refactor mit einem aus dem erwarteten fachlichen
Grund zunächst roten Test, der kleinsten notwendigen Implementierung,
Regressionstests und Refactoring erst bei grünem Stand. Der Abschlussbericht
weist Invariante, Testebene, Red-Nachweis samt Fehlergrund, minimale
Implementierung, ausgeführte Tests und Ergebnisse, verbleibende ungetestete
Risiken sowie begründete Abweichungen aus.

Ein sofort grüner Test ist kein TDD-Nachweis; er darf nur als ausdrücklich
begründeter Charakterisierungstest dienen. Reine Dokumentations-,
Formatierungs-, generierte oder mechanische Änderungen ohne sinnvoll
testbares Verhalten erhalten statt eines künstlichen TDD-Zyklus die passende
maschinelle Prüfung; Zweifelsfälle werden kurz begründet.
Der vollständige Ablauf steht ausschließlich im Skill `test-driven-change`.

- Fachlogik, Berechtigungen, Hierarchien, Konflikte und Validierungen werden
  test-first entwickelt. Cross-App-Verträge erhalten Provider- und
  Consumer-Contract-Tests.
- Berührt eine Änderung eine fachliche oder sicherheitsrelevante Schutz- oder
  Zustandsgrenze, belegen Tests den erlaubten und mindestens einen sinnvollen
  verweigerten, ungültigen oder manipulierten Fall einschließlich
  ausbleibender Nebenwirkungen. Existiert kein sinnvoller Negativfall, wird
  keiner künstlich erzeugt; ein nicht automatisierbarer relevanter Pfad wird
  mit geeigneter Integrations- oder manueller Prüfung und verbleibender
  Nachweislücke berichtet.
- Zeitlich begrenzte Spikes und schwer isolierbare Nextcloud-Integration sind
  im Skill ausdrücklich zu begründende Abweichungen. Übernommener Spike-Code
  wird zuvor charakterisiert; deklarative Änderungen erhalten passende
  Syntax-, Contract-, Layout- oder Sichtprüfungen.
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
  Schema, Transformationsregeln, Bestandsvarianten einschließlich `NULL`-,
  Sonder- und Teilmigrationsständen, Integritätsbedingungen,
  Schema-/Code-Kompatibilität, Transaktionsgrenze, Fortsetzbarkeit,
  Wiederholbarkeit sowie Roll-forward- und Rollbackgrenzen dokumentiert.
- Nicht triviale Strukturänderungen prüfen ein additives
  Expand–Migrate/Backfill–Contract-Vorgehen; die alte Struktur entfällt erst
  nach Verifikation von Code und Datenbestand. Triviale additive Migrationen
  erhalten keinen künstlichen Mehrphasenprozess.
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
- Vor der Veröffentlichung jedes Release-Candidates wird mit dem Skill
  `verify-nextcloud-future-compatibility` gegen gepinnte offizielle
  Nextcloud-Repositories die höchste lückenlos nachgewiesene künftige
  Hauptversion je App bestimmt und als `max-version` in deren `info.xml`
  aufgenommen. Rote, lückenhafte, veraltete oder nur statisch geprüfte
  Nachweise blockieren die Veröffentlichung.
- `min-version` wird niemals automatisch angehoben. Ein belegtes Supportende,
  eine nicht mehr sicher reproduzierbare Plattform oder eine notwendige
  Abkehr von riskanten Kompatibilitätsschichten löst nur eine getrennte
  Bewertung mit Folgen, Tests und ausdrücklicher Entscheidung aus. Eine noch
  nicht deklarierte künftige Hauptversion begrenzt nur die Erweiterung nach
  oben; eine bereits deklarierte oder ausdrücklich geforderte Zielversion
  blockiert bei Inkompatibilität den Release-Candidate.
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

Vor einer strukturellen Änderung wird außerdem gestoppt, wenn konkurrierende
Quellen bestehen und ihre künftige Autorität nicht eindeutig ist, wenn kein
sicherer Migrations- oder Roll-forward-Pfad erkennbar ist, Bestandsdaten
destruktiv oder irreversibel gefährdet wären oder wenn eine kritische
Schutzgrenze mangels Diagnostizierbarkeit nicht sicher implementiert oder
verifiziert werden kann. Der Entscheidungsbericht nennt Fundstellen, Konflikt,
Varianten, Auswirkungen, Empfehlung und benötigte Entscheidung.

Ohne Freigabe sind dort nur Lesen, Analyse und ein minimaler Änderungsplan
zulässig. Sofort stoppen, wenn Produktionssysteme, unerwartete externe
Dienste, Git-Historienumschreibung, neue Produktionsdependencies, Änderungen
außerhalb des Auftrags oder der Verlust fachlicher Regeln erforderlich werden.

## Learning Candidates

Beobachtungen werden nicht automatisch verbindlich. Candidates müssen
reproduzierbar oder belegt, wiederverwendbar und der richtigen Ebene
zugeordnet sein. Sie bleiben bis zur ausdrücklichen Freigabe unverbindlich.
Bewertung und Vorschlagsformat folgen dem Skill
`evaluate-learning-candidate`. `docs/learning-candidates.md` enthält
ausschließlich offene, noch nicht entschiedene Candidates. Nach einer
Entscheidung wird der Candidate dort entfernt: Freigegebene Umsetzungen
werden als konkrete Aufgabe im zuständigen Repository geführt, verworfene
oder als Duplikat eingeordnete Candidates werden nicht als Aufgabe
übernommen. Wenn die Entscheidung für spätere Nachvollziehbarkeit relevant
ist, wird sie knapp in einem datierten Änderungsbericht dokumentiert.
Umgesetzte Aufgaben werden über Code, Tests, Dokumentation oder die
verbindliche Regel und nicht über die Candidate-Liste nachgewiesen.

## Git und Definition of Done

- Wenn Simon nach den nächsten offenen Schritten, Prioritäten oder
  Restaufgaben fragt, werden die anwendbaren ausstehenden Migrationen und
  Entscheidungen aus angenommenen ADRs sowie dokumentierten Rolloutplänen
  mit ihrem Status, Auslöser und erforderlichen Freigabegate genannt. Dabei
  werden sofort umsetzbare Schritte, erst bei späterer App-Arbeit ausgelöste
  Schritte und derzeit nicht entscheidbare Punkte getrennt. Die Erwähnung
  erweitert weder den aktuellen Schreibauftrag noch ersetzt sie eine
  erforderliche Freigabe.
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
