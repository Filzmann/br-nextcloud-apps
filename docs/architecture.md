# Architektur des BR-Nextcloud-App-Workspaces

Diese Datei beschreibt dauerhaftes app-übergreifendes Architekturwissen. Die
kurzen Arbeits- und Stop-Regeln stehen in `AGENTS.md`; wiederholbare Abläufe
stehen unter `.agents/skills/`. App-spezifische Fach- und Rechteverträge
bleiben im jeweiligen App-Repository.

## Repository- und Produktgrenzen

Der Parent ist Meta-, DDEV-, Dokumentations- und Prüfkontext. Jede deploybare
App ist ein getrenntes Git-Repository. Änderungen an einem öffentlichen
Cross-App-Vertrag sind deshalb Änderungen an Provider und Consumern und werden
in jedem betroffenen Repository separat geprüft.

`localbase` stellt kleine, dependency-arme gemeinsame Verträge bereit.
Gemeinsamer Code wird erst aufgenommen, wenn mindestens zwei Apps denselben
semantischen und testbaren Vertrag benötigen. `orgsuite` besitzt die
gemeinsamen AD-/BR-Einstiege und den Adminadapter für app-übergreifende
Organisationskonfiguration; sie besitzt keine Fachdaten.

Die fünf AD-Fachprodukte `adcalendar`, `adplaner`, `adurlaub`, `adroom` und
`adrecruitment`
bleiben einzeln installierbar. Bei genau einem aktiven Fachprodukt bleibt
OrgSuite deaktiviert und das Fachprodukt stellt Navigation und
Organisationsadministration bereit. Ab zwei Fachprodukten aktiviert der
geprüfte Installer OrgSuite. LocalBase und OrgSuite sind Infrastruktur, keine
eigenständigen Fachprodukte.

Noch nicht freigegebene künftige AD-Suite-Module, insbesondere DPA-
Fallsteuerung und Schichtvermittlung, stehen ausschließlich in der
[`AD-Suite-Zukunftsplanung`](ad-suite-zukunftsplanung.md). Diese Vormerkung
ändert weder den geltenden Produktkatalog noch Repositoryinventar,
Laufzeitverträge oder app-lokale Roadmaps und erteilt keine
Implementierungsfreigabe.

Fachapps greifen nicht direkt auf Tabellen, Controller oder JavaScript-Assets
anderer Fachapps zu. Optionale Integrationen verwenden kleine LocalBase-Events
oder Capability-Verträge. Ein fehlender Provider ist ein gültiger
Standalone-Zustand. Navigation, Capability-Verfügbarkeit und Menüsichtbarkeit
erweitern niemals fachliche Rechte.

Die normative Einteilung gemeinsamen Codes und app-übergreifender
Laufzeitdienste, die Store-Regeln sowie die komponentenweise
LocalBase-Bestandsaufnahme stehen in
[`ADR 0001`](architecture-decisions/0001-shared-code-runtime-and-app-store.md).
Die Root-relative Quelle ist
`docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`;
diese Datei wiederholt das dortige Entscheidungs- und Release-Gate nicht.

## Schichten und Modelle

Controller bleiben dünn. Fachlogik, Datenzugriff, Darstellung,
Dokumenterzeugung und Dateiablage bleiben getrennt. Datenzugriffe laufen über
Repository-, Mapper-, Store- oder Service-Klassen. JavaScript trennt
API-Adapter beziehungsweise Repositories, Modelle oder ViewModels, Workflows
und Rendering beziehungsweise Eventbindung.

Modelle und DTOs verwenden `get(...)` für einzelne Objekte, `get_all([...])`
für Listen und `toArray()` für Serialisierung. `save()` ist persistenten,
Store-gebundenen Modellen vorbehalten. Nicht persistierbare DTOs blockieren
`save()` mit einer klaren Fehlermeldung. `fromArray` und `fromRow` bleiben bei
Bedarf interne Hydrationsdetails. Neue `fromApi`-/`toApi`-Aliase werden nicht
eingeführt; bestehende `toApiArray()`-Call-sites dürfen beim ohnehin nötigen
Anfassen der Schicht schrittweise migriert werden.

DRY und KISS gelten gemeinsam. Eine Auslagerung braucht konkrete Duplizierung,
bessere Testbarkeit oder bessere Wartbarkeit. Gemeinsame UI-Komponenten
erfordern mindestens zwei semantisch gleiche Verwendungen einschließlich
Zuständen, Events und Accessibility-Vertrag.

