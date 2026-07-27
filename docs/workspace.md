# BR Nextcloud Apps Workspace

## Zweck

Der Root dieses Repositorys ist der Parent-/Meta-Workspace fuer die gemeinsame
lokale Nextcloud-DDEV-Umgebung und app-uebergreifende Dokumentation. In
Befehlsbeispielen bezeichnet `${WORKSPACE_ROOT}` den lokal ermittelten
absoluten Pfad dieses Roots; der Pfad ist keine Projektinvariante.

Der Parent enthaelt keine deploybare App. Deploybare Apps liegen als eigene Git-Repositories neben dem DDEV-Projekt.

## Verzeichnisstruktur

```text
br-nextcloud-apps/
|-- AGENTS.md
|-- .agents/skills/
|-- .codex/
|   |-- config.toml
|   `-- agents/
|-- 00_ki_projektkonfiguration_br_nextcloud_apps.md
|-- br-nextcloud-apps.code-workspace
|-- docs/
|   `-- workspace.md
|-- nextcloud-dev/
|   `-- .ddev/
|       |-- config.yaml
|       |-- docker-compose.brtop.yaml
|       |-- docker-compose.adplaner.yaml
|       |-- docker-compose.brstunden.yaml
|       |-- docker-compose.localbase.yaml
|       |-- docker-compose.br_permission_matrix.yaml
|       |-- docker-compose.adcalendar.yaml
|       |-- docker-compose.adurlaub.yaml
|       |-- docker-compose.orgsuite.yaml
|       `-- docker-compose.adroom.yaml
|-- brtop/      # eigenes Git-Repo, im Parent ignoriert
|-- adplaner/   # eigenes Git-Repo, im Parent ignoriert
|-- brstunden/  # eigenes Git-Repo, im Parent ignoriert
|-- localbase/  # eigenes Git-Repo, gemeinsame lokale Basisbausteine
|-- br_permission_matrix/ # eigenes Git-Repo, Berechtigungsmatrix
|-- adcalendar/ # eigenes Git-Repo, Dienst- und Terminplanung
|-- adurlaub/   # eigenes Git-Repo, Urlaubsplanung
|-- orgsuite/   # eigenes Git-Repo, gemeinsame AD-/BR-Navigation
|-- adroom/     # eigenes Git-Repo, Raumplanung
|-- adrecruitment/ # eigenes Git-Repo, Bewerbungs- und Recruitingprozesse
`-- ad-suite/   # eigenes Git-Repo, öffentliche Produktdokumentation
```

## Aktuelle App-Repos

Die folgende Tabelle ist eine nicht-kanonische, human-lesbare Übersicht. Die vollständige technische Repository-Liste wird ausschließlich aus `config/workspace-repositories.tsv` abgeleitet.

| App | App-ID | App-Repo | Lokale URL |
| --- | --- | --- | --- |
| BRTop | `brtop` | `brtop/` | `https://nextcloud-dev.ddev.site/apps/brtop/` |
| AdPlaner | `adplaner` | `adplaner/` | `https://nextcloud-dev.ddev.site/apps/adplaner/` |
| BRStunden | `brstunden` | `brstunden/` | `https://nextcloud-dev.ddev.site/apps/brstunden/` |
| LocalBase | `localbase` | `localbase/` | keine Navigation |
| Berechtigungsmatrix | `br_permission_matrix` | `br_permission_matrix/` | `https://nextcloud-dev.ddev.site/apps/br_permission_matrix/` |
| AD Kalender | `adcalendar` | `adcalendar/` | `https://nextcloud-dev.ddev.site/apps/adcalendar/` |
| AD Urlaub | `adurlaub` | `adurlaub/` | `https://nextcloud-dev.ddev.site/apps/adurlaub/` |
| AD-/BR-Suite | `orgsuite` | `orgsuite/` | `https://nextcloud-dev.ddev.site/apps/orgsuite/ad` und `/br` |
| AD Raumplaner | `adroom` | `adroom/` | `https://nextcloud-dev.ddev.site/apps/adroom/` |
| AD Recruitment | `adrecruitment` | `adrecruitment/` | `https://nextcloud-dev.ddev.site/apps/adrecruitment/` |

Die öffentliche Produktübersicht und Release-Unterlagen liegen im getrennten Repository `ad-suite/`; es enthält keinen deploybaren App-Code und keinen Nextcloud-Mount.

App-spezifische Regeln stehen in der jeweiligen App-`AGENTS.md`. Jede App
führt außerdem die gemeinsamen Skills `work-in-nextcloud-app` und
`test-driven-change` als normale lokale Dateien unter `.agents/skills/` mit.
Dadurch sind Regeln und Skills beim direkten Öffnen eines einzelnen
App-Repositories vollständig auflösbar; die Parent-Dateien sind keine
Laufzeitabhängigkeit. Die Parent-Fassungen sind kanonisch, das
Repositorymanifest benennt beide Pflicht-Skills und die Strukturprüfung
erzwingt bytegleiche lokale Kopien.

