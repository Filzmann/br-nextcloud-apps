# AGENTS.md – BR Nextcloud Apps

## Projekt

Lokale Nextcloud-Entwicklungsumgebung für die Betriebsrats-App `brtop`.

Projektwurzel:

    ~/projects/br-nextcloud-apps

DDEV-/Nextcloud-Projekt:

    ~/projects/br-nextcloud-apps/nextcloud-dev

App-Quellcode:

    ~/projects/br-nextcloud-apps/brtop

Lokale App-URL:

    https://nextcloud-dev.ddev.site/apps/brtop/

Nextcloud-App-ID:

    brtop

## Grundregeln

Terminal/CLI vor GUI.

Änderungen klein, prüfbar und rückbaubar halten.

Keine Commits, kein Push und kein Deployment ohne ausdrückliche Freigabe durch Simon.

DDEV wird immer aus diesem Ordner gesteuert:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev

Wichtige Befehle:

    ddev start
    ddev stop
    ddev restart
    ddev describe
    ddev launch /apps/brtop/
    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list | grep -i brtop

## Architektur- und Codequalitätsregeln für BRTop

Diese Regeln sind eigenständige Projektregeln für die Nextcloud-App `brtop`. Sie bleiben gültig, auch wenn sich Regeln in anderen Projekten ändern. Externe Projektkonfigurationen dürfen nur als Denkanstoß dienen, aber nicht als automatisch geltende Quelle.

### Nicht anwendbare Regeln

Für BRTop gelten nicht:

- WordPress-spezifische APIs, Konzepte und Prüfungen wie Shortcodes, Gutenberg-Blöcke, `$wpdb`, WordPress-Nonces, `current_user_can()`, `esc_html()` oder WordPress-Capabilities.
- Namenskonventionen anderer Projekte wie `flz_`.
- Symlink-Regeln, Staging-Workflows oder Plugin-Verzeichnisregeln aus WordPress-Projekten.
- Gemeinsame WordPress-Hilfsplugins oder deren APIs.
- Harte Demo-Daten aus externen Produktivseiten, wenn die Daten lokal aus der App oder der Entwicklungsumgebung ableitbar sind.

### Übertragene Grundprinzipien

Für BRTop gelten diese angepassten Prinzipien verbindlich:

- Controller bleiben dünn.
- Fachlogik, Datenzugriff, Darstellung, Dokumenterzeugung und Dateiablage werden getrennt.
- Wiederkehrende Logik wird nicht mehrfach in Controllern oder `main.js` dupliziert.
- Datenzugriffe laufen mittelfristig über Repository-, Mapper- oder Store-Klassen.
- Wiederkehrende Datenstrukturen werden als Modelle, DTOs oder Value Objects beschrieben, sobald rohe Arrays unübersichtlich werden.
- Größere HTML-Blöcke werden aus `templates/index.php` in Partials ausgelagert.
- Wiederkehrende Frontend-Logik wird app-intern in JavaScript-Module unter `js/components/` oder `js/modules/` ausgelagert.
- Fehler werden zentral protokolliert; Nutzer*innen erhalten sichere, knappe Meldungen ohne interne Details.
- Keine Architekturabstraktion wird vorsorglich gebaut. Auslagerung erfolgt, wenn sie konkrete Duplizierung, Testbarkeit oder Wartbarkeit verbessert.

### Zielstruktur

Die App soll schrittweise in diese Richtung wachsen:

```text
brtop/
├── appinfo/
├── css/
├── js/
│   ├── main.js
│   ├── modules/
│   └── components/
├── lib/
│   ├── AppInfo/
│   ├── Controller/
│   ├── Db/
│   ├── Model/
│   ├── Repository/
│   ├── Service/
│   └── Exception/
└── templates/
    ├── index.php
    ├── partials/
    └── odt/
```

Leere Ordner werden nicht vorsorglich angelegt. Neue Struktur entsteht erst, wenn tatsächlich Code dorthin ausgelagert wird.

### Controller-Regel

Controller dürfen:

- Requests entgegennehmen.
- Eingaben typisieren und validieren.
- Berechtigungsprüfungen anstoßen.
- Services aufrufen.
- `DataResponse`, `JSONResponse`, `TemplateResponse` oder passende Nextcloud-Antworten zurückgeben.