## Rechte und Daten

Die Apps erzwingen deny by default, least privilege und server-side first.
Nextcloud-native Benutzer-, Gruppen-, Session-, AppConfig-, Share-, Datei-,
Capability-, Konfigurations- und Requestmechanismen sind verbindlich. Ein
paralleler eigener Mechanismus ist nur nach dokumentierter, geprüfter und vor
der Implementierung freigegebener Architekturentscheidung zulässig.

Jeder relevante Controller, API-Endpunkt, Servicepfad sowie jede Datei- und
Datenoperation prüft Akteur, Scope und konkrete Berechtigung serverseitig.
Nextcloud-Admin, App-Admin, Gruppenmitglied, normale Nutzer*innen,
Read-only-/Bearbeitungsrollen und Hintergrundjobs werden nicht gleichgesetzt.

Der öffentliche LocalBase-Organisationsvertrag Version 3 trennt `finance`
und `payroll` unter derselben `finance_lead`. Fachapps lesen Rollen und
Bürobereiche über den datensparsamen `AdOrganizationSnapshot`; ein fehlender,
ungültiger oder nur aus Defaults rekonstruierter Persistenzstand erteilt keine
Fachrechte. AD Recruitment verwendet diesen Vertrag für Personalreferat,
Lohn, bereichsgebundene Erstbegleitungen und granulare Vertretungsscopes,
ohne Tabellen oder Controller anderer Fachapps zu lesen.

Requests werden validiert und typisiert, QueryBuilder-Werte gebunden, Ausgaben
escaped und schreibende API-Aktionen per CSRF geschützt. SQL-Fragmente werden
nie aus Requestdaten zusammengesetzt. Dateipfade werden normalisiert und nie
ungeprüft aus Eingaben zusammengesetzt.

## Datenlebenszyklus, Löschfristen und Austritt

Der normative app-übergreifende Vertrag, das Provider- und Retention-Modell,
Self-Service- und Admin-Auskunft, Ausbauetappen sowie die Migrationsmatrix
stehen in der
[Datenschutzarchitektur](privacy-architecture.md)
(`docs/privacy-architecture.md`). Fachapps liefern und verändern ausschließlich
ihre eigenen Daten über öffentliche Provider; die zentrale Komponente kennt
keine fremden Tabellen oder internen Entitäten.

Es gibt keine pauschale app-übergreifende Jahresfrist. Konkrete Fristen,
Trigger, Löschsperren und Maßnahmen werden je Datenklasse fachlich sowie
datenschutzrechtlich freigegeben. Ein Beschäftigungsende wird weder aus einer
Kontodeaktivierung abgeleitet noch ohne belastbare Quelle erfunden. Fehlende
oder widersprüchliche Ereignisse lösen keine destruktive Aktion aus.

## UI und Accessibility

Benutzertexte verwenden echte deutsche Umlaute und `ß`; technische IDs und
Maschinenverträge bleiben stabil. Oberflächen sind semantisch, vollständig per
Tastatur bedienbar und besitzen sichtbare Fokuszustände, sprechende Labels und
verständliche Fehler. Bedeutung wird nie nur durch Farbe, Hover oder
Zeigerinteraktion vermittelt.

Persönliche Einstellungen liegen in einem eigenen semantischen Tab
`Einstellungen`. App-spezifische Administration liegt im Adminabschnitt der
Fachapp, app-übergreifende Organisationskonfiguration im zuständigen
Suite-Adminabschnitt.

Der direkte App-Root ist der vertikale Scrollcontainer. Breite Tabellen
scrollen horizontal nur in einem inneren Wrapper. Apps überschreiben weder
`body` noch globale Nextcloud-Core-Selektoren.

## Fachliche Zustände und Migrationen

Vor Funktionen, die persistente Fachobjekte verändern, wird das Zustandsmodell
bestimmt: erlaubte und verbotene Ausgangszustände, Vorbedingungen, Zielzustand,
Nebenwirkungen, Fehlerzustände, Wiederholungsverhalten und relevante
Nebenläufigkeitskonflikte.

Statusänderungen mit fachlichen Übergangsregeln erfolgen nicht über frei
verwendbare allgemeine Setter. Erlaubte Übergänge werden im Fachmodell oder
einem eindeutig zuständigen Anwendungsservice gekapselt und positiv, negativ
und im Fehlerfall getestet.

