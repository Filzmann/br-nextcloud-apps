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
|       |-- docker-compose.flzplaner.yaml
|       |-- docker-compose.brstunden.yaml
|       |-- docker-compose.localbase.yaml
|       |-- docker-compose.flz_permission_matrix.yaml
|       |-- docker-compose.flzcalendar.yaml
|       |-- docker-compose.flzurlaub.yaml
|       |-- docker-compose.orgsuite.yaml
|       |-- docker-compose.flzroom.yaml
|       |-- docker-compose.flzrecruitment.yaml
|       |-- docker-compose.flzbqplanung.yaml
|       `-- docker-compose.flz_data_protection.yaml
|-- brtop/      # eigenes Git-Repo, im Parent ignoriert
|-- flzplaner/   # eigenes Git-Repo, im Parent ignoriert
|-- brstunden/  # eigenes Git-Repo, im Parent ignoriert
|-- localbase/  # eigenes Git-Repo, gemeinsame lokale Basisbausteine
|-- flz_permission_matrix/ # eigenes Git-Repo, Berechtigungsmatrix
|-- flzcalendar/ # eigenes Git-Repo, Dienst- und Terminplanung
|-- flzurlaub/   # eigenes Git-Repo, Urlaubsplanung
|-- orgsuite/   # eigenes Git-Repo, gemeinsame FLZ-/BR-Navigation
|-- flzroom/     # eigenes Git-Repo, Raumplanung
|-- flzrecruitment/ # eigenes Git-Repo, Bewerbungs- und Recruitingprozesse
|-- flzbqplanung/ # eigenes Git-Repo, Basisqualifizierungsplanung
|-- flz_data_protection/ # eigenes Git-Repo, Datenschutz-Center
`-- flz-full-suite/   # eigenes Git-Repo, öffentliche Produktdokumentation
```

## Aktuelle App-Repos

Die folgende Tabelle ist eine nicht-kanonische, human-lesbare Übersicht. Die vollständige technische Repository-Liste wird ausschließlich aus `config/workspace-repositories.tsv` abgeleitet.

| App | App-ID | App-Repo | Lokale URL |
| --- | --- | --- | --- |
| BRTop | `brtop` | `brtop/` | `https://nextcloud-dev.ddev.site/apps/brtop/` |
| FlzPlaner | `flzplaner` | `flzplaner/` | `https://nextcloud-dev.ddev.site/apps/flzplaner/` |
| BRStunden | `brstunden` | `brstunden/` | `https://nextcloud-dev.ddev.site/apps/brstunden/` |
| LocalBase | `localbase` | `localbase/` | keine Navigation |
| Berechtigungsmatrix | `flz_permission_matrix` | `flz_permission_matrix/` | `https://nextcloud-dev.ddev.site/apps/flz_permission_matrix/` |
| Filzmann Kalender | `flzcalendar` | `flzcalendar/` | `https://nextcloud-dev.ddev.site/apps/flzcalendar/` |
| Filzmann Urlaubsplanung | `flzurlaub` | `flzurlaub/` | `https://nextcloud-dev.ddev.site/apps/flzurlaub/` |
| FLZ-/BR-Suite | `orgsuite` | `orgsuite/` | `https://nextcloud-dev.ddev.site/apps/orgsuite/flz` und `/br` |
| Filzmann Raumplaner | `flzroom` | `flzroom/` | `https://nextcloud-dev.ddev.site/apps/flzroom/` |
| Filzmann Recruitment | `flzrecruitment` | `flzrecruitment/` | `https://nextcloud-dev.ddev.site/apps/flzrecruitment/` |
| Filzmann BQ-Planer | `flzbqplanung` | `flzbqplanung/` | `https://nextcloud-dev.ddev.site/apps/flzbqplanung/` |
| Datenschutz-Center | `flz_data_protection` | `flz_data_protection/` | `https://nextcloud-dev.ddev.site/apps/flz_data_protection/` |

Die öffentliche Produktübersicht und Release-Unterlagen liegen im getrennten Repository `flz-full-suite/`; es enthält keinen deploybaren App-Code und keinen Nextcloud-Mount.

## Lokale Test- und Demokonten

