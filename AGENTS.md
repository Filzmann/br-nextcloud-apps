# AGENTS.md - BR Nextcloud Apps

## Projekt

Dies ist der Parent-/Meta-Workspace fuer die lokale Nextcloud-Entwicklungsumgebung und gemeinsame Dokumentation eigener Nextcloud-Apps.

Projektwurzel:

    ~/projects/br-nextcloud-apps

DDEV-/Nextcloud-Projekt:

    ~/projects/br-nextcloud-apps/nextcloud-dev

Der Parent ist nur Meta-/DDEV-/Dokumentationskontext. Deploybarer App-Code liegt in eigenen Git-Repositories neben dem DDEV-Projekt und darf im Parent nicht getrackt werden.

Aktuelle eigene Apps:

| App | App-ID | App-Repo | Lokale URL | DDEV-Mount |
| --- | --- | --- | --- | --- |
| BRTop | `brtop` | `~/projects/br-nextcloud-apps/brtop` | `https://nextcloud-dev.ddev.site/apps/brtop/` | `/var/www/html/html/custom_apps/brtop` |
| AdPlaner | `adplaner` | `~/projects/br-nextcloud-apps/adplaner` | `https://nextcloud-dev.ddev.site/apps/adplaner/` | `/var/www/html/html/custom_apps/adplaner` |
| BRStunden | `brstunden` | `~/projects/br-nextcloud-apps/brstunden` | `https://nextcloud-dev.ddev.site/apps/brstunden/` | `/var/www/html/html/custom_apps/brstunden` |
| LocalBase | `localbase` | `~/projects/br-nextcloud-apps/localbase` | keine Navigation | `/var/www/html/html/custom_apps/localbase` |
| Berechtigungsmatrix | `br_permission_matrix` | `~/projects/br-nextcloud-apps/br_permission_matrix` | `https://nextcloud-dev.ddev.site/apps/br_permission_matrix/` | `/var/www/html/html/custom_apps/br_permission_matrix` |
| AD Kalender | `adcalendar` | `~/projects/br-nextcloud-apps/adcalendar` | `https://nextcloud-dev.ddev.site/apps/adcalendar/` | `/var/www/html/html/custom_apps/adcalendar` |
| AD Urlaub | `adurlaub` | `~/projects/br-nextcloud-apps/adurlaub` | `https://nextcloud-dev.ddev.site/apps/adurlaub/` | `/var/www/html/html/custom_apps/adurlaub` |
| AD-/BR-Suite | `orgsuite` | `~/projects/br-nextcloud-apps/orgsuite` | `https://nextcloud-dev.ddev.site/apps/orgsuite/ad` und `/br` | `/var/www/html/html/custom_apps/orgsuite` |
| AD Raumplaner | `adroom` | `~/projects/br-nextcloud-apps/adroom` | `https://nextcloud-dev.ddev.site/apps/adroom/` | `/var/www/html/html/custom_apps/adroom` |

Öffentliche Produktübersicht und Release-Unterlagen der AD-Suite liegen im eigenständigen Dokumentations-Repository `~/projects/br-nextcloud-apps/ad-suite`. Es enthält keinen deploybaren App-Code und wird nicht in Nextcloud gemountet.

## Verbindlicher Arbeitsumfang

- In diesem Parent-Repo werden nur Meta-Dokumentation, DDEV-Konfiguration, Workspace-Konfiguration und app-uebergreifende Regeln gepflegt.
- App-Code aus `brtop/`, `adplaner/`, `brstunden/` oder weiteren App-Repos wird hier nicht geaendert, nicht gestaged und nicht committed, ausser Simon fordert das ausdruecklich fuer eine konkrete App an.
- Wenn an einer App gearbeitet werden soll, zuerst in das jeweilige App-Repo wechseln, dort die `AGENTS.md` vollstaendig lesen und dort den Git-Status pruefen.
- App-spezifische Fachlogik, Zielprozesse, Tests, lokale Architekturentscheidungen und Gruppen-/Rechteschemata gehoeren in die `AGENTS.md` des jeweiligen App-Repos.
- App-uebergreifende Arbeitsweise, DDEV-Regeln, Mounts, neue-App-Checklisten und gemeinsame Architekturprinzipien gehoeren in diese Parent-`AGENTS.md` und bei Bedarf in `docs/`.

## Repo-Trennung

Jede deploybare eigene Nextcloud-App wird als eigenes Git-Repository gefuehrt.

- `brtop/` ist ein eigenes Git-Repository.
- `adplaner/` ist ein eigenes Git-Repository.
- `brstunden/` ist ein eigenes Git-Repository.
- `localbase/` ist ein eigenes Git-Repository fuer gemeinsame, fachlich neutrale Basisbausteine.
- `br_permission_matrix/` ist ein eigenes Git-Repository fuer die read-only Berechtigungsmatrix.
- `adcalendar/` ist ein eigenes Git-Repository fuer Dienst- und Terminplanung.
- `adurlaub/` ist ein eigenes Git-Repository fuer Urlaubsplanung.
- `orgsuite/` ist ein eigenes Git-Repository fuer die gemeinsame AD-/BR-Navigation ohne Fachdaten.
- `adroom/` ist ein eigenes Git-Repository fuer Raumverwaltung und Raumbuchungen.
- `ad-suite/` ist ein eigenes Git-Repository für die öffentliche Produktübersicht, Installations-, Betriebs-, Abnahme- und Release-Unterlagen der AD-Suite.
- Neue deploybare Apps bekommen eigene Git-Repos, eigene `AGENTS.md` und eigene `.gitignore`.
- Der Parent ignoriert App-Verzeichnisse per `.gitignore`; App-Code darf im Parent nicht auftauchen.
- Keine Submodule fuer diese lokalen App-Repos, solange Simon das nicht ausdruecklich entscheidet.
- Keine gemeinsame Bibliothek vorsorglich anlegen. Eine gemeinsame Library wird sinnvoll, sobald mindestens zwei Apps denselben Code nicht nur aehnlich, sondern semantisch gleich brauchen, die Schnittstelle stabil genug ist und app-uebergreifend getestet werden kann. In der lokalen Vor-Production-Phase darf diese Extraktion frueher erfolgen, wenn sie sofort echte Duplizierung entfernt; sie bleibt trotzdem ein eigener bewusster Schritt.

## DDEV

DDEV wird immer aus diesem Ordner gesteuert:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev

Wichtige Befehle:

    ddev start
    ddev stop
    ddev restart
    ddev describe
    ddev launch /apps/brtop/
    ddev launch /apps/adplaner/
    ddev launch /apps/brstunden/
    ddev launch /apps/br_permission_matrix/
    ddev launch /apps/adcalendar/
    ddev launch /apps/adurlaub/
    ddev launch /apps/orgsuite/ad
    ddev launch /apps/orgsuite/br
    ddev launch /apps/adroom/
    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list | grep -i brtop
    ddev exec -d /var/www/html/html php occ app:list | grep -i adplaner
    ddev exec -d /var/www/html/html php occ app:list | grep -i brstunden
    ddev exec -d /var/www/html/html php occ app:list | grep -i localbase
    ddev exec -d /var/www/html/html php occ app:list | grep -i br_permission_matrix
    ddev exec -d /var/www/html/html php occ app:list | grep -i adcalendar
    ddev exec -d /var/www/html/html php occ app:list | grep -i adurlaub
    ddev exec -d /var/www/html/html php occ app:list | grep -i orgsuite
    ddev exec -d /var/www/html/html php occ app:list | grep -i adroom