Bei Datenbankänderungen mit möglichen Bestandsdaten werden altes und neues
Schema, Transformationsregeln, Bestandsvarianten, Integritätsbedingungen,
Transaktionsgrenze, Fortsetzbarkeit und Rollbackgrenzen dokumentiert. Erforderlich
sind mindestens ein Test der frischen Installation, ein Upgrade-Test aus der
relevanten Vorversion mit synthetischen Bestandsdaten, Integritätsprüfungen,
eine Behandlung ungültiger oder widersprüchlicher Altdaten und ein
Anwendungstest auf dem migrierten Schema.

Veröffentlichte Migrationen werden nicht nachträglich verändert. Korrekturen
erfolgen durch eine neue Migration.

## Test- und Liefermodell

Der verbindliche TDD-Arbeitsablauf steht im Skill
[`test-driven-change`](../.agents/skills/test-driven-change/SKILL.md). Diese
Dokumentation beschreibt nur die dauerhaft geltende Einordnung der
Testnachweise.

Ein Check weist eine technische Eigenschaft nach, etwa gültige Syntax,
vollständige Metadaten, Linkziele oder synchronisierte Dateien. Ein
Verhaltenstest beobachtet dagegen ein fachlich relevantes Ergebnis über eine
definierte Schnittstelle. Ein grüner Check ersetzt keinen Verhaltenstest,
wenn Verhalten sinnvoll prüfbar ist.

- Unit-Tests isolieren kleine Fachregeln oder Wertobjekte und geben schnelle,
  präzise Fehlerhinweise.
- Integrationstests prüfen das Zusammenspiel realer Komponenten, etwa
  Repository, Datenbank, Nextcloud-Container oder Dateisystemgrenzen.
- Contract-Tests sichern eine veröffentlichte Schnittstelle auf Provider- und
  Consumerseite, ohne interne Implementierungen festzuschreiben.
- Smoke-Tests belegen einen schmalen, kritischen Pfad durch eine realistische
  Laufzeitumgebung; sie sind kein Ersatz für fachliche Varianten.
- End-to-End-Tests prüfen einen vollständigen Nutzerfluss und decken
  Integration breit, Ursachen aber meist weniger präzise ab.
- Charakterisierungstests halten bereits beobachtetes gewünschtes Verhalten
  vor Refactorings oder der Übernahme eines Spikes fest. Ein sofort grüner
  Charakterisierungstest ist kein Red-Nachweis für neues Verhalten.

Tests werden lesbar nach Arrange–Act–Assert aufgebaut: Ausgangslage
herstellen, genau die relevante Aktion ausführen, beobachtbare Ergebnisse und
Nebenwirkungen prüfen. Sie bleiben voneinander isoliert und deterministisch:
keine Abhängigkeit von Ausführungsreihenfolge, Echtzeit, Zufall, Netzwerk oder
gemeinsamem Restzustand ohne kontrollierte Fakes beziehungsweise explizite
Integrationsebene. Testdaten sind synthetisch und minimal; angelegte Dateien,
Konten, Gruppen, Konfigurationen und Datensätze werden zuverlässig bereinigt.

Jeder Test benennt seinen Beweiswert und seine blinden Flecken. Ein Unit-Test
beweist beispielsweise nicht automatisch Routing, Dependency Injection,
Persistenz oder Browserdarstellung; ein Mock-Aufruf beweist kein
beobachtbares Ergebnis, wenn dieses direkt prüfbar ist.

App-übergreifende Verträge besitzen Tests auf Provider- und Consumerseite.
Für neuen oder wesentlich geänderten ausführbaren Code werden mindestens 85
Prozent Line-Coverage angestrebt; PHP und JavaScript werden getrennt
betrachtet. Sicherheitsinvarianten werden unabhängig von Prozentwerten
vollständig abgedeckt.

Test-, Demo- und Dokumentationsdaten sind synthetisch, neutral und
datenschutzarm. Produktive oder fremde Beschäftigten-, BR-, Kund*innen-, Mail-,
Gesundheits-, Konflikt-, Beschluss- oder interne Dokumentdaten werden nicht
übernommen.

DDEV und Produktion sind getrennte Umgebungen. Produktive Pfade, Benutzer,
`apps_paths`, CLI-PHP und Memory-Limit werden in der Zielumgebung ermittelt.
Eine Installation ist erst geliefert, wenn neben Status und Migration
mindestens ein CSS- und ein JavaScript-Asset im Static-Webserver-Kontext und
über HTTPS mit richtigem Content-Type sowie die sichtbare Oberfläche geprüft
wurden.