Für alle ausschließlich in der lokalen Entwicklungsumgebung erzeugten Test-
und Demokonten gilt: Das initiale Passwort entspricht exakt dem Benutzernamen.
Diese bewusst einfache Vorgabe dient der lokalen manuellen Abnahme und darf
weder in Produktions-, Staging- oder öffentlich erreichbare Umgebungen noch
in echte Konten oder externe Benutzer-Backends übernommen werden. Vorhandene
fremde, produktive oder LDAP-verwaltete Konten werden dafür niemals
umgewidmet. App-spezifische Demo-Packs verwenden die gemeinsame lokale
Provisionierung; abweichende lokale Testskripte halten denselben Vertrag ein.

App-spezifische Regeln stehen in der jeweiligen App-`AGENTS.md`. Jede App
führt außerdem die gemeinsamen Skills `work-in-nextcloud-app` und
`test-driven-change` als normale lokale Dateien unter `.agents/skills/` mit.
Dadurch sind Regeln und Skills beim direkten Öffnen eines einzelnen
App-Repositories vollständig auflösbar; die Parent-Dateien sind keine
Laufzeitabhängigkeit. Die Parent-Fassungen sind kanonisch, das
Repositorymanifest benennt beide Pflicht-Skills und die Strukturprüfung
erzwingt bytegleiche lokale Kopien.

Die Governance-Hierarchie steht kanonisch in
`docs/parent-governance-contract.md`. Ihr versionierter Block wird zusätzlich
vollständig in jeder Subrepository-`AGENTS.md` mitgeführt: Repository-lokale Regeln bleiben bei
einem Einzel-Checkout vollständig, dürfen anwendbare Parent-Verträge aber nur
konkretisieren oder verschärfen. Der Parent-Contract-Test prüft jede im
Repositorymanifest registrierte Subrepository auf eine bytegleiche Projektion.

## Repo-Trennung

- Der Parent ist nur Meta-/DDEV-/Dokumentationskontext.
- `brtop/`, `flzplaner/`, `brstunden/`, `localbase/`, `flz_permission_matrix/`, `flzcalendar/`, `flzurlaub/`, `orgsuite/`, `flzroom/`, `flzrecruitment/`, `flzbqplanung/`, `flz_data_protection/` und `flz-full-suite/` sind eigene Git-Repositories.
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
ddev exec -d /var/www/html/html php occ app:list | grep -i flzplaner
ddev exec -d /var/www/html/html php occ app:list | grep -i brstunden
ddev exec -d /var/www/html/html php occ app:list | grep -i localbase
ddev exec -d /var/www/html/html php occ app:list | grep -i flz_permission_matrix
ddev exec -d /var/www/html/html php occ app:list | grep -i flzcalendar
ddev exec -d /var/www/html/html php occ app:list | grep -i flzurlaub
ddev exec -d /var/www/html/html php occ app:list | grep -i orgsuite
ddev exec -d /var/www/html/html php occ app:list | grep -i flzroom
ddev exec -d /var/www/html/html php occ app:list | grep -i flzrecruitment
ddev exec -d /var/www/html/html php occ app:list | grep -i flzbqplanung
ddev exec -d /var/www/html/html php occ app:list | grep -i flz_data_protection
```

In Codex-Sessions koennen DDEV-Befehle wegen Docker-/Stream-FD-Zugriffen eskalierten Zugriff brauchen. Das ist dann ein Sandbox-Thema, kein Hinweis auf einen kaputten DDEV-Stand.

DDEV und Produktion sind getrennte Umgebungen. DDEV-Pfade, DDEV-Benutzer, Containerpfade, PHP-Binaries, Datenbankzugänge und andere lokale Annahmen dürfen nie auf Hosting oder Produktion übertragen werden. In der Zielumgebung müssen Produktionspfade, Benutzer, reale `apps_paths`, PHP-Binary und CLI-Memory-Limit separat ermittelt werden. Jeder Wechsel zwischen DDEV und Produktion wird ausdrücklich als Umgebungsgrenze benannt; bei unklarer Zielumgebung wird gestoppt.

### Nextcloud-Aktualität und Release-Kompatibilität

Die lokale DDEV-Laufzeit wird regelmäßig mit der neuesten offiziell
veröffentlichten stabilen Version aus dem Git-Repository
`nextcloud/server` abgeglichen. Der Vergleich verwendet den gepinnten Tag und
Commit, dessen `version.php` sowie die tatsächliche Ausgabe von `occ status`.
Nach einem freigegebenen Update belegen die vorhandenen schmalen DDEV- und
Workspace-Smokes, dass auf dieser Version gearbeitet wird. Der Ablauf und die
Nachweisgrenze stehen im Skill `verify-nextcloud-future-compatibility`; der
Aktualitätscheck allein ändert keine App-Metadaten und ist kein Releaseurteil.

Die vollständige Matrix vom deklarierten Minimum bis zu den offiziell
benannten testbaren Zukunftsversionen wird erst beim Erstellen eines
veröffentlichungsfähigen Release-Candidates ausgeführt. Dabei muss jede App
die für diesen Kandidaten aktuelle openDesk-Nextcloud-Hauptversion einschließen;
die höchste lückenlos grüne Version bestimmt `max-version`.

## App-Installation und Migrationen

App-Migrationen laufen beim Aktivieren einer App mit `occ app:enable <app-id>`
bzw. ueber `occ upgrade`, wenn `occ status` `needsDbUpgrade: true` meldet. Vor
der Verwendung eines versionsabhängigen Einzelbefehls wird dessen Verfügbarkeit
in der tatsächlich laufenden Nextcloud-Version geprüft.

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

FlzPlaner:

```text
${WORKSPACE_ROOT}/flzplaner
-> /var/www/html/html/custom_apps/flzplaner
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flzplaner.yaml
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
${WORKSPACE_ROOT}/flz_permission_matrix
-> /var/www/html/html/custom_apps/flz_permission_matrix
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flz_permission_matrix.yaml
```

Filzmann Kalender:

```text
${WORKSPACE_ROOT}/flzcalendar
-> /var/www/html/html/custom_apps/flzcalendar
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flzcalendar.yaml
```

Filzmann Urlaubsplanung:

```text
${WORKSPACE_ROOT}/flzurlaub
-> /var/www/html/html/custom_apps/flzurlaub
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flzurlaub.yaml
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