Controller sollen nicht dauerhaft enthalten:

- SQL- oder QueryBuilder-Details.
- ODT-XML-Erzeugung.
- Dokumentgenerator-Logik.
- Dateiablage- und Exportpfade.
- komplexe TOP-Gruppierung oder TOP-Nummerierung.
- Default-Beschlussfragen und §99-/§100-/§102-Fachlogik.
- große Textbausteine für Einladung, Protokoll oder Beschlüsse.

### Service-Regel

Fachlogik gehört in Services. Für BRTop sind insbesondere diese Services sinnvoll:

- `MeetingService`: Sitzungen anlegen, laden, prüfen.
- `AgendaService`: Standard-TOPs, Sortierung, Gruppierung, Nummerierung.
- `PersonnelCaseService`: §99-, §100- und §102-Vorgänge fachlich einordnen.
- `ResolutionService`: Beschlussfragen, Beschlusslogik und Abstimmung.
- `InvitationService`: Einladungsstruktur und Einladungstext erzeugen.
- `ProtocolService`: Protokollstruktur aus Sitzung und TOPs erzeugen.
- `DocumentGenerationService`: Einladungen, Protokolle und Beschlussdokumente koordinieren.
- `OdtTemplateRenderer`: ODT-Vorlagen befüllen, ohne das Vorlagenlayout unnötig zu zerstören.
- `FileExportService`: Dateien im Nextcloud-Dateisystem ablegen.
- `ErrorReporter` oder `BrtopLogger`: Fehler zentral protokollieren.

Services sollen möglichst wenig Framework-Code enthalten. Nextcloud-spezifische Datei-, User- und Response-Details gehören an die Ränder der App.

### Modelle, DTOs und Repositories

Bei wiederkehrenden Datenstrukturen sind Modelle oder DTOs zu prüfen. Sinnvolle Kandidaten:

- `Meeting`
- `AgendaItem`
- `PersonnelCase`
- `Resolution`
- `VoteGroup`
- `GeneratedDocument`
- `DocumentRequest`
- `DocumentResult`

Datenbankzugriffe sollen mittelfristig aus dem `ApiController` herausgezogen werden. Sinnvolle Kandidaten:

- `MeetingRepository`
- `AgendaItemRepository`
- `ResolutionRepository`
- `VoteGroupRepository`

Eine gemeinsame Bibliothek analog zu einem Datenbank-Hilfsplugin ist erst sinnvoll, wenn mindestens eine zweite eigene Nextcloud-App dieselben Repository-, DTO- oder Exportmuster verwendet. Bis dahin bleibt Wiederverwendung app-intern.

### Frontend- und UI-Regeln

`js/main.js` darf Einstiegspunkt bleiben, soll aber nicht dauerhaft alle UI-Logik enthalten.

Auslagerung ist zu prüfen für:

- API-Fetch-Wrapper.
- Sitzungsformular.
- TOP-/Vorgangsformular.
- Beschlussformular.
- Sitzungsübersicht.
- Dokumentaktionen.
- Status- und Fehlermeldungen.
- Chips, Badges, Listen und wiederkehrende UI-Zustände.

Mögliche Struktur:

```text
brtop/js/modules/api.js
brtop/js/modules/notices.js
brtop/js/components/meeting-form.js
brtop/js/components/agenda-form.js
brtop/js/components/resolution-form.js
brtop/js/components/meeting-list.js
brtop/js/components/document-actions.js
```

Eine gemeinsame UI-App oder gemeinsame UI-Bibliothek ist erst sinnvoll, wenn mehrere eigene Nextcloud-Apps dieselben UI-Komponenten verwenden. Bis dahin bleiben Komponenten app-intern.

### Template-Regel

`templates/index.php` soll nur die Seitenstruktur enthalten. Wiederkehrende oder größere Blöcke gehören in Partials, zum Beispiel:

- `templates/partials/meeting-form.php`
- `templates/partials/agenda-form.php`
- `templates/partials/document-actions.php`
- `templates/partials/notices.php`

Ausgaben in Templates müssen escaped werden. Interne Fehlerdetails, Stacktraces, absolute Pfade oder Datenbankdetails werden nicht in Templates ausgegeben.