In Codex-Sessions koennen DDEV-Befehle im normalen Sandbox-Kontext nicht zuverlaessig auf Docker zugreifen. Wenn `ddev` mit Docker-/Stream-FD-Fehlern scheitert, ist das kein App- oder DDEV-Projektfehler.

Eskalierter Zugriff darf fuer reine Diagnose- und Testbefehle erneut versucht werden, zum Beispiel `ddev describe`, `ddev exec ... php occ status`, `ddev exec ... php occ app:list`, PHP-/Node-Testlaeufe oder gezielte lesende Checks.

Zustandsaendernde DDEV-/`occ`-Befehle wie `ddev start`, `ddev stop`, `ddev restart`, `occ app:enable`, `occ upgrade`, Migrationen, Installationen oder Bereinigungen duerfen nur mit eskaliertem Zugriff ausgefuehrt werden, wenn Simon die konkrete Aktion angefordert oder freigegeben hat.

DDEV-Pruefungen deshalb buendeln, lokale PHP-/Node-Pruefungen bevorzugen und wiederverwendbare Prefix-Freigaben fuer `ddev exec` nur fuer klar benannte Befehle nutzen.

## Nextcloud-App-Installation und Migrationen

In der lokalen Nextcloud 34-Umgebung gibt es keinen `occ migrations:migrate`-Befehl. App-Migrationen laufen beim Aktivieren einer App mit `occ app:enable <app-id>` bzw. ueber `occ upgrade`, wenn `occ status` `needsDbUpgrade: true` meldet.

Nach dem Aktivieren oder Aktualisieren einer App pruefen:

    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list | grep -i <app-id>

Bei neuen Tabellen oder Background-Jobs zusaetzlich gezielt pruefen, ob die erwartete Tabelle bzw. der erwartete Eintrag in `oc_jobs` existiert.

## DDEV-Mounts

Die App-Repos werden per DDEV-Bind-Mount eingebunden.

BRTop:

    ~/projects/br-nextcloud-apps/brtop
    -> /var/www/html/html/custom_apps/brtop

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.brtop.yaml

AdPlaner:

    ~/projects/br-nextcloud-apps/adplaner
    -> /var/www/html/html/custom_apps/adplaner

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.adplaner.yaml

BRStunden:

    ~/projects/br-nextcloud-apps/brstunden
    -> /var/www/html/html/custom_apps/brstunden

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.brstunden.yaml

LocalBase:

    ~/projects/br-nextcloud-apps/localbase
    -> /var/www/html/html/custom_apps/localbase

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.localbase.yaml

Berechtigungsmatrix:

    ~/projects/br-nextcloud-apps/br_permission_matrix
    -> /var/www/html/html/custom_apps/br_permission_matrix

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.br_permission_matrix.yaml

AD Kalender:

    ~/projects/br-nextcloud-apps/adcalendar
    -> /var/www/html/html/custom_apps/adcalendar

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.adcalendar.yaml

AD Urlaub:

    ~/projects/br-nextcloud-apps/adurlaub
    -> /var/www/html/html/custom_apps/adurlaub

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.adurlaub.yaml

AD-/BR-Suite:

    ~/projects/br-nextcloud-apps/orgsuite
    -> /var/www/html/html/custom_apps/orgsuite

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.orgsuite.yaml

AD Raumplaner:

    ~/projects/br-nextcloud-apps/adroom
    -> /var/www/html/html/custom_apps/adroom

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.adroom.yaml

Mounts muessen den lowercase-Pfad `~/projects/...` verwenden. Nicht `~/Projects/...`.

Wenn eine neue App angelegt wird, wird im Parent nur die DDEV-Mount-Konfiguration und die gemeinsame Dokumentation ergaenzt. Der App-Code bleibt im neuen App-Repo.

## Anlegen neuer Apps

Neue eigene Nextcloud-Apps werden als eigene App-Repos neben den bestehenden Apps angelegt, nicht als vom Parent getrackter Unterordner.

Checkliste:

1. Fachlich einordnen: BR-App, AD-App oder anderer eigener Anwendungsbereich.
2. App-ID, lokaler Pfad, lokale URL und DDEV-Mount festlegen.
3. App-Verzeichnis neben `brtop/` und `adplaner/` anlegen.
4. Im App-Verzeichnis ein eigenes Git-Repo initialisieren.
5. Eigene `AGENTS.md` mit Zielsetzung, Fachkontext, Architekturregeln, Git-Regeln, DDEV-Hinweisen und Learning-Regel anlegen.
6. Eigene `.gitignore` im App-Repo anlegen.
7. Parent-`.gitignore` um das neue App-Verzeichnis ergaenzen.
8. DDEV-Mount im Parent unter `nextcloud-dev/.ddev/docker-compose.<app-id>.yaml` anlegen.
9. Parent-Dokumentation nur um Meta-/DDEV-/Mount-Informationen ergaenzen.
10. Vor dem ersten Commit im App-Repo `git status --short`, `git diff --stat` und `git diff --name-only` zeigen; Dateien gezielt stagen, nie `git add .`.

## Gemeinsame Architekturprinzipien

Diese Regeln bilden die gemeinsame Basis fuer eigene Nextcloud-Apps. App-spezifische Ziele, Fachlogik und Tests werden zusaetzlich in der jeweiligen App-`AGENTS.md` gepflegt.

Fuer eigene Nextcloud-Apps gelten nicht:

- WordPress-spezifische APIs, Konzepte und Pruefungen wie Shortcodes, Gutenberg-Bloecke, `$wpdb`, WordPress-Nonces, `current_user_can()`, `esc_html()` oder WordPress-Capabilities.
- Namenskonventionen anderer Projekte wie `flz_`.
- Symlink-Regeln, Staging-Workflows oder Plugin-Verzeichnisregeln aus WordPress-Projekten.
- Gemeinsame WordPress-Hilfsplugins oder deren APIs.
- Harte Demo-Daten aus externen Produktivsystemen.

Fuer eigene Nextcloud-Apps gelten diese angepassten Prinzipien:

- Controller bleiben duenn.
- Fachlogik, Datenzugriff, Darstellung, Dokumenterzeugung und Dateiablage werden getrennt.
- Wiederkehrende Logik wird nicht mehrfach in Controllern oder `main.js` dupliziert.
- Persistente Kernobjekte bekommen Modelle/DTOs oder Value Objects, sobald rohe Arrays unuebersichtlich werden oder mehrere Schichten durchlaufen.
- Modelle/DTOs werden bei Neu- und Weiterentwicklungen in PHP und JavaScript einheitlich angefasst: `get(...)` fuer ein einzelnes Payload/Row/Objekt, `get_all([...])` fuer Listen, `toArray()` fuer Serialisierung und `save()` nur fuer wirklich persistierbare, store-gebundene Modelle. Nicht persistierbare DTOs duerfen `save()` bewusst mit klarer Fehlermeldung blockieren.
- Modell-Hydration wird von aussen ueber `get(...)` und `get_all([...])` aufgerufen. Hilfsmethoden wie `fromArray` oder `fromRow` bleiben, falls noetig, interne/protected Implementierungsdetails und sind keine oeffentliche Modell-API.
- Neue Modellarbeit fuehrt keine neuen `fromApi`-/`toApi`-Kompatibilitaetsaliase ein. Bestehende PHP-`toApiArray()`-Call-sites duerfen schrittweise auf `toArray()` migriert werden, wenn die betroffene Schicht ohnehin angefasst wird.
- Datenzugriffe laufen ueber Repository-, Mapper-, Store- oder Service-Klassen.
- Services arbeiten bevorzugt mit Modellen/DTOs statt rohen Arrays.
- Groessere HTML-Bloecke werden aus `templates/index.php` in Partials ausgelagert.
- Wiederkehrende Frontend-Logik wird app-intern in `js/components/`, `js/modules/` oder `js/repositories/` ausgelagert.
- JavaScript wird gut gekapselt, wiederverwendbar und weitgehend objektorientiert strukturiert. App-spezifische API-Zugriffe gehoeren in Repositories/API-Adapter, Daten in Modelle/ViewModels, Workflows in kleine Services/Controller und Rendering/Eventbindung in Komponenten.
- DRY und KISS gelten gemeinsam: echte Duplizierung wird entfernt, aber einfache Lesbarkeit und klare lokale Fachgrenzen bleiben wichtiger als fruehe generische Abstraktionen.
- Eine gemeinsame UI-Component-Library ist sinnvoll, sobald mindestens zwei Apps dieselben UI-Primitives oder Komponenten semantisch gleich brauchen, inklusive gleicher Zustaende, Events und Accessibility-Regeln. Bis dahin werden nur kleine, stabile Helfer wie Escaping, Notices, Buttons oder Formatierer nach `localbase` verschoben; keine grosse Design-System-Schicht vorsorglich bauen.
- Fehler werden zentral protokolliert; Nutzer*innen erhalten sichere, knappe Meldungen ohne interne Details.
- Keine Architekturabstraktion wird vorsorglich gebaut. Auslagerung erfolgt, wenn sie konkrete Duplizierung, Testbarkeit oder Wartbarkeit verbessert.

### Gemeinsame AD-/BR-Suite-Navigation

- Im Nextcloud-Appmenue werden die AD-Fachapps unter dem Einstieg `AD` und die BR-Fachapps unter `BR` gebuendelt.
- `orgsuite` besitzt die gemeinsamen Menue-Definitionen, Icons und Einstieg-Weiterleitungen. Fachapps duplizieren keine Suite-Linklisten.
- Die Fachapps bleiben eigenstaendige Repositories, Datenmodelle und Berechtigungsraeume. Navigation erteilt niemals fachliche Rechte; Zielcontroller und APIs pruefen weiterhin serverseitig.
- Neue AD- oder BR-Fachapps werden sowohl in OrgSuite als auch in ihrer eigenen App-`AGENTS.md` dem passenden Suite-Menue zugeordnet und registrieren keinen zusaetzlichen Hauptnavigationseintrag.

## Gemeinsame Kommentar- und Dokumentationsstruktur im Code

Kommentare und PHPDoc/JSDoc sollen den Code erklaeren, nicht seine Syntax nacherzaehlen. Klassen, Attribute und Methoden werden kommentiert, wenn Zweck, fachliche Bedeutung, Seiteneffekte, Sicherheitsgrenzen oder das Zusammenspiel mit anderen Bausteinen nicht unmittelbar aus Namen, Typen und wenigen Codezeilen hervorgehen.

Fuer erklaerungsbeduerftige Klassen und groessere Funktionen gilt diese Reihenfolge. Nur die jeweils benoetigten Bloecke werden aufgenommen:

```text
Zweck: Warum existiert der Baustein, welche Verantwortung hat er?
Zusammenspiel: Welche anderen Klassen/Services rufen ihn auf oder werden von ihm koordiniert?
Spiegelung: Welches konkrete PHP-/JavaScript-Symbol bildet denselben Vertrag oder Algorithmus ab?
Vertrag: Welche Invarianten, Berechtigungen, Seiteneffekte, Fehlerfaelle oder Datenformate sind wichtig?
```

Verbindliche Regeln:

- Klassenkommentare beschreiben bei nicht trivialen Services, Controllern, Repositories, Adaptern, Modellen und UI-Komponenten mindestens den Zweck. `Zusammenspiel` wird ergaenzt, wenn die Rolle erst im Daten- oder Kontrollfluss verstaendlich wird.
- Methodenkommentare erklaeren fachliche Entscheidungen, Seiteneffekte, Berechtigungsgrenzen, Fehlerverhalten oder nicht offensichtliche Rueckgabevertraege. Getter, einfache Delegationen und selbsterklaerende CRUD-Methoden werden nicht kommentiert.
- Attribute, Properties und Payload-Felder werden nur kommentiert, wenn Bedeutung, Einheit, Herkunft, Lebensdauer oder Datenschutzrelevanz nicht aus Name und Typ hervorgehen. Dafuer reicht meist `Bedeutung: ...` in einer kurzen Property-Dokumentation.
- Wenn sich Verhalten in PHP und JavaScript spiegelt, nennen beide Stellen unter `Spiegelung` das exakte Gegenstueck, zum Beispiel `PHP: ExportService::aggregateCell()` und `JS: render.aggregateCell()`. Der Kommentar nennt auch, welcher Vertrag identisch bleiben muss. Wenn sinnvoll, sichert ein Contract-Test diese Uebereinstimmung ab.
- Bei zusammenarbeitenden Klassen wird der relevante Fluss beschrieben, nicht lediglich die Constructor-Liste wiederholt, zum Beispiel `Controller -> AccessService -> Repository` oder `ScannerService baut Snapshot, DiffService bewertet ihn, SnapshotMapper persistiert ihn`.
- Kommentare stehen direkt am dokumentierten Symbol. Laengere Architekturerklaerungen gehoeren in die App-`AGENTS.md` oder `docs/` und werden im Code nur knapp referenziert.
- Bestehende Kommentarstruktur und Sprache eines Repos werden beibehalten. Wo noch kein Stil besteht, werden die obigen deutschen Bezeichnungen einheitlich verwendet.
- Kommentare werden bei Verhaltensaenderungen mitgepflegt. Veraltete, spekulative oder den Code nur wiederholende Kommentare werden entfernt.
- Keine Kommentarquote erzwingen: So viel Dokumentation wie zum sicheren Verstaendnis noetig, aber nur so viel wie fachlich oder technisch Mehrwert bietet.