## Repo-Trennung

- Der Parent ist nur Meta-/DDEV-/Dokumentationskontext.
- `brtop/`, `adplaner/`, `brstunden/`, `localbase/`, `br_permission_matrix/`, `adcalendar/`, `adurlaub/`, `orgsuite/`, `adroom/`, `adrecruitment/` und `ad-suite/` sind eigene Git-Repositories.
- Der Parent ignoriert App-Verzeichnisse per `.gitignore`.
- App-Code darf im Parent nicht getrackt, gestaged oder committed werden.
- App-Code wird nur im App-Repo geaendert und nur nach ausdruecklichem Auftrag.
- Neue deploybare Apps bekommen eigene Git-Repos, eigene `AGENTS.md` und eigene `.gitignore`.

## DDEV

DDEV-Projekt:

```bash
cd "${WORKSPACE_ROOT}/nextcloud-dev"
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
ddev exec -d /var/www/html/html php occ app:list | grep -i localbase
ddev exec -d /var/www/html/html php occ app:list | grep -i br_permission_matrix
ddev exec -d /var/www/html/html php occ app:list | grep -i adcalendar
ddev exec -d /var/www/html/html php occ app:list | grep -i adurlaub
ddev exec -d /var/www/html/html php occ app:list | grep -i orgsuite
ddev exec -d /var/www/html/html php occ app:list | grep -i adroom
ddev exec -d /var/www/html/html php occ app:list | grep -i adrecruitment
```

In Codex-Sessions koennen DDEV-Befehle wegen Docker-/Stream-FD-Zugriffen eskalierten Zugriff brauchen. Das ist dann ein Sandbox-Thema, kein Hinweis auf einen kaputten DDEV-Stand.

DDEV und Produktion sind getrennte Umgebungen. DDEV-Pfade, DDEV-Benutzer, Containerpfade, PHP-Binaries, Datenbankzugänge und andere lokale Annahmen dürfen nie auf Hosting oder Produktion übertragen werden. In der Zielumgebung müssen Produktionspfade, Benutzer, reale `apps_paths`, PHP-Binary und CLI-Memory-Limit separat ermittelt werden. Jeder Wechsel zwischen DDEV und Produktion wird ausdrücklich als Umgebungsgrenze benannt; bei unklarer Zielumgebung wird gestoppt.

## App-Installation und Migrationen

Die lokale Nextcloud 34-Umgebung hat keinen `occ migrations:migrate`-Befehl. App-Migrationen laufen beim Aktivieren einer App mit `occ app:enable <app-id>` bzw. ueber `occ upgrade`, wenn `occ status` `needsDbUpgrade: true` meldet.

Nach App-Aktivierung oder Updates pruefen:

```bash
ddev exec -d /var/www/html/html php occ status
ddev exec -d /var/www/html/html php occ app:list | grep -i <app-id>
```

Bei neuen Tabellen oder Background-Jobs zusätzlich direkt kontrollieren, ob die erwartete Tabelle bzw. der erwartete Eintrag in `oc_jobs` existiert.

Ein neu in `info.xml` deklarierter Background-Job wird bei einer bereits installierten App nicht allein durch Deaktivieren und erneutes Aktivieren zuverlässig als Upgrade-Schritt registriert. Deshalb die App-Version anheben, den realen Upgrade-Pfad mit `occ upgrade` ausführen und anschließend die Registrierung sowohl über `occ background-job:list` als auch direkt in `oc_jobs` verifizieren.

## Mounts

BRTop:

```text
${WORKSPACE_ROOT}/brtop
-> /var/www/html/html/custom_apps/brtop
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.brtop.yaml
```

AdPlaner:

```text
${WORKSPACE_ROOT}/adplaner
-> /var/www/html/html/custom_apps/adplaner
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.adplaner.yaml
```

BRStunden:

```text
${WORKSPACE_ROOT}/brstunden
-> /var/www/html/html/custom_apps/brstunden
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.brstunden.yaml
```

LocalBase:

```text
${WORKSPACE_ROOT}/localbase
-> /var/www/html/html/custom_apps/localbase
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.localbase.yaml
```

Berechtigungsmatrix:

```text
${WORKSPACE_ROOT}/br_permission_matrix
-> /var/www/html/html/custom_apps/br_permission_matrix
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.br_permission_matrix.yaml
```

AD Kalender:

```text
${WORKSPACE_ROOT}/adcalendar
-> /var/www/html/html/custom_apps/adcalendar
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.adcalendar.yaml
```

AD Urlaub:

```text
${WORKSPACE_ROOT}/adurlaub
-> /var/www/html/html/custom_apps/adurlaub
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.adurlaub.yaml
```