Filzmann Raumplaner:

```text
${WORKSPACE_ROOT}/flzroom
-> /var/www/html/html/custom_apps/flzroom
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flzroom.yaml
```

Filzmann Recruitment:

```text
${WORKSPACE_ROOT}/flzrecruitment
-> /var/www/html/html/custom_apps/flzrecruitment
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flzrecruitment.yaml
```

Filzmann BQ-Planer:

```text
${WORKSPACE_ROOT}/flzbqplanung
-> /var/www/html/html/custom_apps/flzbqplanung
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flzbqplanung.yaml
```

Datenschutz-Center:

```text
${WORKSPACE_ROOT}/flz_data_protection
-> /var/www/html/html/custom_apps/flz_data_protection
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.flz_data_protection.yaml
```

Mount-Pfade muessen die tatsächliche Schreibweise des lokal ermittelten
Workspace-Roots verwenden. Eine persönliche Home-Verzeichnisstruktur ist kein
Projektvertrag.

## Neue App anlegen

Der kanonische Ablauf ist der Skill
`.agents/skills/create-nextcloud-app/SKILL.md`. Die Dokumentation ist keine
zweite Schrittquelle.

Unverzichtbare Ergebnisse des Skills sind:

- eigenes Repository und die Pflichtquellen aus
  `docs/app-repository-structure.md`: `README.md`, `ROADMAP.md`,
  `CHANGELOG.md`, `LICENSE`, `AGENTS.md`, `.gitignore`, `appinfo/info.xml`, das lokale
  Architekturdokument und das lokale manuelle Abnahmeformular;
- reguläre lokale Kopien von `.agents/skills/work-in-nextcloud-app/SKILL.md` sowie
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
- Einheitliche App-Dokument- und Steuerungsstruktur:
  `docs/app-repository-structure.md`
- Einzige aktive systemweite Aufgabenquelle: `docs/zukunftsplan.md`
- Wiederkehrende Workflows: `.agents/skills/`
- Vollständige technische Repository-Liste: `config/workspace-repositories.tsv`
- Projektbezogene Sandbox- und Subagent-Grenzen: `.codex/config.toml`
- Strukturprüfung aller Repository-Roots und lokalen Skill-Ketten: `scripts/check-workspace-structure`
- Schneller Parent-Check: `scripts/check-fast`
- Schnelle Tests aller registrierten App-Repositories: `scripts/check-apps`
- Vollständiger Workspace-Check aus Parent plus allen Apps: `scripts/check-full`
- Echtes sauberes Filzmann-Full-Suite-Delivery-Gate: `scripts/check-flz-full-suite-delivery`
- Automatisches RC-Deployment auf Teamcloud: `docs/staging-deployment.md`

