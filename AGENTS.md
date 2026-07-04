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
    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list | grep -i brtop
    ddev exec -d /var/www/html/html php occ app:list | grep -i adplaner
    ddev exec -d /var/www/html/html php occ app:list | grep -i brstunden
    ddev exec -d /var/www/html/html php occ app:list | grep -i localbase

In Codex-Sessions koennen DDEV-Befehle im normalen Sandbox-Kontext nicht zuverlaessig auf Docker zugreifen. Wenn `ddev` mit Docker-/Stream-FD-Fehlern scheitert, ist das kein App- oder DDEV-Projektfehler; den gleichen Befehl mit eskaliertem Zugriff erneut ausfuehren. DDEV-Pruefungen deshalb buendeln, lokale PHP-/Node-Pruefungen bevorzugen und wiederverwendbare Prefix-Freigaben fuer `ddev exec` nutzen.

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
- Neue Modellarbeit fuehrt keine neuen `fromApi`-/`toApi`-Kompatibilitaetsaliase ein. Bestehende PHP-`toApiArray()`-Call-sites duerfen schrittweise auf `toArray()` migriert werden, wenn die betroffene Schicht ohnehin angefasst wird.
- Datenzugriffe laufen ueber Repository-, Mapper-, Store- oder Service-Klassen.
- Services arbeiten bevorzugt mit Modellen/DTOs statt rohen Arrays.
- Groessere HTML-Bloecke werden aus `templates/index.php` in Partials ausgelagert.
- Wiederkehrende Frontend-Logik wird app-intern in `js/components/`, `js/modules/` oder `js/repositories/` ausgelagert.
- Fehler werden zentral protokolliert; Nutzer*innen erhalten sichere, knappe Meldungen ohne interne Details.
- Keine Architekturabstraktion wird vorsorglich gebaut. Auslagerung erfolgt, wenn sie konkrete Duplizierung, Testbarkeit oder Wartbarkeit verbessert.

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

## Dokumentation und Learnings

Wenn bei der Arbeit ein echtes, wiederverwendbares Projekt-Learning entsteht, soll Codex vorschlagen, es zu dokumentieren. Die Ergaenzung erfolgt erst nach ausdruecklicher Freigabe.

Speicherort:

- App-spezifische Fachlogik, Zielprozesse, Tests und lokale Architekturentscheidungen: jeweilige App-`AGENTS.md`.
- App-uebergreifende Arbeitsweise, DDEV-Regeln, Repo-Trennung, neue-App-Checklisten und gemeinsame Architekturprinzipien: Parent-`AGENTS.md` und `docs/`.
- Wenn ein Learning beide Ebenen betrifft, im Parent beschreiben und in den betroffenen App-`AGENTS.md` als konkrete Arbeitsregel wiederholen.
- Regeln sollen dort stehen, wo Codex sie beim Arbeiten tatsaechlich liest.

Human-lesbare Workspace-Dokumentation:

    docs/workspace.md

Die alte Datei `00_ki_projektkonfiguration_br_nextcloud_apps.md` ist nur noch ein Kompatibilitaets-Hinweis und keine zweite Regelquelle.

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