OrgSuite:

```text
${WORKSPACE_ROOT}/orgsuite
-> /var/www/html/html/custom_apps/orgsuite
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.orgsuite.yaml
```

AD Raumplaner:

```text
${WORKSPACE_ROOT}/adroom
-> /var/www/html/html/custom_apps/adroom
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.adroom.yaml
```

AD Recruitment:

```text
${WORKSPACE_ROOT}/adrecruitment
-> /var/www/html/html/custom_apps/adrecruitment
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.adrecruitment.yaml
```

Mount-Pfade muessen die tatsächliche Schreibweise des lokal ermittelten
Workspace-Roots verwenden. Eine persönliche Home-Verzeichnisstruktur ist kein
Projektvertrag.

## Neue App anlegen

Der kanonische Ablauf ist der Skill
`.agents/skills/create-nextcloud-app/SKILL.md`. Die Dokumentation ist keine
zweite Schrittquelle.

Unverzichtbare Ergebnisse des Skills sind:

- eigenes Repository, lokale `AGENTS.md`, `.gitignore` und reguläre lokale
  Kopien von `.agents/skills/work-in-nextcloud-app/SKILL.md` sowie
  `.agents/skills/test-driven-change/SKILL.md`;
- genau ein neuer Eintrag in `config/workspace-repositories.tsv` sowie daraus
  abgeleitete oder dagegen geprüfte Parent-Inventare;
- lokaler DDEV-Mount und app-lokale Fast-Tests;
- Bytegleichheit der Skillkopie und
  `REQUIRE_TRACKED_STRUCTURE=1 scripts/check-workspace-structure`.

Eine App wird erst als fertig gemeldet, wenn die Pflichtdateien in ihren
jeweiligen Repositories getrackt sind. Fehlt die Commit-Freigabe, bleibt dieser Punkt ausdrücklich offen.
Aktivierung in Nextcloud braucht eine gesonderte Freigabe.

## VS-Code Workspace

`br-nextcloud-apps.code-workspace` öffnet den Parent-Meta-Workspace und die getrennten App-/Produkt-Repos als eigene Workspace-Folder. Sie bleiben damit in VS Code sichtbar, während der Parent sie per `.gitignore` ignoriert.

## Codex-Steuerung und Verifikation

- Dauerhafte Regeln und Abbruchbedingungen: `AGENTS.md`
- Wiederkehrende Workflows: `.agents/skills/`
- Vollständige technische Repository-Liste: `config/workspace-repositories.tsv`
- Projektbezogene Sandbox- und Subagent-Grenzen: `.codex/config.toml`
- Strukturprüfung aller Repository-Roots und lokalen Skill-Ketten: `scripts/check-workspace-structure`
- Schneller Parent-Check: `scripts/check-fast`
- Schnelle Tests aller registrierten App-Repositories: `scripts/check-apps`
- Vollständiger Workspace-Check aus Parent plus allen Apps: `scripts/check-full`
- Echtes sauberes AD-Suite-Delivery-Gate: `scripts/check-ad-suite-delivery`

`check-full` ist bewusst kein Release-Urteil und baut keine Delivery-Artefakte. Das Delivery-Gate lehnt standardmäßig jedes schmutzige enthaltene Repository ab und führt den strikten Parent-Fast-Pfad genau einmal aus; ein zusätzlicher vorgelagerter `check-fast` im selben Releasepfad ist unnötig. Nur `scripts/check-ad-suite-delivery --diagnostic` akzeptiert einen schmutzigen Stand zur Fehlersuche und endet ausdrücklich mit `DIAGNOSE ABGESCHLOSSEN – KEIN RELEASE-URTEIL`.

DDEV-, HTTP- oder Rechtematrix-Smokes laufen nicht automatisch. Sie bleiben über die in `verify-ad-suite-delivery.sh` dokumentierten `RUN_*`-Variablen bewusst opt-in.

Wenn an einer App gearbeitet wird, bewusst in deren Workspace-Folder bzw. Repo-Kontext wechseln und die lokale `AGENTS.md` samt lokalem Skill lesen. Der Parent startet mit `sandbox_mode = "workspace-write"` und `approval_policy = "on-request"`, damit ausdrücklich beauftragte Änderungen sowie gezieltes Staging und Committen innerhalb des Workspaces möglich sind. Dieser technische Schreibzugriff erteilt keine fachliche Schreibfreigabe und ersetzt weder Repository-Grenzen noch Git-Regeln. Externe Connector-/MCP-Systeme bleiben separat durch Auftrag und Rollenregeln begrenzt. Parent-only-Aenderungen duerfen weiterhin nur Meta-/DDEV-/Dokumentationsdateien betreffen. Schreibende Cross-App-Arbeit ist ein ausdrücklich beauftragter Sonderlauf; `.gitignore` ersetzt diese Grenze nicht.

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
