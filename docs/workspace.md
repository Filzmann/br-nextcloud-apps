# BR Nextcloud Apps Workspace

## Zweck

`~/projects/br-nextcloud-apps` ist der Parent-/Meta-Workspace fuer die gemeinsame lokale Nextcloud-DDEV-Umgebung und app-uebergreifende Dokumentation.

Der Parent enthaelt keine deploybare App. Deploybare Apps liegen als eigene Git-Repositories neben dem DDEV-Projekt.

## Verzeichnisstruktur

```text
br-nextcloud-apps/
|-- AGENTS.md
|-- 00_ki_projektkonfiguration_br_nextcloud_apps.md
|-- br-nextcloud-apps.code-workspace
|-- docs/
|   `-- workspace.md
|-- nextcloud-dev/
|   `-- .ddev/
|       |-- config.yaml
|       |-- docker-compose.brtop.yaml
|       |-- docker-compose.adplaner.yaml
|       `-- docker-compose.brstunden.yaml
|-- brtop/      # eigenes Git-Repo, im Parent ignoriert
|-- adplaner/   # eigenes Git-Repo, im Parent ignoriert
`-- brstunden/  # eigenes Git-Repo, im Parent ignoriert
```

## Aktuelle App-Repos

| App | App-ID | App-Repo | Lokale URL |
| --- | --- | --- | --- |
| BRTop | `brtop` | `~/projects/br-nextcloud-apps/brtop` | `https://nextcloud-dev.ddev.site/apps/brtop/` |
| AdPlaner | `adplaner` | `~/projects/br-nextcloud-apps/adplaner` | `https://nextcloud-dev.ddev.site/apps/adplaner/` |
| BRStunden | `brstunden` | `~/projects/br-nextcloud-apps/brstunden` | `https://nextcloud-dev.ddev.site/apps/brstunden/` |

App-spezifische Regeln stehen in der jeweiligen App-`AGENTS.md`.

## Repo-Trennung

- Der Parent ist nur Meta-/DDEV-/Dokumentationskontext.
- `brtop/`, `adplaner/` und `brstunden/` sind eigene Git-Repositories.
- Der Parent ignoriert App-Verzeichnisse per `.gitignore`.
- App-Code darf im Parent nicht getrackt, gestaged oder committed werden.
- App-Code wird nur im App-Repo geaendert und nur nach ausdruecklichem Auftrag.
- Neue deploybare Apps bekommen eigene Git-Repos, eigene `AGENTS.md` und eigene `.gitignore`.

## DDEV

DDEV-Projekt:

```bash
cd ~/projects/br-nextcloud-apps/nextcloud-dev
```

Haeufige Befehle:

```bash
ddev start
ddev stop
ddev restart
ddev describe
ddev exec -d /var/www/html/html php occ status
ddev exec -d /var/www/html/html php occ app:list | grep -i brtop
ddev exec -d /var/www/html/html php occ app:list | grep -i adplaner
ddev exec -d /var/www/html/html php occ app:list | grep -i brstunden
```

In Codex-Sessions koennen DDEV-Befehle wegen Docker-/Stream-FD-Zugriffen eskalierten Zugriff brauchen. Das ist dann ein Sandbox-Thema, kein Hinweis auf einen kaputten DDEV-Stand.

## App-Installation und Migrationen

Die lokale Nextcloud 34-Umgebung hat keinen `occ migrations:migrate`-Befehl. App-Migrationen laufen beim Aktivieren einer App mit `occ app:enable <app-id>` bzw. ueber `occ upgrade`, wenn `occ status` `needsDbUpgrade: true` meldet.

Nach App-Aktivierung oder Updates pruefen:

```bash
ddev exec -d /var/www/html/html php occ status
ddev exec -d /var/www/html/html php occ app:list | grep -i <app-id>
```

Bei neuen Tabellen oder Background-Jobs zusaetzlich direkt kontrollieren, ob die erwartete Tabelle bzw. der erwartete Eintrag in `oc_jobs` existiert.

## Mounts

BRTop:

```text
~/projects/br-nextcloud-apps/brtop
-> /var/www/html/html/custom_apps/brtop
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.brtop.yaml
```

AdPlaner:

```text
~/projects/br-nextcloud-apps/adplaner
-> /var/www/html/html/custom_apps/adplaner
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.adplaner.yaml
```

BRStunden:

```text
~/projects/br-nextcloud-apps/brstunden
-> /var/www/html/html/custom_apps/brstunden
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.brstunden.yaml
```

Mount-Pfade muessen lowercase `~/projects/...` verwenden, nicht `~/Projects/...`.

## Neue App anlegen

1. Fachbereich und App-ID festlegen.
2. App-Verzeichnis neben `brtop/` und `adplaner/` anlegen.
3. Eigenes Git-Repo im App-Verzeichnis initialisieren.
4. Eigene `AGENTS.md` und eigene `.gitignore` im App-Repo anlegen.
5. Parent-`.gitignore` um das neue App-Verzeichnis ergaenzen.
6. DDEV-Mount unter `nextcloud-dev/.ddev/docker-compose.<app-id>.yaml` anlegen.
7. App in Nextcloud aktivieren und pruefen.
8. Parent-Dokumentation nur um Meta-/DDEV-/Mount-Informationen ergaenzen.

## VS-Code Workspace

`br-nextcloud-apps.code-workspace` oeffnet den Parent-Meta-Workspace und die App-Repos als eigene Workspace-Folder. So bleiben `brtop/`, `adplaner/` und `brstunden/` in VS Code sichtbar, waehrend der Parent sie weiterhin per `.gitignore` ignoriert.

Wenn an einer App gearbeitet wird, bewusst in deren Workspace-Folder bzw. Repo-Kontext wechseln. Parent-only-Aenderungen duerfen weiterhin nur Meta-/DDEV-/Dokumentationsdateien betreffen.

## Git-Regeln

Keine Commits, kein Push und kein Deployment ohne ausdrueckliche Freigabe durch Simon.

Vor Commits immer zeigen:

```bash
git status --short
git diff --stat
git diff --name-only
```

Nicht verwenden:

```bash
git add .
```

Dateien werden gezielt gestaged.