### Error-Reporting

Interne Fehler sollen zentral protokolliert werden, vorzugsweise über die Logging-Infrastruktur der Nextcloud-App und einen dünnen Wrapper-Service.

Regeln:

- Interne Exception-Details stehen im Log, nicht in der UI.
- API-Antworten enthalten höchstens kurze Fehlercodes oder sichere Meldungen.
- Fehlerkontext soll nachvollziehbar sein: Aktion, Sitzungs-ID, TOP-ID, Dokumenttyp, User-ID, Exception-Klasse und Message.
- ODT- oder Exportfehler sollen so gemeldet werden, dass Teilerfolge erkennbar bleiben.
- Keine Secrets, Tokens, unnötigen personenbezogenen Zusatzdaten oder vollständigen Stacktraces in sichtbaren Antworten ausgeben.

Beispiel für eine sichtbare Meldung:

```text
Das Protokoll konnte nicht als ODT erzeugt werden. Details stehen im Nextcloud-Log.
```

### Sicherheitsregeln für Nextcloud-Code

Bei jeder Änderung ist zu prüfen:

- Request-Parameter validieren und typisieren.
- Ausgaben in Templates escapen.
- API-Aktionen gegen CSRF absichern; `NoCSRFRequired` nur verwenden, wenn es bewusst begründet ist.
- Berechtigungen prüfen: Nutzer*innen dürfen nur eigene oder freigegebene Sitzungen sehen und ändern.
- Keine SQL-Fragmente aus Request-Daten bauen.
- QueryBuilder-Parameter gebunden übergeben.
- Dateipfade normalisieren und nicht ungeprüft aus Eingaben zusammensetzen.
- Keine Secrets in Repository, Logs oder erzeugte Dokumente schreiben.
- Personenbezogene Daten in Logs minimieren.

### Demo- und Seed-Daten

Demo- und Seed-Daten sollen bevorzugt aus lokal vorhandenen App-Daten oder bewusst gepflegten lokalen Seed-Dateien stammen.

Nicht verwenden:

- Live-Daten aus fremden Produktivsystemen.
- frei erfundene fachliche Scheindaten, wenn dadurch falsche Annahmen über BR-Abläufe entstehen.
- externe URLs als harte Datenquelle.

Wenn Demo-Daten fehlen, bleiben Felder leer oder werden als nicht verfügbar markiert.

### Refactoring-Regel

Wenn ein Stück Logik zum zweiten Mal benötigt wird, ist Auslagerung zu prüfen.

Wenn ein Stück Logik zum dritten Mal benötigt wird, soll es ausgelagert werden, außer es gibt einen klaren Grund dagegen.

Typische Auslagerungskandidaten im aktuellen BRTop-Stand:

- TOP-Gruppierung.
- TOP-Nummerierung.
- Default-Beschlussfragen.
- §99-/§100-/§102-Fachlogik.
- Dokumentstruktur für Einladung, Protokoll und Beschlüsse.
- ODT-XML-Fragmente.
- Dateinamen und Exportpfade.
- API-Fehlerantworten.
- UI-Notice-Rendering.
- API-Fetch-Wrapper im JavaScript.

### Prüfpflicht vor größeren Änderungen

Vor größeren Änderungen an Architektur oder Datenmodell:

1. Aktuellen Stand mit `git status --short` prüfen.
2. Relevante Dateien lesen.
3. Kurz benennen, was ausgelagert wird und warum.
4. Prüfen, ob bestehende Funktionen betroffen sind.
5. Migrationsbedarf nennen, falls Tabellen, Dateistrukturen oder API-Antworten geändert werden.
6. Nach der Änderung Syntax- und Funktionstests ausführen.
7. Keine Commits ohne ausdrückliche Freigabe.

### Branch-Regel für größere Änderungen

Für größere Refactorings, neue Datenmodelle oder neue Services soll ein eigener Branch verwendet werden.

Beispiele:

```bash
git checkout -b refactor/extract-agenda-service
git checkout -b refactor/repository-layer
git checkout -b feat/resolution-vote-groups
```

## Mount

