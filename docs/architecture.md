# Architektur des BR-Nextcloud-App-Workspaces

Diese Datei beschreibt dauerhaftes app-übergreifendes Architekturwissen. Die
kurzen Arbeits- und Stop-Regeln stehen in `AGENTS.md`; wiederholbare Abläufe
stehen unter `.agents/skills/`. App-spezifische Fach- und Rechteverträge
bleiben im jeweiligen App-Repository.

## Entwicklungsphase und Kompatibilitätsbedarf

Entscheidung vom 5. September 2026: Das Gesamtprojekt befindet sich vollständig
in der Entwicklung. Es gibt kein PROD, keinen produktiven Datenbestand und
keinen bereits betriebenen Bestand mit zu erhaltendem Upgradepfad. STAGING
ist eine wegwerfbare Entwicklungs- und Integrationsumgebung und darf im
konkret beauftragten Reinstall vollständig neu aufgebaut werden. Wenige
externe Testnutzer ändern diese Einordnung nicht.

Vor einer Datenmigration, Legacy-Unterstützung, Compatibility Layer,
Deprecated API, Dual-Read/Dual-Write, einem Altschema-Fallback, Übergangsformat
oder der Unterstützung historischer Entwicklungsstände wird geprüft:

1. Wurde der betroffene Zustand jemals produktiv eingesetzt?
2. Benötigen reale Daten oder Nutzer seine Erhaltung?
3. Gibt es einen anderen konkreten technischen Erhaltungsgrund, insbesondere
   einen geltenden Plattform- oder externen API-Vertrag?

Sind alle relevanten Antworten nein, ist die saubere Breaking-Change-/
Reinstall-Lösung der Standard. Frühere rein interne Entwicklungsstände
begründen weder Abwärtskompatibilität noch eine Deprecationfrist.
Entwicklungsschemata dürfen durch ein kanonisches Installationsschema ersetzt,
alte interne APIs und Konfigurationsformate samt ausschließlich dafür
benötigten Adaptern und Tests entfernt werden. Architekturqualität und der
saubere Zielzustand haben Vorrang. Nextclouds nötige Installationsmigrationen
bleiben erhalten; ein Verzeichnisname `Migration` beweist keine Altlast.

Breaking Changes werden im selben Änderungskontext vollständig durchgezogen:
betroffene Provider, Consumer, standardisierte APIs, Vertragsversionen,
Metadaten, Tests und Dokumentation müssen zusammenpassen. Unterstützte
Nextcloud-/openDesk-Plattformverträge, externe Standards, Autorisierung und
Datenschutz gelten unverändert. Fehlende oder inkompatible optionale Provider
bleiben kontrolliert sichtbar. Ein Reinstall erlaubt keine privaten
Fremdtabellenzugriffe oder parallel erfundenen Plattformmechanismen.

Vor destruktiver Arbeit werden die tatsächlich benötigten externen
Testidentitäten, Gruppen, Rollen und nicht reproduzierbaren Testdaten gezielt
gesichert oder über bestehende native Setup-Strukturen reproduzierbar gemacht.
Echte Personen- und Zugangsdaten bleiben außerhalb von Git. Diese begrenzte
Sicherung begründet keine allgemeine Legacy-Unterstützung. Ein Reinstall
bleibt ein normaler unterstützter Entwicklungsweg; der vorhandene
Compatibility-Workflow besitzt den Fresh-Install-Nachweis, dessen aktueller
Belegstatus in `docs/workspace.md` beschrieben ist.

Diese Phase endet ausschließlich durch einen ausdrücklich dokumentierten,
von Simon freigegebenen **Production-Readiness-/Production-Freeze-Entscheid**.
Ein Release Candidate, eine Versionsnummer, ein Staging-Deployment oder ein
externer Testzugang lösen den Wechsel nicht aus. Der Entscheid wird in dieser
kanonischen Lifecycle-Quelle mit Datum, Geltungsbereich und betroffenem
Versions-/Datenstand festgehalten und in die lokale Steuerung projiziert.
Dann werden Upgradepfade, Datenbankmigrationen, Persistenz, Backup/Restore,
Rollback, Release-/API-Kompatibilitätszusagen, Deployment-/Freigabeprozess und
PROD→STAGING/COPY-Strategie neu bewertet. Eine vollständige PROD-Governance
wird jetzt nicht vorweggenommen.

Diese Regel entscheidet den Kompatibilitätsbedarf, erweitert aber keinen
Repository-Schreibauftrag und ersetzt keine Freigabe für eine konkrete
destruktive Aktion. Lokale Regelprojektionen folgen dem bestehenden
`docs/parent-governance-contract.md`; ein unsynchronisierter Einzel-Checkout
darf keinen abweichenden Phasenstand stillschweigend annehmen.

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

Die sechs AD-Fachprodukte `adcalendar`, `adplaner`, `adurlaub`, `adroom`,
`adrecruitment` und `adbqplanung` bleiben einzeln installierbar. Der BQ-Planer
ist als Entwicklungsprodukt im gemeinsamen Menü und Standalone-Vertrag
registriert, bis zur dokumentierten Release-Reife jedoch aus Full-Suite- und
Einzelprodukt-Bundles ausgeschlossen. Bei genau einem aktiven Fachprodukt bleibt
OrgSuite deaktiviert und das Fachprodukt stellt Navigation und
Organisationsadministration bereit. Ab zwei Fachprodukten aktiviert der
geprüfte Installer OrgSuite. LocalBase und OrgSuite sind Infrastruktur, keine
eigenständigen Fachprodukte.