## Gemeinsame Rechte- und Zugriffsschutzregeln

Eine klare, granulare Benutzerrechte- und Zugriffsschutzsteuerung ist fuer alle eigenen Nextcloud-Apps eine harte Architektur- und Sicherheitsanforderung. Codex muss Rechtefragen bei neuen Features, Refactorings, API-Endpunkten, Datenmodellen, Dateioperationen, Dokumenterzeugung und UI-Aenderungen aktiv mitdenken.

Grundprinzipien:

- Deny by default: Zugriff ist nur erlaubt, wenn er bewusst und nachvollziehbar erlaubt wurde.
- Least privilege: Nutzer*innen erhalten nur die Rechte, die sie fuer die konkrete Funktion brauchen.
- Server-side first: UI-Ausblendungen sind nur Komfort, kein Schutz. Jeder relevante Controller, API-Endpunkt, Servicepfad und jede Datei-/Datenoperation braucht serverseitige Pruefung.
- Nextcloud-native Funktionen bevorzugen: Gruppen, Benutzerkontext, Session, App-Konfiguration, Share-/Dateirechte, Capabilities und vorhandene Nextcloud-APIs sollen genutzt werden, statt vorschnell parallele Eigenlogik aufzubauen.
- App-spezifische Rollen, Gruppen, Rechte und Sonderfaelle gehoeren in die jeweilige App-`AGENTS.md`. App-uebergreifende Muster gehoeren in die Parent-`AGENTS.md` oder nach `localbase`, wenn sie konkret wiederverwendbar sind.
- Rechtepruefungen sollen moeglichst zentral ueber Permission-, Access-, Policy- oder Capability-Services gebuendelt werden. Keine verstreuten Ad-hoc-Pruefungen in vielen Controllern, Templates oder JavaScript-Dateien.
- Frontend-Code darf Berechtigungen anzeigen, erklaeren und UI-Zustaende anpassen, aber keine alleinige Autoritaet ueber Zugriff haben.
- Repositories, Stores und Services duerfen keine unbeschraenkten Datenlisten liefern, wenn der aufrufende Kontext eigentlich eingeschraenkt sein muss. Akteur, Gruppe, Scope oder Permission-Kontext muessen in der Architektur nachvollziehbar sein.
- Nextcloud-Admin, App-Admin, Gruppenmitglied, normale*r Nutzer*in, Read-only-Rolle, Bearbeitungsrolle und technische Hintergrundaufgabe duerfen nicht automatisch gleichgesetzt werden.
- Temporare lokale Vereinfachungen sind in der Vor-Production-Phase erlaubt, muessen aber als solche benannt werden und duerfen keine spaetere granulare Rechtearchitektur verbauen.

Bei jeder neuen Funktion muss Codex pruefen und im Plan oder Abschlussbericht benennen:

1. Wer darf die Funktion sehen?
2. Wer darf die Funktion ausfuehren?
3. Welche Daten duerfen gelesen werden?
4. Welche Daten duerfen angelegt, geaendert oder geloescht werden?
5. Welche Nextcloud-Gruppen, Rollen, Shares, Capabilities oder App-Konfigurationen sind relevant?
6. Welche serverseitige Pruefung erzwingt die Regel?
7. Welche Tests oder Smoke-Checks decken erlaubte und verbotene Zugriffe ab?

Tests fuer Berechtigungen muessen mindestens typische Allow- und Deny-Faelle abdecken. Bei sicherheitsrelevanten Funktionen sollen direkte API-Aufrufe ohne passende Berechtigung negativ getestet werden, auch wenn die UI den Button versteckt.

Wenn eine Aufgabe Berechtigungen, Gruppenlogik, Rollen, Zugriffsschutz, Shares, Dateirechte oder Capabilities beruehrt, gelten die Stop-Regeln fuer riskante Aenderungen. Ohne ausdrueckliche Freigabe darf Codex dann nur analysieren und einen Rechte-/Zugriffsplan vorschlagen.

## Gemeinsame UI- und Accessibility-Regeln

Accessibility ist fuer eigene Nextcloud-Apps eine harte Entwicklungsregel, nicht nur ein optionaler Feinschliff.

### Einstellungen als eigener Tab

Wiederverwendbares Learning: Persönliche Einstellungen werden in jeder navigierbaren eigenen App in einem eigenen Tab `Einstellungen` gebündelt. Administrative Einstellungen erscheinen im Nextcloud-Adminbereich und werden fachlich der App zugeordnet, deren Verhalten sie steuern.

- Der Einstellungstab einer Fachapp enthält ausschließlich persönliche Einstellungen des eingeloggten Kontos.
- Administrative Einstellungen, die nur eine Fachapp betreffen, gehören in einen eigenen Adminabschnitt dieser Fachapp. Die Suite-App dient nicht als Sammelstelle für app-spezifische Konfiguration.
- App-übergreifende Organisationskonfigurationen wie gemeinsam verwendete Gruppen, Hierarchien oder Freigaben gehören in den Nextcloud-Adminbereich der zuständigen Suite-App.

- Einstellungen werden nicht in Hauptansichten, aufklappbaren `details`-Bloecken oder fachfremden Dialogen versteckt.
- Der Tab `Einstellungen` ist Teil derselben semantischen Tabnavigation wie die Hauptansicht und verwendet `role="tablist"`, `role="tab"`, `role="tabpanel"`, eindeutige `aria-controls`-/`aria-labelledby`-Beziehungen und gepflegte `aria-selected`-Zustaende.
- Fehlende Berechtigungen blenden den Einstellungstab oder Adminabschnitt nur als Komfort aus; lesende und schreibende Einstellungs-Endpunkte bleiben serverseitig geschützt.
- Kontextuelle Kleinstoptionen duerfen direkt an einer Funktion liegen, wenn sie ausschliesslich diese eine Aktion konfigurieren. Dauerhafte App-, Gruppen-, Rechte- oder Standardwerte gehoeren immer in den Einstellungstab.
- Neue Apps und groessere UI-Aenderungen bekommen einen Smoke-Test fuer Tabwechsel, Sichtbarkeit und die semantischen Tabbeziehungen.

### Nextcloud-Scrollvertrag fuer App-Seiten

Wiederverwendbares Learning: Nextcloud stellt den zentralen App-Inhaltsbereich als begrenzten Layoutbereich bereit und verhindert haeufig das Scrollen des Dokument-Bodys. Jede navigierbare eigene App muss deshalb von Beginn an einen expliziten Scrollvertrag besitzen:

- Der direkte App-Root im Nextcloud-Contentbereich ist der vertikale Scrollcontainer und verwendet mindestens `height: 100%`, `min-height: 0`, `overflow-y: auto` und `box-sizing: border-box`.
- Der App-Root bekommt einen deckenden Nextcloud-Hintergrund, typischerweise `background: var(--color-main-background)`, damit kein globales Theme-/Login-Wallpaper durchscheint.
- Breite Kalender, Matrizen und Tabellen scrollen horizontal nur in einem gezielten inneren Wrapper mit `overflow-x: auto`; die gesamte App darf dadurch nicht unkontrolliert horizontal wachsen.
- Breitenintensive Tabellen dürfen die gesamte verfügbare App-Breite nutzen. Künstliche `max-width`-Begrenzungen auf umgebenden Ansichten sind zu vermeiden; die Tabelle kombiniert je nach Inhalt `width: 100%`, `min-width: 100%` oder `width: max-content` mit dem gezielten horizontalen Wrapper.
- In Flex- oder Grid-Eltern muessen scrollende Kinder `min-height: 0` beziehungsweise `min-width: 0` erhalten, damit der Browser den Overflow tatsaechlich innerhalb des vorgesehenen Containers berechnet.
- `body`, globale Nextcloud-Container und Core-Selektoren werden von Apps nicht ueberschrieben. Der Scrollvertrag bleibt auf app-eigene Klassen begrenzt.
- Jede neue navigierbare App und jede groessere Layoutaenderung bekommt einen Layout-Smoke-Test oder eine gezielte Browserpruefung fuer vertikales App-Scrolling, horizontalen Tabellen-Overflow und deckenden Hintergrund.

Bei neuen oder geaenderten Oberflaechen muss Codex auf folgende Mindeststandards achten:

- Deutsche Benutzertexte verwenden echte Umlaute und `ß`; Schreibweisen wie `ae`, `oe`, `ue` oder `ss` sind kein Ersatz. Technische IDs, bestehende Gruppenkennungen, URLs, Dateinamen und andere Maschinenvertraege bleiben davon unberuehrt.
- semantische HTML-Struktur,
- nutzbare Tastaturbedienung,
- sichtbare Fokuszustaende,
- sprechende Labels fuer Formularfelder, Buttons und interaktive Elemente,
- keine rein farbliche Bedeutungsuebermittlung,
- verstaendliche Fehlermeldungen,
- ausreichende Lesbarkeit auf kleineren Bildschirmen,
- keine versteckten Pflichtaktionen nur per Hover,
- keine unnoetigen Modals fuer einfache Bestaetigungen,
- sinnvolle ARIA-Attribute nur dort, wo semantisches HTML nicht reicht,
- keine bewusst eingefuehrten Tastaturfallen.

Wenn eine UI-Aenderung diese Punkte nicht erfuellt, darf Codex sie nicht als fertig darstellen. Wenn Codex einzelne Punkte nicht pruefen kann, muss das im Abschlussbericht offen als nicht vollstaendig verifiziert genannt werden.

## Gemeinsame Teststrategie

Tests werden als Sicherheitsgurt vor groesseren Refactorings behandelt, besonders bei gemeinsamen Libraries.

- Tests sind Teil der Architekturarbeit und kein optionaler Nachtrag. Neue oder refaktorierte Fachlogik bekommt passende Charakterisierungs-, Unit-, Contract- oder Smoke-Tests, bevor darauf weiter aufgebaut wird.
- Vor groesseren Refactorings zuerst kleine Charakterisierungstests schreiben oder aktualisieren, die das gewuenschte bestehende Verhalten festhalten.
- Danach refaktorieren und dieselben Tests erneut laufen lassen.
- `localbase` ist Multiplikator-Code und wird strenger behandelt als einzelne Apps: Jede Aenderung an oeffentlichen LocalBase-Vertraegen braucht passende PHP-/JavaScript-Tests in LocalBase und betroffene Contract-/Smoke-Tests in den nutzenden Apps.
- App-Repos testen ihre eigene Fachlogik und die Integration mit LocalBase, duplizieren aber nicht die vollstaendige LocalBase-Testabdeckung.
- Gemeinsame Test-Helper sind sinnvoll, sobald mindestens zwei Repos dieselben Assertions, Fakes, Fixtures oder Setup-Schritte semantisch gleich brauchen. Sie bleiben klein, dependency-arm, test-only und werden in LocalBase selbst getestet, bevor Apps sie nutzen.
- Dependency-arme PHP-Smoke-Tests werden grundsätzlich in getrennten Prozessen ausgeführt; jeder Test lädt seine Abhängigkeiten selbst. Dadurch bleiben Testreihenfolge, bereits geladene Klassen und globale Zustände ohne Einfluss auf das Ergebnis.
- In der lokalen Vor-Production-Phase duerfen Apps gemeinsame LocalBase-Test-Helper pragmatisch per relativen Repo-Pfaden nutzen. Eine stabilere Packaging-/Autoload-/Import-Struktur wird erst geklaert, wenn CI, Distribution, Production-Haertung oder die Pfade selbst spuerbar bremsen.
- Jedes App-Repo soll schnelle, dependency-arme Einstiegspunkte fuer lokale Tests anbieten: `php tests/run.php` fuer PHP und `node tests/run-js.mjs` fuer JavaScript.
- Vor jedem Commit laufen die schnellen Tests des betroffenen Repos. Nach LocalBase-Aenderungen laufen zusaetzlich die schnellen Tests der betroffenen Apps.
- Bei Controller-, DI-, Migrations- oder Nextcloud-Container-Aenderungen zusaetzlich gezielte DDEV-/`occ`-Checks ausfuehren.
- Vor groesseren Architekturentscheidungen und spaeter vor Production-Releases die schnelle Suite ueber alle eigenen Apps laufen lassen.
- Ein groesseres Testframework wie PHPUnit, Pest, Vitest oder Jest wird erst eingefuehrt, wenn die einfachen Testlaeufer, Assertion-Helfer, Mocks oder Fixtures selbst spuerbar dupliziert werden oder Tests dadurch deutlich lesbarer werden.


## LocalBase in der Vor-Production-Phase

`localbase` ist gemeinsamer Multiplikator-Code, wird aber in der lokalen Vor-Production-Phase noch pragmatisch behandelt.

Codex darf interne LocalBase-Verbesserungen vornehmen, wenn sie:

- konkrete Duplizierung entfernen,
- Tests vereinfachen,
- Fachlogik neutral halten,
- keine nutzende App unbemerkt brechen,
- durch schnelle LocalBase-Tests und betroffene App-Smoke-/Contract-Tests abgesichert werden.

Strenger zu behandeln sind oeffentliche LocalBase-Vertraege, also Klassen, Funktionen, Modelle, DTOs, Services, Imports, Events oder Rueckgabeformate, die bereits von mehreren Apps genutzt werden.

Bei solchen Vertragsaenderungen muss Codex vor der Umsetzung mindestens:

1. die bekannten Call-Sites in den nutzenden Apps suchen,
2. den alten und neuen Vertrag knapp beschreiben,
3. einen pragmatischen Migrationspfad nennen,
4. passende LocalBase-Tests und betroffene App-Checks benennen,
5. Simon um Freigabe bitten, ausser Simon hat die konkrete Umsetzung bereits ausdruecklich verlangt.

Solange keine Production-Freigabe besteht, muessen keine schweren Packaging-, Versionierungs- oder SemVer-Prozesse eingefuehrt werden. Rueckwaertskompatibilitaet bleibt wuenschenswert, aber einfache, klar getestete Anpassungen sind erlaubt, wenn alle betroffenen Apps im Workspace mitgezogen werden.