## Lokaler Markdown- und Abnahme-Viewer

Der Parent stellt einen dependency-freien lokalen Viewer für alle
Markdown-Dateien unterhalb der jeweiligen `docs`-Verzeichnisse im Parent und
in den über `config/workspace-repositories.tsv` registrierten Repositories
bereit:

```bash
scripts/manual-acceptance-viewer
```

Anschließend wird die ausgegebene Adresse (standardmäßig
`http://127.0.0.1:8765/`) im Browser geöffnet. Ein abweichender lokaler Port
kann mit `--port PORT` gewählt werden. Normale Markdown-Dokumente sind
schreibgeschützt. Dateien mit dem Namen
`<repo>/docs/manual-acceptance.md` werden als Formular gerendert; „Speichern“
schreibt erkannte Checkboxen und Eingabefelder atomar in genau diese Datei
zurück. „Drucken / PDF“ verwendet eine reduzierte Druckansicht.

Der Dienst bindet ausschließlich an `127.0.0.1`, akzeptiert nur lokale Host-
und Origin-Werte und schützt die API pro Start mit einem zufälligen Token.
Symlinks, Dateien außerhalb registrierter `docs`-Bäume, unbekannte Dokumente
und veraltete Speicherstände werden abgewiesen. Ändert sich die Datei nach dem
Laden, muss sie vor dem nächsten Speichern neu geladen werden.

Der Formular- und Dateivertrag einschließlich Reset- und Commit-Lebenszyklus
steht in `docs/app-repository-structure.md`. Der fokussierte Test läuft mit:

```bash
python3 tests/test_manual_acceptance_viewer.py
```

Die lokale HTTP-Grenze wird in Umgebungen, die Loopback-Sockets erlauben,
zusätzlich mit `RUN_LOCAL_SOCKET_TESTS=1` aktiviert. `scripts/check-fast`
enthält den socketfreien Contract-Test bereits.

`check-full` ist bewusst kein Release-Urteil und baut keine Delivery-Artefakte. Das Delivery-Gate lehnt standardmäßig jedes schmutzige enthaltene Repository ab und führt den strikten Parent-Fast-Pfad genau einmal aus; ein zusätzlicher vorgelagerter `check-fast` im selben Releasepfad ist unnötig. Nur `scripts/check-flz-full-suite-delivery --diagnostic` akzeptiert einen schmutzigen Stand zur Fehlersuche und endet ausdrücklich mit `DIAGNOSE ABGESCHLOSSEN – KEIN RELEASE-URTEIL`.

Ein Releasebau löscht keine älteren Release Candidates. Eine Bereinigung ist
ein eigener Auftrag nach erfolgreichem Neubau. Zuerst wird ausschließlich die
Vorschau geprüft:

```bash
scripts/prune-flz-full-suite-release-candidates.sh \
  --dist-root <DIST-ROOT> \
  --keep-label nc<major>-rcN
```

Erst die Wiederholung desselben Aufrufs mit `--execute` entfernt die exakt
ausgegebenen, validierten lokalen Artefakte genau dieser Nextcloud-Majorserie.
Der benannte RC, Kandidaten anderer Majorserien und finale Releases bleiben
erhalten. Gelöschte Artefakte sind nur aus einer anderen Kopie oder durch
einen reproduzierbaren Neubau der exakten Quellcommits wiederherstellbar.

DDEV-, HTTP- oder Rechtematrix-Smokes laufen nicht automatisch. Sie bleiben über die in `scripts/verify-flz-full-suite-delivery.sh` dokumentierten `RUN_*`-Variablen bewusst opt-in.

### Auswahl der Verifikation

Wähle den kleinsten bestehenden Prüfeinstieg, der die zusätzliche Fehlerklasse
nachweist. `scripts/check-workspace-structure` prüft Strukturarbeit,
`scripts/check-fast` Parent-Änderungen und `scripts/check-full` den gesamten
Workspace. App- und Delivery-Arbeit folgen den jeweiligen lokalen Skills und
Gates. Ein Diagnosemodus ist kein Release-Urteil.

DDEV-, HTTP- und Rollen-Smokes werden nur bei ausdrücklich beauftragter
Laufzeitprüfung ausgeführt. Der Umfang und die Beweisgrenze jedes aktuellen
Checks stehen in dessen Ausgabe und im zuständigen Skill; historische
Auditberichte sind keine Betriebsanleitung.

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