Noch nicht freigegebene künftige AD-Suite-Module, insbesondere DPA-
Fallsteuerung und Schichtvermittlung, stehen ausschließlich in der
[`systemweiten Zukunftsplanung`](zukunftsplan.md). Diese Vormerkung
ändert weder den geltenden Produktkatalog noch Repositoryinventar,
Laufzeitverträge oder app-lokale Roadmaps und erteilt keine
Implementierungsfreigabe.

Fachapps greifen nicht direkt auf Tabellen, Controller oder JavaScript-Assets
anderer Fachapps zu. Optionale Integrationen verwenden kleine LocalBase-Events
oder Capability-Verträge. Ein fehlender Provider ist ein gültiger
Standalone-Zustand. Navigation, Capability-Verfügbarkeit und Menüsichtbarkeit
erweitern niemals fachliche Rechte.

OrgSuite bleibt die kanonische Quelle der gemeinsamen AD- und BR-Menüs. Im
OrgSuite-Adminbereich können Nextcloud-Admins je Suite zusätzliche externe
Links mit Bezeichnung, HTTPS-Ziel und stabiler Reihenfolge verwalten. Die
Konfiguration wird serverseitig validiert und datensparsam in der
OrgSuite-AppConfig gespeichert; Fachapps pflegen keine Kopien. Die Links
werden im jeweiligen gemeinsamen Suite-Menü zusätzlich zu den aktivierten
Fachapps angezeigt. Ein externer Link erteilt weder Nextcloud- noch Fachrechte
und verändert die serverseitigen Berechtigungsprüfungen der Apps nicht.

`adbqplanung` konsumiert den öffentlichen LocalBase-Jahreskalender Version 1
für Schulferien und gesetzliche Feiertage über einen app-eigenen Port.
Brückentage bleiben lokale BQ-Konfiguration. Ein `stale`-Stand wird sichtbar
gekennzeichnet; `unavailable` oder inkompatible Daten liefern keinen
automatischen BQ-Terminvorschlag.

Die normative Einteilung gemeinsamen Codes und app-übergreifender
Laufzeitdienste, die Store-Regeln sowie die komponentenweise
LocalBase-Bestandsaufnahme stehen in
[`ADR 0001`](architecture-decisions/0001-shared-code-runtime-and-app-store.md).
Die Root-relative Quelle ist
`docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`;
diese Datei wiederholt das dortige Entscheidungs- und Release-Gate nicht.

Native Nextcloud-Administration ist keine app-übergreifende fachliche
Superrolle. Eigene Apps behandeln einen notwendigen Vollzugriff app-lokal,
pro Admin und höchstens 24 Stunden gemäß
[`ADR 0004`](architecture-decisions/0004-app-local-temporary-admin-full-access.md).

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

Die BQ-Planung gruppiert ihre fachlich verschiedenen Funktionen mit demselben
semantischen, tastaturbedienbaren Tab-Muster wie die übrigen Apps. Planung,
Termine beziehungsweise Tagesprogramm, Ressourcen und Einstellungen bleiben
dadurch klar getrennt. Eine Verwaltung von Bewerber*innen oder ihrer
Zuordnung zu einem BQ-Durchlauf ist kein Tab und keine Funktion der
BQ-Planungs-App; diese Zuständigkeit bleibt vollständig bei AD Recruitment.

Neue und wesentlich überarbeitete Menüs arbeiten möglichst kompakt: häufige
Aktionen bleiben direkt erreichbar, zusammengehörige seltene Optionen werden
verständlich gruppiert oder schrittweise eingeblendet. Kompaktheit darf weder
Beschriftungen, aktuellen Zustand und Fehlerhinweise noch Tastaturbedienung,
sichtbaren Fokus oder ausreichend große Touch-Ziele verdrängen. Insbesondere
Planungsoberflächen erhalten für kleine Smartphone-Viewports eine
eigenständig nutzbare responsive Darstellung; ein horizontal verschiebbarer
Desktop-Plan allein gilt nicht als smartphone-taugliche Ansicht.

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

Zuerst wird nach der Entwicklungsphasenregel oben entschieden, ob überhaupt
ein zu erhaltender Zustand vorliegt. Nur für diesen Fall gelten die folgenden
Erhaltungs- und Upgradepflichten; bei einem erlaubten Entwicklungsreset werden
stattdessen das kanonische Zielschema, notwendige Testdatensicherung,
Fresh Install/Reinstall, Integrität und Anwendung auf dem neuen Schema geprüft.

Bei Datenbankänderungen mit zu erhaltenden Bestandsdaten werden altes und neues
Schema, Transformationsregeln, Bestandsvarianten, Integritätsbedingungen,
Transaktionsgrenze, Fortsetzbarkeit und Rollbackgrenzen dokumentiert. Erforderlich
sind mindestens ein Test der frischen Installation, ein Upgrade-Test aus der
relevanten Vorversion mit synthetischen Bestandsdaten, Integritätsprüfungen,
eine Behandlung ungültiger oder widersprüchlicher Altdaten und ein
Anwendungstest auf dem migrierten Schema.

Migrationen für produktiv eingesetzte oder anderweitig konkret zu erhaltende
Stände werden nicht nachträglich verändert. Korrekturen erfolgen durch eine
neue Migration. Rein interne Entwicklungsrevisionen fallen unter die
Entwicklungsphasenregel; eine bloße RC-Veröffentlichung erzeugt keinen
fiktiven Produktionsbestand.

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