## Stop-Regeln fuer riskante Aenderungen

Codex muss die Arbeit unterbrechen und Simon zuerst einen Plan mit Risiko, betroffenen Dateien, Teststrategie und Rueckbauweg vorlegen, wenn eine Aufgabe eines dieser Themen beruehrt:

- Datenbank-Schema, Migrationen oder bestehende produktive Daten,
- Berechtigungen, Gruppenlogik, Rollen, CSRF, Authentifizierung oder Zugriffsschutz,
- oeffentliche LocalBase-Vertraege oder Code, der von mehreren Apps genutzt wird,
- Dateiablage, Dateipfade, Uploads, Downloads oder Dokumenterzeugung,
- Loeschungen, Umbenennungen oder Verschiebungen groesserer Codebereiche,
- Aenderungen an DDEV-, Docker-, Nextcloud- oder `occ`-Konfiguration,
- Cross-App-Aenderungen in mehr als einem App-Repo,
- Tests, die nur durch breite Refactorings wieder gruen werden,
- jede Aenderung, bei der Codex den Rueckbauweg nicht klar benennen kann.

Ausnahme: Wenn Simon die Umsetzung ausdruecklich verlangt, zum Beispiel mit Formulierungen wie "setz das jetzt um", "mach die Aenderung", "du darfst fortfahren" oder einer vergleichbar klaren Freigabe, darf Codex nach kurzer Benennung des Risikos weiterarbeiten. Auch dann gilt: Aenderungen klein halten, keine Commits ohne Freigabe, Tests ausfuehren und offene Risiken im Abschlussbericht nennen.

Bis zur Freigabe darf Codex in diesen Faellen nur lesen, analysieren und einen minimalen Aenderungsplan vorschlagen.

## Codex-Credit-Spar-Strategie

Codex soll sparsam arbeiten, ohne Pruefsicherheit an den falschen Stellen zu verlieren.

- Zuerst lokalen Kontext mit `rg`, gezielten Dateiauszuegen und Git-Status klaeren; keine breiten Re-Scans ohne neuen Anlass.
- Kleine, naheliegende Aenderungen direkt im betroffenen Repo erledigen und nur die relevanten Tests laufen lassen.
- Teure oder langsame Checks wie DDEV, vollstaendige App-Suiten oder browsernahe Pruefungen buendeln und erst ausfuehren, wenn lokale Syntax-/Unit-/Smoke-Checks sauber sind.
- Keine Internetrecherche, Dependency-Installation oder Plugin-/Tool-Suche ohne konkreten Bedarf.
- Subagents oder kleinere Spezialagenten nur fuer wirklich unabhaengige, groessere Such- oder Audit-Aufgaben einsetzen; lineare Codeaenderungen bleiben beim Hauptagenten, damit kein Kontext doppelt bezahlt wird.
- Wenn Modellwahl verfuegbar ist, einfache mechanische Aufgaben mit einem kleineren Modell bearbeiten und groessere Architekturentscheidungen, Sicherheitsfragen oder schwierige Refactorings mit einem staerkeren Modell.
- Kleine, abgeschlossene Aenderungseinheiten bevorzugen, damit nach einem Fehler nicht dieselbe Analyse wiederholt werden muss. Commits werden nur nach ausdruecklicher Freigabe durch Simon vorbereitet oder ausgefuehrt.

### Subagent-Orchestrierung

Codex darf bei komplexeren Aufgaben einfache Subagents einsetzen, um Kontextarbeit zu parallelisieren und den Hauptagenten schlank zu halten. Subagents sind nur sinnvoll, wenn sie klar abgegrenzte, lesende Such-, Analyse- oder Audit-Aufgaben erledigen. Sie sollen keine linearen Codeänderungen durchführen, keine Dateien schreiben, keine Commits vorbereiten und keine eigenen Architekturentscheidungen treffen.

Der Hauptagent bleibt verantwortlich für:

* das Verstehen der Nutzeranforderung,
* die Auswahl der betroffenen App und des richtigen Repos,
* das Lesen der Parent-`AGENTS.md` und der jeweiligen App-`AGENTS.md`,
* die finale technische Entscheidung,
* den konkreten Patch,
* die Testauswahl,
* die Ergebnisbewertung.

Subagents arbeiten nur als Zuarbeit. Ihre Ergebnisse werden vom Hauptagenten geprüft, zusammengeführt und gegen die Projektregeln bewertet.

#### Wann Subagents eingesetzt werden dürfen

Subagents dürfen eingesetzt werden, wenn mindestens eines zutrifft:

* Die Aufgabe betrifft mehrere voneinander unabhängige Suchräume, zum Beispiel PHP-Backend, JavaScript-Frontend und Templates.
* Es soll eine größere Codebasis nach bestimmten Mustern durchsucht werden, ohne sofort Änderungen vorzunehmen.
* Es gibt getrennte Audit-Fragen, zum Beispiel Sicherheit, Tests, Datenmodell oder UI-Auswirkungen.
* Eine Änderung betrifft mehrere Apps oder `localbase`, und die betroffenen Stellen sollen zunächst nur gefunden werden.
* Der Hauptagent würde sonst mehrfach große Dateibereiche lesen oder dieselbe Codebasis wiederholt scannen.

Subagents sollen nicht eingesetzt werden bei:

* kleinen mechanischen Änderungen,
* eindeutig lokalisierter Fehlerbehebung,
* reinen CSS-/Text-/Template-Korrekturen,
* linearen Refactorings in wenigen Dateien,
* Aufgaben, bei denen der Subagent denselben Kontext wie der Hauptagent vollständig lesen müsste.

#### Anzahl und Stärke der Subagents

Die Anzahl der Subagents muss klein bleiben.

* Standard: kein Subagent.
* Bei klar trennbarer Such- oder Analysearbeit: 1 bis 2 Subagents.
* Bei größeren Architektur-, Sicherheits- oder Cross-App-Prüfungen: maximal 3 Subagents.
* Mehr als 3 Subagents nur vorschlagen, nicht selbstständig starten.

Wenn Modellwahl oder Reasoning-Effort verfügbar ist:

* Subagents für reine Suche, Dateikartierung, einfache Pattern-Erkennung oder CSS-/Template-Auswirkungen mit kleinerem Modell und niedrigem Reasoning-Effort starten.
* Subagents für Tests, Contract-Fragen oder einfache Architekturvergleiche mit kleinerem Modell und mittlerem Reasoning-Effort starten.
* Sicherheits-, Berechtigungs-, Datenmodell- oder Migrationsanalysen nur dann mit stärkerem Modell oder höherem Reasoning-Effort starten, wenn sie nicht zuverlässig mit einem kleineren Subagent bearbeitet werden können.
* Ultra-/xhigh-Reasoning nicht für Subagents verwenden, außer Simon fordert es ausdrücklich oder es handelt sich um eine klar abgegrenzte Hochrisikoanalyse.

#### Standardrollen für Subagents

