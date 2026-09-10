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
|       |-- docker-compose.filzmann_permission_matrix.yaml
|       |-- docker-compose.adcalendar.yaml
|       |-- docker-compose.adurlaub.yaml
|       |-- docker-compose.orgsuite.yaml
|       |-- docker-compose.adroom.yaml
|       |-- docker-compose.adrecruitment.yaml
|       |-- docker-compose.adbqplanung.yaml
|       `-- docker-compose.filzmann_data_protection.yaml
|-- brtop/      # eigenes Git-Repo, im Parent ignoriert
|-- adplaner/   # eigenes Git-Repo, im Parent ignoriert
|-- brstunden/  # eigenes Git-Repo, im Parent ignoriert
|-- localbase/  # eigenes Git-Repo, gemeinsame lokale Basisbausteine
|-- filzmann_permission_matrix/ # eigenes Git-Repo, Berechtigungsmatrix
|-- adcalendar/ # eigenes Git-Repo, Dienst- und Terminplanung
|-- adurlaub/   # eigenes Git-Repo, Urlaubsplanung
|-- orgsuite/   # eigenes Git-Repo, gemeinsame AD-/BR-Navigation
|-- adroom/     # eigenes Git-Repo, Raumplanung
|-- adrecruitment/ # eigenes Git-Repo, Bewerbungs- und Recruitingprozesse
|-- adbqplanung/ # eigenes Git-Repo, Basisqualifizierungsplanung
|-- filzmann_data_protection/ # eigenes Git-Repo, Datenschutz-Center
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
| Berechtigungsmatrix | `filzmann_permission_matrix` | `filzmann_permission_matrix/` | `https://nextcloud-dev.ddev.site/apps/filzmann_permission_matrix/` |
| AD Kalender | `adcalendar` | `adcalendar/` | `https://nextcloud-dev.ddev.site/apps/adcalendar/` |
| AD Urlaub | `adurlaub` | `adurlaub/` | `https://nextcloud-dev.ddev.site/apps/adurlaub/` |
| AD-/BR-Suite | `orgsuite` | `orgsuite/` | `https://nextcloud-dev.ddev.site/apps/orgsuite/ad` und `/br` |
| AD Raumplaner | `adroom` | `adroom/` | `https://nextcloud-dev.ddev.site/apps/adroom/` |
| AD Recruitment | `adrecruitment` | `adrecruitment/` | `https://nextcloud-dev.ddev.site/apps/adrecruitment/` |
| AD BQ-Planer | `adbqplanung` | `adbqplanung/` | `https://nextcloud-dev.ddev.site/apps/adbqplanung/` |
| Datenschutz-Center | `filzmann_data_protection` | `filzmann_data_protection/` | `https://nextcloud-dev.ddev.site/apps/filzmann_data_protection/` |

Die öffentliche Produktübersicht und Release-Unterlagen liegen im getrennten Repository `ad-suite/`; es enthält keinen deploybaren App-Code und keinen Nextcloud-Mount.

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
- `brtop/`, `adplaner/`, `brstunden/`, `localbase/`, `filzmann_permission_matrix/`, `adcalendar/`, `adurlaub/`, `orgsuite/`, `adroom/`, `adrecruitment/`, `adbqplanung/`, `filzmann_data_protection/` und `ad-suite/` sind eigene Git-Repositories.
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
ddev exec -d /var/www/html/html php occ app:list | grep -i filzmann_permission_matrix
ddev exec -d /var/www/html/html php occ app:list | grep -i adcalendar
ddev exec -d /var/www/html/html php occ app:list | grep -i adurlaub
ddev exec -d /var/www/html/html php occ app:list | grep -i orgsuite
ddev exec -d /var/www/html/html php occ app:list | grep -i adroom
ddev exec -d /var/www/html/html php occ app:list | grep -i adrecruitment
ddev exec -d /var/www/html/html php occ app:list | grep -i adbqplanung
ddev exec -d /var/www/html/html php occ app:list | grep -i filzmann_data_protection
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
${WORKSPACE_ROOT}/filzmann_permission_matrix
-> /var/www/html/html/custom_apps/filzmann_permission_matrix
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.filzmann_permission_matrix.yaml
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

AD BQ-Planer:

```text
${WORKSPACE_ROOT}/adbqplanung
-> /var/www/html/html/custom_apps/adbqplanung
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.adbqplanung.yaml
```

Datenschutz-Center:

```text
${WORKSPACE_ROOT}/filzmann_data_protection
-> /var/www/html/html/custom_apps/filzmann_data_protection
```

Konfiguration:

```text
nextcloud-dev/.ddev/docker-compose.filzmann_data_protection.yaml
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
- Echtes sauberes AD-Suite-Delivery-Gate: `scripts/check-ad-suite-delivery`
- Automatisches RC-Deployment auf Teamcloud: `docs/staging-deployment.md`

`check-full` ist bewusst kein Release-Urteil und baut keine Delivery-Artefakte. Das Delivery-Gate lehnt standardmäßig jedes schmutzige enthaltene Repository ab und führt den strikten Parent-Fast-Pfad genau einmal aus; ein zusätzlicher vorgelagerter `check-fast` im selben Releasepfad ist unnötig. Nur `scripts/check-ad-suite-delivery --diagnostic` akzeptiert einen schmutzigen Stand zur Fehlersuche und endet ausdrücklich mit `DIAGNOSE ABGESCHLOSSEN – KEIN RELEASE-URTEIL`.

Ein Releasebau löscht keine älteren Release Candidates. Eine Bereinigung ist
ein eigener Auftrag nach erfolgreichem Neubau. Zuerst wird ausschließlich die
Vorschau geprüft:

```bash
scripts/prune-ad-suite-release-candidates.sh \
  --dist-root <DIST-ROOT> \
  --keep-label nc<major>-rcN