Die App wird per DDEV-Bind-Mount eingebunden:

    ~/projects/br-nextcloud-apps/brtop
    -> /var/www/html/html/custom_apps/brtop

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.brtop.yaml

Der Mount muss den lowercase-Pfad `~/projects/...` verwenden. Nicht `~/Projects/...`.

## Relevante App-Dateien

    brtop/appinfo/info.xml
    brtop/appinfo/routes.php
    brtop/lib/Controller/PageController.php
    brtop/lib/Controller/ApiController.php
    brtop/lib/Service/OdtTemplateRenderer.php
    brtop/templates/index.php
    brtop/templates/odt/protokoll-template.odt
    brtop/js/main.js
    brtop/css/style.css

## Zielprozess BRTop

BRTop soll den wiederkehrenden Sitzungs- und Dokumentprozess des Betriebsrats abbilden, nicht nur einzelne Dokumente erzeugen.

Grundmodell:

- Es gibt einen Betriebsrat mit `n` Mitgliedern; die Mitgliederzahl ist eine Setup- bzw. Konfigurationsvariable.
- Ein BR-Mitglied ist ein Nextcloud-User in der Gruppe `Betriebsrat`.
- Listen, Ersatzmitglieder und Nachladungen bleiben zu Beginn bewusst außen vor, müssen aber später wieder aufgegriffen werden.
- Die reguläre BR-Sitzung findet in einem konfigurierbaren Rhythmus statt, zunächst typischerweise wöchentlich am Dienstag zu einer konfigurierbaren Uhrzeit.
- Die Einladung erfolgt an einem konfigurierbaren Wochentag vor der Sitzung, zunächst typischerweise am Freitag vorher.
- Sitzungen werden nicht automatisch vorerzeugt, sondern über „nächste Sitzung planen“ angelegt.
- Beim Erzeugen einer Einladung wird die Ladungsliste als rechtssicherer Snapshot gespeichert; spätere Gruppenänderungen dürfen alte Einladungen nicht verändern.
- Standard-TOPs und Sitzungstypen sollen konfigurierbar werden.
- TOPs und Sub-TOPs werden bis Ebene 3 mit Überschrift, Reihenfolge, fachlicher TOP-Art und späterem Protokollinhalt in der Datenbank gespeichert.
- Aus denselben gespeicherten Sitzungs- und TOP-Daten werden TOP-Liste für die Einladung, Mailtext und Protokollvorlage erzeugt.
- Alte Einladungen, Protokolle und Beschlussdokumente sollen über eine eigene Dokumentübersicht mit DB-Metadaten auffindbar sein, nicht nur über Dateipfade.
- E-Mail-Versand soll mittelfristig direkt aus der App möglich sein; die Absenderadresse muss konfigurierbar sein.
- Start-Sitzungstypen sind reguläre BR-Sitzung, Monatsgespräch, Betriebsausschuss, Ausschuss / AG und freie Sitzung. Sie sollen später konfigurierbar werden.
- Aktuelle Ausschüsse bzw. AG-Codes sind ASA, DPA, IKT, BA und IBF. Auch diese Liste soll später konfigurierbar werden.

Architekturfolge: Refactorings sollen zuerst dieses Prozessmodell, Sitzungstypen, Konfiguration, Agenda-Templates, Ladungssnapshots und Dokumentmetadaten berücksichtigen, bevor Renderer- oder Controller-Details großflächig umgebaut werden.

## Fachliche BR-Logik

Standardstruktur einer BR-Sitzung:

    1. Protokolle
    2. Personelle Angelegenheiten
    3. Arbeitsorganisatorisches
    4. Bericht aus den Sprechstunden seit der letzten Sitzung
    5. Weitere Tagesordnungspunkte

Unter TOP 2 derzeit berücksichtigt:

    2.1 Personelle Einzelmaßnahmen nach § 99 BetrVG
    2.2 Vorläufige personelle Maßnahmen nach § 100 BetrVG
    2.3 Anhörungen zu Kündigungen nach § 102 BetrVG

§ 101 BetrVG wird aktuell ignoriert.

Einladung:

- §99-, §100- und §102-Fälle dürfen kompakt unter „Personelle Angelegenheiten“ zusammengefasst werden.

Protokoll:

- §99-, §100- und §102-Fälle müssen getrennt aufgeführt werden.
- Nicht jeder TOP ist beschlussrelevant. TOPs können fachlich Gliederungspunkte, Berichte, Beratungen oder Beschlüsse sein.

Beschlüsse:

- Jeder beschlussrelevante Fall erhält ein eigenes Beschlussdokument.
- Das gilt auch dann, wenn mehrere Fälle gemeinsam abgestimmt wurden.

## Default-Beschlussfragen

Bei Zustimmungsverweigerung:

    Wer verweigert die Zustimmung zu <<Maßnahme>> und widerspricht ihr damit?

Bei allgemeinen Beschlüssen:

    Wer stimmt <<Maßnahme>> zu?

Allgemeine Beschlusstypen:

- Entsendung / Schulung
- Betriebsvereinbarung
- Ausschuss- oder Arbeitsauftrag
- Beauftragung / Verfahren
- Protokollgenehmigung
- organisatorischer Beschluss

Sonderfall §100 BetrVG:

    Wer bestreitet, dass die vorläufige Durchführung der personellen Maßnahme <<Maßnahme>> aus sachlichen Gründen dringend erforderlich ist?

Sonderfall §102 BetrVG:

    Wer widerspricht der beabsichtigten Kündigung <<Maßnahme>> gemäß § 102 BetrVG?

## ODT-Vorlage

Aktuelle Protokollvorlage:

    brtop/templates/odt/protokoll-template.odt

Anforderungen:

- Kopfzeile mit `{{GREMIENNAME}}` und `{{GREMIUM_ADRESSE}}`
- keine Seitenrahmen
- Rahmen nur um bestimmte Elemente
- Inhaltsverzeichnis als echtes ODT-Verzeichnisobjekt
- Formatvorlagen möglichst: Standard, Textkörper, Überschrift 1, Überschrift 2, Überschrift 3
- Beschlusskasten als wiederverwendbares Element
- Protokoll und Beschlussdokumente sollen denselben Beschlusskasten verwenden

## Prüfungen nach Änderungen

PHP-Syntax:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev
    ddev exec php -l /var/www/html/html/custom_apps/brtop/lib/Controller/ApiController.php
    ddev exec php -l /var/www/html/html/custom_apps/brtop/lib/Service/OdtTemplateRenderer.php
    ddev exec php -l /var/www/html/html/custom_apps/brtop/templates/index.php

Logs:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev
    ddev exec -d /var/www/html/html tail -n 160 data/nextcloud.log
    ddev logs -s web | tail -n 120

Bei JS-/CSS-/Template-Cacheproblemen App-Version in `brtop/appinfo/info.xml` erhöhen und App neu aktivieren:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev
    ddev exec -d /var/www/html/html php occ app:disable brtop
    ddev exec -d /var/www/html/html php occ app:enable brtop

## Git-Regeln

Vor Commit immer zeigen:

    git status --short
    git diff --stat
    git diff --name-only

Nicht ohne ausdrückliche Freigabe:

    git add .
    git commit
    git push

Statt `git add .` gezielt Dateien hinzufügen.

Git-Befehle, die den Index, Commits oder Refs schreiben, immer aus dem Repo-Root `~/projects/br-nextcloud-apps` ausführen. Das `.git`-Verzeichnis liegt dort und nicht in `brtop/`; bei einer Codex-Session mit Workspace-Root `brtop/` muss dafür eskalierter Schreibzugriff auf die Git-Metadaten genutzt werden.

## Arbeitsweise für Codex

Vor Änderungen:

1. Relevante Dateien lesen.
2. Problem knapp benennen.
3. Minimalen Patch vorschlagen oder anwenden.
4. Keine Architekturänderung ohne Begründung.

Nach Änderungen:

1. Syntax prüfen.
2. Relevante grep-Prüfung ausführen.
3. DDEV/Nextcloud nur neu starten, wenn nötig.
4. Ergebnis knapp melden.

Wenn bei der Arbeit ein echtes, wiederverwendbares Projekt-Learning entsteht, soll Codex vorschlagen, es in `AGENTS.md` zu ergänzen. Die Ergänzung erfolgt erst nach ausdrücklicher Freigabe.