Codex soll Subagents nur mit einer klaren Rolle, einem engen Auftrag und einem begrenzten Rückgabeformat starten.

Geeignete Rollen:

1. **Map-Agent**

   * Zweck: relevante Dateien, Klassen, Funktionen, Routen, Services, Stores, Repositories oder Templates finden.
   * Darf: lesen, suchen, Fundstellen knapp zusammenfassen.
   * Darf nicht: bewerten, refactoren, ändern.

2. **Frontend-Agent**

   * Zweck: JavaScript-, CSS-, Template- und UI-Auswirkungen einer geplanten Änderung prüfen.
   * Darf: betroffene Komponenten, Events, Selektoren, Modelle und Renderingpfade nennen.
   * Darf nicht: Code ändern oder UI-Architektur neu entwerfen.

3. **Backend-Agent**

   * Zweck: PHP-Controller, Services, Repositories, Stores, Models, DTOs, Migrationen und DI-Verbindungen kartieren.
   * Darf: Datenflüsse und Abhängigkeiten knapp beschreiben.
   * Darf nicht: Datenmodellentscheidungen treffen oder Migrationen schreiben.

4. **Test-Agent**

   * Zweck: vorhandene Tests, fehlende Smoke-/Unit-/Contract-Tests und passende schnelle Prüfungen identifizieren.
   * Darf: konkrete Testdateien und Testbefehle vorschlagen.
   * Darf nicht: große Testframeworks einführen oder Tests ohne Freigabe breit umbauen.

5. **Security-Agent**

   * Zweck: gezielt Request-Validierung, CSRF, Berechtigungen, Escaping, QueryBuilder-Parameter, Dateipfade und Logging prüfen.
   * Darf: Risiken und konkrete Fundstellen melden.
   * Darf nicht: eigenständig Sicherheitsarchitektur umbauen.

#### Standardprompt für Subagents

Subagents sollen nach diesem Muster beauftragt werden:

```text
Du bist ein lesender Spezialagent in diesem Nextcloud-App-Workspace.

Aufgabe:
<enge Aufgabe in einem Satz>

Kontextgrenzen:
- Lies die relevante Parent-AGENTS.md-Regel und die App-AGENTS.md nur soweit noetig.
- Arbeite nur im betroffenen Repo oder in den ausdruecklich genannten Repos.
- Nutze gezielte Suche mit rg und kurze Dateiauszuege.
- Keine Dateien aendern.
- Keine Commits, kein Staging, kein Push.
- Keine Dependency-Installation.
- Keine Internetrecherche.
- Keine Shell-Befehle mit Schreibwirkung ausfuehren.
- Kein eskalierter Zugriff durch Subagents.
- Keine DDEV-, Docker-, `occ`-, Git- oder Installationsbefehle ausfuehren, ausser der Hauptagent hat fuer genau diesen lesenden Check eine ausdrueckliche Freigabe erteilt.
- Keine breiten Re-Scans ohne konkreten Anlass.

Rueckgabeformat:
1. Betroffene Dateien und Symbole.
2. Relevante Fundstellen mit kurzer Begruendung.
3. Risiken oder offene Fragen.
4. Maximal 5 konkrete Empfehlungen fuer den Hauptagenten.

Halte die Antwort knapp. Keine vollstaendigen Dateien ausgeben.
```

#### Ergebnisverwertung durch den Hauptagenten

Nach Subagent-Rueckgaben muss der Hauptagent:

1. doppelte oder widerspruechliche Ergebnisse zusammenführen,
2. Fundstellen gegen Parent- und App-`AGENTS.md` prüfen,
3. entscheiden, welche Ergebnisse relevant sind,
4. einen minimalen Änderungsplan formulieren,
5. erst danach Dateien ändern,
6. nach der Änderung passende schnelle Tests ausführen,
7. offen nennen, welche Subagent-Ergebnisse genutzt oder verworfen wurden.

Subagent-Ergebnisse sind Hinweise, keine Autoritaet. Bei Widerspruch gelten die aktuelle Nutzeranweisung, die jeweilige App-`AGENTS.md`, die Parent-`AGENTS.md`, der aktuelle Code und die Testergebnisse.

#### Learnings aus Subagent-Ergebnissen

Subagents duerfen moegliche Learning-Kandidaten nur markieren. Sie duerfen keine dauerhaften Regeln formulieren oder speichern.

Der Hauptagent prueft Learning-Kandidaten aus Subagent-Ergebnissen gegen diese Kriterien:

- Ist das Ergebnis reproduzierbar oder belegt?
- Ist es kuenftig wiederverwendbar?
- Gehoert es in Parent, App-`AGENTS.md`, `docs/`, Tests oder Codekommentare?
- Ist es eine Regel, ein To-do, eine technische Beobachtung oder nur ein einmaliger Befund?

Nur der Hauptagent darf Simon einen Learning-Vorschlag im Standardformat vorlegen.

## Gemeinsame Sicherheitsregeln

Bei App-Aenderungen ist in den jeweiligen App-Repos zu pruefen:

- Request-Parameter validieren und typisieren.
- Ausgaben in Templates escapen.
- API-Aktionen gegen CSRF absichern; `NoCSRFRequired` nur verwenden, wenn es bewusst begruendet ist.
- Berechtigungen pruefen.
- Keine SQL-Fragmente aus Request-Daten bauen.
- QueryBuilder-Parameter gebunden uebergeben.
- Dateipfade normalisieren und nicht ungeprueft aus Eingaben zusammensetzen.
- Keine Secrets in Repository, Logs oder erzeugte Dokumente schreiben.
- Personenbezogene Daten in Logs minimieren.


## Testdaten und generierte Testfaelle

Codex darf Testfaelle generieren, muss dabei aber kuenstliche, neutrale und datenschutzarme Testdaten verwenden.

Nicht erlaubt sind:

- echte personenbezogene Daten,
- echte BR-Faelle,
- echte Beschaeftigtendaten,
- echte Kund*innen-/ASN-Daten,
- echte interne Dokumentinhalte,
- echte Mailinhalte,
- echte Gesundheits-, Konflikt- oder Beschlussdetails.

Fuer Tests, Fixtures, Screenshots, Logs, Beispieldaten und Dokumentation sind synthetische Daten zu verwenden, zum Beispiel neutrale Namen, abstrakte Fallnummern, technische Platzhalter und frei erfundene Inhalte.

Generierte Tests muessen bestehendes oder ausdruecklich gewuenschtes Verhalten pruefen. Codex darf keine Tests erzeugen, die stillschweigend neues Fachverhalten festschreiben, ohne dieses vorher als Annahme oder Aenderung zu benennen.

Wenn Codex Tests aus einer Beobachtung ableitet, muss klar sein:

- Welches Verhalten wird festgehalten?
- Ist es bestehendes Verhalten, gewuenschtes neues Verhalten oder eine Annahme?
- Welche Dateien oder Funktionen sind betroffen?
- Welche schnellen Testbefehle pruefen das Verhalten?