```

Erst die Wiederholung desselben Aufrufs mit `--execute` entfernt die exakt
ausgegebenen, validierten lokalen Artefakte genau dieser Nextcloud-Majorserie.
Der benannte RC, Kandidaten anderer Majorserien und finale Releases bleiben
erhalten. Gelöschte Artefakte sind nur aus einer anderen Kopie oder durch
einen reproduzierbaren Neubau der exakten Quellcommits wiederherstellbar.

DDEV-, HTTP- oder Rechtematrix-Smokes laufen nicht automatisch. Sie bleiben über die in `scripts/verify-ad-suite-delivery.sh` dokumentierten `RUN_*`-Variablen bewusst opt-in.

### Zuständigkeit und Kosten der Prüfungen

Der ergänzende [systemische Harness-Audit vom 9./10. September 2026](harness-audit-2026-09-09.md)
klassifiziert Regeln und Testgruppen samt Beweisgrenzen. Er dokumentiert die
entfernte zweite Instruktionsprüfung in `scripts/check-apps`, den gestärkten
Majorfolgen-Test sowie verbleibende Schema-/Runtime- und Fresh-Install-Lücken.
Er ist ein datierter Befundbericht, keine zusätzliche Regel- oder Aufgabenquelle.

Bestandsaudit vom 5. September 2026. Die Kosten sind relative Einordnungen,
keine gemessenen Laufzeitversprechen. Für den Abschluss wird ein passender
umfassender Einstieg gewählt; dessen Teilprüfungen werden nicht unmittelbar
vorher auf demselben unveränderten Stand nochmals gestartet.

| Primärer Nachweis | Zweck und Umfang | Auslöser | Kosten / Abgrenzung |
| --- | --- | --- | --- |
| `scripts/check-workspace-structure` | Manifest, lokale Regeln/Skills, Tracking, TOML/YAML, Markdown-Struktur | Strukturarbeit; enthalten in Fast und App-Einstieg | niedrig bis mittel; Git-Abfragen je Repository, keine Container |
| `scripts/check-fast` | Parent-Shellsyntax, Diff/Artefakte, Struktur, Dokumentreferenzen, Governance-, Architektur-, CI-, Coverage-Baseline-, Installer- und Archivverträge | Parent-Arbeit | mittel; synthetische Datei-/Archiv-/ACL-Tests, keine realen App-Installationen |
| app-lokale PHP-/JS-Testläufer | Syntax und Fach-/Unit-/Contract-Smokes; PHP-Prozesse isoliert | App-Arbeit und App-CI | mittel; Syntax ist bereits enthalten, keine vorgelagerten identischen Linterläufe nötig |
| `scripts/check-full` | Fast plus alle registrierten App-Testläufer und reale öffentliche Providerklassen in den zentralen Contracts | vollständige Workspace-Verifikation | höher; übernimmt Struktur aus Fast; kein Paketbau, keine Runtime-Freigabe |
| App-CI | PHP 8.3 mit Coverage, PHP 8.5 ohne Coverage; JS mit Coverage | PR und Push auf main | höher; verschiedene Runtimes sind eigenständige Nachweise; Coverage ersetzt dort bereits einen separaten identischen Testlauf |
| LocalBase-Consumer-CI | Consumer-Suiten gegen den geprüften LocalBase-Stand | LocalBase-Änderung | hoch; anderer Providerinput als in unabhängigen App-CIs, daher keine nachgewiesene Doppelung |
| `scripts/measure-ad-suite-php-coverage.sh` und `scripts/measure-ad-suite-js-coverage.sh` | Coverage aller registrierten Apps gegen zentrale Baselines | gezielte Coverage-Abnahme | hoch; führen Suiten aus; PHP benötigt DDEV/Xdebug, JS c8; nicht zusätzlich ohne eigenen Beweisbedarf starten |
| `scripts/check-ad-suite-delivery` | striktes Fast, enthaltene Produkte, App-Tests, einmaliger temporärer Paketbau und Archivprüfung | konkrete Suite-Delivery | hoch; baut intern mit bereits geprüften Tests; sauberer Commitstand erforderlich |
| optionale Delivery-Smokes | DDEV-Status/Inventar, HTTP/CSRF, Rollenmatrix oder echte Integrationsfälle | ausdrücklich beauftragte lokale Laufzeitprüfung | hoch; verschiedene Fehlerklassen, keine vollständige Neuinstallation |
| `scripts/verify-nextcloud-compatibility` mit `scripts/run-nextcloud-ddev-compatibility-stage` | gepinnte Plattformmatrix, Fresh Install, DI/Jobs, app-lokale Rechte-Smokes, Runtime, Assets/UI und anwendbare Upgrades gemäß `verify-nextcloud-future-compatibility` | Supportbereich / RC-Veröffentlichung | sehr hoch; prüft exakte Commits und verwaltet die reservierte lokale DDEV-Registrierung mit Wiederherstellung |
| `scripts/check-privacy-app-compatibility` | physisch fehlende und inkompatible optionale Privacy-App | Änderung dieser Runtimegrenze | hoch; Mountwechsel benötigen Neustarts; kein historischer API-Adapter und kein Fresh-Install-Ersatz |

Die Dokumentreferenz-Fixtures prüfen den Prüfer einschließlich seiner
Fehlerfälle; der anschließende Scan prüft die echten Dokumente. Ebenso
ergänzen Installer-Fakes, echte Runtime-Smokes und Archivprüfungen einander.
App-lokale Provider-Fakes und die zentralen Tests gegen reale öffentliche
Providerklassen sichern verschiedene Grenzen. Diese Prüfungen bleiben erhalten.

Belegte Doppelarbeit wurde im optionalen Delivery-DDEV-Statuspfad entfernt:
`occ app:list` wird einmal statt je enthaltene App ausgeführt; alle erwarteten
Apps werden gegen diesen unmittelbar gelesenen Stand geprüft. Im derzeitigen
Full-Suite-Katalog entfallen dadurch sechs von sieben Inventaraufrufen,
einschließlich DDEV-Aufruf und Nextcloud-Bootstrap. Das ist kein Cache über
verschiedene Läufe oder Zustandsänderungen hinweg.

Die App-CIs installieren gesperrtes Coverage-Tooling in getrennten Jobs.
Ein gemeinsamer Cache könnte Downloads sparen, ist aber ohne Änderung dieser
eigenständigen Repositories nicht umgesetzt. Lock-Dateien und Runtime müssen
Teil eines künftigen Cache-Schlüssels sein. Alte lokale Coverage-Ausgaben mit
`REUSE_COVERAGE=1` besitzen keinen automatischen Quellstandnachweis und sind
kein ungeprüft wiederverwendbares Freigabeergebnis.

Ein konfigurierter `core.hooksPath`, getrackter Precommit-/Preflight-Wrapper
oder gesonderter allgemeiner PHPStan-/ESLint-Lauf wurde im registrierten
Bestand nicht gefunden. Die eingebauten Syntaxprüfungen sind keine vollständige
statische Analyse gegen einen gepinnten OCP-Stand.

### Fresh Install und externe Testkonten

Der Skill `verify-nextcloud-future-compatibility` bleibt der zuständige
Workflow für isolierte Neuinstallationen je gepinntem Plattformstand. Der
getrackte Einstieg `scripts/verify-nextcloud-compatibility` prüft Archive und
Commit-Identitäten, erzwingt lückenlose Majors und ruft für Fresh Install und
benachbarte Upgrades `scripts/run-nextcloud-ddev-compatibility-stage` auf.
Der Driver prüft die exakten App-Snapshots im PHP-/Node-Container vor der
Core-Installation, installiert anschließend einen leeren Core und aktiviert
erst danach Infrastruktur und Fachapps. App-lokale Dateien namens
`nextcloud-compatibility-smoke.php` definieren UI- und
Berechtigungsgrenzen, damit der Parent keine fachlichen Seiten- oder
Rechteannahmen dupliziert.

Wenn der lokal auflösbare Name `nextcloud-dev` benötigt wird, übernimmt
`--reserved-ddev-root nextcloud-dev` dessen Registrierung einmal für die
gesamte Matrix. Der Runner erfasst den vorherigen Laufzustand, verwendet für
die isolierten Stufen SQLite ohne gemeinsamen Datenbankcontainer und stellt
Registrierung sowie ursprünglichen Laufzustand auch nach einem Fehler wieder
her. Frühere appweise Nachweise im Zukunftsplan sind keine neue Ausführung und
beweisen nicht die Neuinstallierbarkeit aller aktuellen Apps.
Insbesondere sind `RUN_DDEV_CHECKS=1`, Installer-Fakes und der isolierte
Urlaubsschema-Test kein vollständiger Nextcloud-/Workspace-Reinstall.
Nach ausdrücklicher Freigabe wurde im bestehenden Workflow am 5. September
2026 ein lokaler Einzelnachweis ausgeführt: Nextcloud 34.0.2, PHP 8.3.21,
separate temporäre Codekopie ohne Bestandskonfiguration oder Nutzerdaten,
leere SQLite-Datenbank. Alle zwölf aktuellen App-Kopien wurden bei Fresh
Install und erneutem Aufbau aktiviert; die beiden vollständigen Schemata
sind identisch. Alle App-Migrationen sind angewendet, alle sechs deklarierten
Jobs registriert und über DI auflösbar. Die korrekte Aktivierungsreihenfolge
beginnt mit Infrastruktur; ein vorläufiger Lauf mit Fachapps zuerst meldete
vorübergehend fehlende LocalBase-Kommandodienste.

Fünf vorhandene Integrations-Smokes bestanden in der neuen Kopie:
Monatsplanstatus, Terminserien, Standarddienst-/Urlaubsintegration,
DAV-Abgleich und Recruitment-Durchstich. Der Urlaubsschema-Smoke scheiterte
unter SQLite an den global eindeutigen Namen seiner parallelen Testindizes;
derselbe bestehende Test bestand anschließend in DDEV/MariaDB mit seinen
selbstbereinigenden synthetischen Tabellen. Das ist eine Grenze des
Testaufbaus, kein fehlgeschlagener Fresh Install der Urlaubs-App.

Quellhashes, Schemavergleich, Aufrufe und Logs liegen als lokale, ignorierte
Prüfartefakte unter `build/harness-audit-2026-09-05/`. Config-Dateien und
Datenbanken werden dort nicht übernommen. Dieser lokale NC-34-/SQLite-
Nachweis ist keine gepinnte Mehrversionsmatrix und keine vollständige
Installationsabnahme mit authentifizierter Oberfläche und HTTPS-Assets.
Dieser weitergehende Gesamtnachweis bleibt **nicht vollständig verifiziert**.

Die lokalen Demo-Packs verwenden bereits die gemeinsame
`localbase/lib/Service/DemoAccountProvisioningService.php` und native
Nextcloud-Benutzer/Gruppen. Die Organisationsdefinition und Rollenquellen
sind explizit. Dies reproduziert synthetische lokale Fälle, belegt aber keine
Wiederherstellung realer externer Staging-Testkonten. Die Serverinstanz und
deren Konten wurden für diesen Audit nicht ausgelesen.

Vor einem destruktiven Staging-Reinstall werden nur die tatsächlich nötigen
externen Testidentitäten, Gruppenmitgliedschaften, app-eigenen Rollenwerte
und nicht reproduzierbaren Testdaten gezielt inventarisiert. Neutrale
Sollkonfiguration gehört bei Bedarf in vorhandene Setup-/Test-Fixtures;
reale Zuordnungen und notwendige Sicherungen bleiben geschützt außerhalb von
Git. Bestehende native Provisionierung wird verwendet; insbesondere wird die
lokale Passwortkonvention niemals auf Staging übernommen. Ein allgemeiner
Legacy-Pfad oder ein neues Benutzerverwaltungssystem folgt daraus nicht.

### Einordnung der Entwicklungsaltlasten

Der Audit vom 5. September 2026 umfasst den Parent und lesend die registrierten
Subrepositories. App-Code wurde nicht zur Bereinigung freigegeben. Die
folgenden konkreten Funde bleiben daher erhalten; eine Entfernung braucht den
zusammenhängenden app-lokalen beziehungsweise benannten Cross-App-Auftrag.

| Fund | Einordnung und Entscheidung |
| --- | --- |
| pauschale Upgrade-/Migrationspflichten in Root-Architektur und ADR 0001; verpflichtender eigener Deprecation-Releasezyklus | im Parent auf konkret zu erhaltende Zustände begrenzt; rein interne Entwicklungsstände folgen der zentralen Phasenregel |
| `localbase/lib/Organization/AdOrganizationDefinition.php`, interne Formate 1–3 → 4 | historischer Formatadapter; aktueller Zielvertrag und alle Consumer müssen beim Entfernen gemeinsam geprüft werden; vorhandene Organisationskonfiguration gezielt reproduzierbar machen |
| `brtop/lib/Service/BrtopSettingsService.php`, `brtop/lib/Service/BrGroupsService.php`, `localbase/lib/Organization/BrGroupSettingsService.php` | alte BRTop-Gruppenquelle; Initialisierungsmethode wird auch von BRStunden für den aktuellen Gruppenvertrag verwendet. Kein pauschales Löschen anhand des Namens `Legacy` |
| `adurlaub/tests/integration/MigrationSchemaSmoke.php` | Altschema-Übernahme alter Importfelder ist ein Entwicklungsaltlast-Kandidat; derselbe Test prüft aber auch das aktuelle leere Schema, Indizes und Integrität. Fresh-Anteil bleibt notwendig |
| `adrecruitment/lib/Service/HiringMasterDataService.php`, Statusmail-/Dokumentkommentar-Übergänge | frühere interne Formate; aktueller Mail-Klartext als Versandalternative und sicheres Escaping sind eigenständige Anforderungen, keine entbehrliche Legacy-Schicht |
| nummerierte Nextcloud-Migrationen und Jobregistrierung | erzeugen auch beim Fresh Install das aktuelle Schema/Jobs. Erst vollständigen Zielaufbau beweisen, dann rein historische Schritte appweise konsolidieren |
| Groupfolders-Source-Gate, DAV-Adapter und Negativtests alter Rollengruppen | Plattform-/Rechtegrenzen; bleiben einschließlich inkompatibler und verweigerter Fälle erhalten |
| LocalBase-Privacy-Pilot und Retention-Dry-Run | noch aktuell konsumierte öffentliche Verträge; ADR 0002 und Privacy-Rollout gelten, kein unkontrolliertes Entfernen während des Parent-Audits |

Der schnelle Parent-Check prüft eindeutig ausgeschriebene technische Pfade in
der aktuellen Betriebs-, Architektur- und Vertragsdokumentation gegen den
Workspace. Nicht mehr vorhandene exakte Pfade blockieren. Bloße technische
Basenames ohne Repositorykontext bleiben wegen möglicher Mehrdeutigkeit eine
Warnung. Sämtliche aktuellen Markdown-Dateien unter `docs/` werden geprüft;
abgelöste Planarchive werden nicht als zweite Dokumentwahrheit mitgeführt.

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