Bei unklarer Fachlogik muss Codex zuerst einen Testvorschlag machen, statt durch generierte Tests eine fachliche Entscheidung zu erzwingen.

## Dokumentation und Learnings

Wenn bei der Arbeit ein echtes, wiederverwendbares Projekt-Learning entsteht, soll Codex vorschlagen, es zu dokumentieren. Die Ergaenzung erfolgt erst nach ausdruecklicher Freigabe.

Speicherort:

- App-spezifische Fachlogik, Zielprozesse, Tests und lokale Architekturentscheidungen: jeweilige App-`AGENTS.md`.
- App-uebergreifende Arbeitsweise, DDEV-Regeln, Repo-Trennung, neue-App-Checklisten und gemeinsame Architekturprinzipien: Parent-`AGENTS.md` und `docs/`.
- Wenn ein Learning beide Ebenen betrifft, im Parent beschreiben und in den betroffenen App-`AGENTS.md` als konkrete Arbeitsregel wiederholen.
- Regeln sollen dort stehen, wo Codex sie beim Arbeiten tatsaechlich liest.

### Kriterien fuer Projekt-Learnings

Ein Learning darf nur zur Dokumentation vorgeschlagen werden, wenn es mindestens eines dieser Kriterien erfuellt:

- Es korrigiert eine vorherige falsche oder riskante Annahme.
- Es beschreibt eine wiederkehrende Projektbesonderheit, die bei kuenftigen Aufgaben erneut relevant ist.
- Es betrifft eine stabile Architektur-, Test-, DDEV-, Repo-, LocalBase-, Sicherheits- oder Nextcloud-App-Regel.
- Es verhindert wahrscheinlich kuenftige Fehlentscheidungen, doppelte Arbeit oder falsche Standardloesungen.
- Es wurde durch Code, Test, Logausgabe, reproduzierbares Verhalten oder Simon bestaetigt.

Nicht als Learning speichern:

- einmalige Zwischenstaende,
- blosse Vermutungen,
- temporaere Workarounds ohne bestaetigten Nutzen,
- aufgabenspezifische To-dos,
- reine Zusammenfassungen der gerade erledigten Arbeit,
- Informationen, die nur fuer die aktuelle Session gelten,
- private oder sensible Inhalte,
- Details, die besser in Codekommentare, Tests oder normale Projektdokumentation gehoeren.

Wenn ein moegliches Learning plausibel, aber noch nicht bestaetigt ist, muss Codex es als Vorschlag mit Status `unbestaetigt` kennzeichnen und zuerst eine Pruefung oder Freigabe durch Simon anfordern.

### Format fuer Learning-Vorschlaege

Wenn Codex ein Learning vorschlaegt, soll es nicht direkt in Dateien schreiben, sondern zuerst diesen Vorschlag machen:

```text
Moegliches Learning:
- Ebene: Parent / App / beide
- Betroffene Datei: <AGENTS.md oder docs/...>
- Status: verifiziert / plausibel / unbestaetigt / verworfen
- Grund: Warum ist das kuenftig wiederverwendbar?
- Vorgeschlagener Regeltext:
  <kurzer, konkreter Text>
```

Codex darf ein Learning erst nach ausdruecklicher Freigabe durch Simon in eine `AGENTS.md` oder Dokumentationsdatei einbauen.

Human-lesbare Workspace-Dokumentation:

    docs/workspace.md

Die alte Datei `00_ki_projektkonfiguration_br_nextcloud_apps.md` ist nur noch ein Kompatibilitaets-Hinweis und keine zweite Regelquelle.


## Abschlussbericht nach Aenderungen

Nach jeder Code- oder Dokumentationsaenderung meldet Codex knapp:

1. Welche Anforderung umgesetzt wurde.
2. Welche Dateien geaendert wurden.
3. Welche Tests oder Checks ausgefuehrt wurden.
4. Welche Tests oder Checks nicht ausgefuehrt wurden und warum.
5. Welche Risiken oder offenen Punkte bleiben.
6. Ob ein Learning-Kandidat entstanden ist.
7. Ob ein Commit vorbereitet werden soll oder nicht.

Codex darf eine Aufgabe nicht als vollstaendig abgeschlossen darstellen, wenn relevante Tests nicht gelaufen sind oder ein Check wegen Sandbox-, DDEV- oder Docker-Grenzen nicht ausgefuehrt werden konnte. In diesem Fall muss der Status als `teilweise geprueft` oder `nicht vollstaendig verifiziert` benannt werden.

## Git-Regeln

Keine Commits, kein Push und kein Deployment ohne ausdrueckliche Freigabe durch Simon.

Vor Commits immer zeigen:

    git status --short
    git diff --stat
    git diff --name-only

Nicht ohne ausdrueckliche Freigabe:

    git add .
    git commit
    git push

Statt `git add .` Dateien gezielt stagen.

Git-Befehle, die den Index, Commits oder Refs schreiben, immer aus dem jeweils betroffenen Repo-Root ausfuehren:

- Parent-/DDEV-/Meta-Aenderungen: `~/projects/br-nextcloud-apps`
- BRTop-Aenderungen: `~/projects/br-nextcloud-apps/brtop`
- AdPlaner-Aenderungen: `~/projects/br-nextcloud-apps/adplaner`
- BRStunden-Aenderungen: `~/projects/br-nextcloud-apps/brstunden`
- LocalBase-Aenderungen: `~/projects/br-nextcloud-apps/localbase`
- Berechtigungsmatrix-Aenderungen: `~/projects/br-nextcloud-apps/br_permission_matrix`
- AD-Kalender-Aenderungen: `~/projects/br-nextcloud-apps/adcalendar`
- AD-Urlaub-Aenderungen: `~/projects/br-nextcloud-apps/adurlaub`
- OrgSuite-Aenderungen: `~/projects/br-nextcloud-apps/orgsuite`
- AD-Raumplaner-Aenderungen: `~/projects/br-nextcloud-apps/adroom`

In Codex-Sessions kann das Schreiben in `.git` je nach Sandbox-Kontext eskalierten Zugriff benoetigen. Das ist dann ein Sandbox-Thema, kein Hinweis auf einen kaputten Git-Stand.

## Arbeitsweise fuer Codex

Vor Parent-Aenderungen:

1. Parent-`AGENTS.md` vollstaendig lesen.
2. `git status --short` im Parent pruefen.
3. Relevante Meta-/DDEV-/Dokumentationsdateien lesen.
4. Problem knapp benennen.
5. Minimalen passenden Patch anwenden oder bei grossen Aufraeumarbeiten die betroffenen Dateien klar benennen.

Nach Parent-Aenderungen:

1. `git status --short` pruefen.
2. `git diff --stat` pruefen.
3. `git diff --name-only` pruefen.
4. Relevante grep-/Konsistenzpruefung ausfuehren.
5. Ergebnis knapp melden.

Wenn die Aufgabe App-Code betrifft:

1. In das App-Repo wechseln.
2. Dortige `AGENTS.md` vollstaendig lesen.
3. Dortigen Git-Status pruefen.
4. Parent-Regeln zur Repo-Trennung weiter beachten.
