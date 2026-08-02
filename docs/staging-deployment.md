# Automatisches Staging-Deployment

Jeder erfolgreiche Testlauf nach einem Push auf `main` installiert den
zugehörigen App-Commit als Release Candidate auf
`https://teamcloud.simonbeyer.de`. Pull Requests und fehlgeschlagene Tests
deployen nicht.

Der wiederverwendbare Workflow liegt im Parent-Repository unter
`.github/workflows/deploy-staging.yml`. Die zehn App-Repositories rufen ihn
nach ihren eigenen PHP-, JavaScript- und gegebenenfalls Consumer-Contract-
Tests auf. Paketierung, Prüfsummenprüfung, Installation und Rollbacklogik
bleiben dadurch zentral gepflegt.

## Sicherheits- und Betriebsvertrag

- SSH läuft ausschließlich als Hosting-/Domainbenutzer `filzmann`.
- Es wird weder ein Root-Passwort noch ein anderes Serverpasswort in GitHub
  gespeichert.
- Ein dedizierter Ed25519-Schlüssel ist nur für dieses Staging-Deployment zu
  verwenden. Der Eintrag in `authorized_keys` erhält mindestens die Option
  `restrict`.
- GitHub vertraut nur dem vorab auf dem Server verifizierten Ed25519-Host-Key;
  der Workflow verwendet kein dynamisches `ssh-keyscan`.
- Installiert werden Ein-Wurzel-Archive ohne `.git`, `.github`, Tests,
  Agentensteuerung, Abhängigkeit-Caches oder Symlinks.
- Ein root-eigener Wrapper erlaubt ausschließlich Archive aus dem fest
  vorgegebenen Incoming-Verzeichnis und delegiert ohne Passwort an den
  Plesk-Systembenutzer `simonbeyer_sys`. Der Installer serialisiert alle
  App-Deployments serverseitig, prüft
  SHA-256, App-ID, Nextcloud-Status und Aktivierung und führt anschließend
  `occ upgrade` aus.
- Vorhandener App-Code wird vor dem Austausch außerhalb des Webroots unter
  dem Statusverzeichnis des Benutzers gesichert. Fehler vor Beginn eines
  migrationsfähigen `occ`-Schritts stellen den alten Code automatisch wieder
  her. Nach Beginn von `app:enable` oder `occ upgrade` erfolgt wegen möglicher
  Datenbankänderungen kein automatischer Code-Rollback.
- Eine Schemaänderung benötigt weiterhin eine höhere App-Version in
  `appinfo/info.xml`; ein reiner Code-RC darf dieselbe App-Version erneut
  bereitstellen.

## Einmalige Servereinrichtung

Zuerst auf einem vertrauenswürdigen Administrationssystem ein eigenes
Schlüsselpaar erzeugen:

```bash
ssh-keygen -t ed25519 -C teamcloud-staging-github -f teamcloud-staging-github -N ''
```

Nur den öffentlichen Schlüssel auf dem Server in der tatsächlichen Home-
Directory des Benutzers `filzmann` hinterlegen. Die folgenden Platzhalter
werden vorher anhand von `getent passwd filzmann` und `id filzmann` ersetzt:

```bash
install -d -o filzmann -g <FILZMANN-GRUPPE> -m 700 <FILZMANN-HOME>/.ssh
```

In `<FILZMANN-HOME>/.ssh/authorized_keys` eine einzelne Zeile in dieser Form
ergänzen:

```text
restrict ssh-ed25519 <PUBLIC-KEY> teamcloud-staging-github
```

Anschließend Besitz und Modus minimal setzen:

```bash
chown filzmann:<FILZMANN-GRUPPE> <FILZMANN-HOME>/.ssh/authorized_keys
chmod 600 <FILZMANN-HOME>/.ssh/authorized_keys
```

Für Teamcloud wurden der reale Nextcloud-Root
`/var/www/vhosts/simonbeyer.de/teamcloud.simonbeyer.de`, der Plesk-Benutzer
`simonbeyer_sys` und `/opt/plesk/php/8.4/bin/php` ermittelt. Der
Plesk-Benutzer kann `occ` lesen und `custom_apps` schreiben. `filzmann`
erhält deshalb keine zusätzlichen Zugriffsrechte auf den vHost.

Die beiden geprüften Parent-Skripte werden zunächst als `filzmann` in ein
temporäres Bootstrap-Verzeichnis hochgeladen. Anschließend werden sie als
Root unveränderlich für den Deploymentbenutzer installiert:

```bash
install -d -o root -g root -m 755 /usr/local/libexec/teamcloud-staging
install -o root -g root -m 755 \
  /tmp/teamcloud-staging-bootstrap/install-staging-app.sh \
  /usr/local/libexec/teamcloud-staging/install-staging-app.sh
install -o root -g root -m 755 \
  /tmp/teamcloud-staging-bootstrap/teamcloud-staging-install \
  /usr/local/sbin/teamcloud-staging-install

install -d -o filzmann -g psacln -m 750 \
  /var/tmp/teamcloud-staging-incoming
install -d -o simonbeyer_sys -g psacln -m 750 \
  /var/lib/teamcloud-staging
```

Die Sudoers-Regel wird ausschließlich mit `visudo` angelegt:

```bash
visudo -f /etc/sudoers.d/teamcloud-staging
```

Ihr einziger Inhalt ist:

```text
filzmann ALL=(simonbeyer_sys) NOPASSWD: /usr/local/sbin/teamcloud-staging-install
```

Danach werden Modus, Syntax und der erlaubte Aufruf geprüft:

```bash
chmod 440 /etc/sudoers.d/teamcloud-staging
visudo -cf /etc/sudoers.d/teamcloud-staging
sudo -u filzmann sudo -n -u simonbeyer_sys \
  /usr/local/sbin/teamcloud-staging-install
```

Der letzte Befehl muss ohne Passwortabfrage mit der Usage-Meldung und Exit 2
enden. Ein Root-Aufruf von `occ`, `chmod 777`, ein schreibbarer Core-App-Pfad
oder zusätzliche Rechte von `filzmann` am vHost sind nicht zulässig.

Den zu GitHub passenden Host-Key direkt auf dem Server anzeigen und seinen
Fingerprint getrennt prüfen:

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

Der vollständige öffentliche Host-Key wird später als bekannte Hostzeile im
GitHub-Secret gespeichert:

```text
teamcloud.simonbeyer.de ssh-ed25519 <SERVER-HOST-KEY>
```

## GitHub-Environment `staging`

Im Parent-Repository `Filzmann/br-nextcloud-apps` wird ein Environment
`staging` angelegt. Es enthält genau diese beiden Environment-Secrets:

- `STAGING_SSH_PRIVATE_KEY`: privater Teil des dedizierten Deploy-Schlüssels;
- `STAGING_SSH_KNOWN_HOSTS`: verifizierte Host-Key-Zeile des Servers.

Das Parent-Repository ist öffentlich. Der zentrale Workflow und seine
Skripte werden deshalb ohne zusätzliches GitHub-Zugriffstoken gelesen.

Der private Schlüssel wird nach dem Übertragen in das GitHub-Environment vom
Administrationssystem sicher entfernt. Er wird niemals committed, in Logs
ausgegeben oder auf dem Stagingserver gespeichert.

## Aktivierung und erster Lauf

Der Parent-Workflow und seine Skripte müssen zuerst auf `main` verfügbar sein.
Danach werden die Caller-Änderungen in den App-Repositories auf `main`
übernommen. So verweist kein App-Workflow zwischenzeitlich auf einen noch
nicht vorhandenen wiederverwendbaren Workflow.

Der erste kontrollierte Lauf erfolgt mit genau einer App. Er muss folgende
Nachweise liefern:

1. grüne App-Tests vor dem Deploy-Job;
2. erfolgreiche SSH-Host-Key- und Key-Authentifizierung;
3. grüne Archiv-, Prüfsummen-, `occ status`-, Aktivierungs- und Upgradechecks;
4. HTTP 200 sowie passender Content-Type für mindestens ein CSS- und ein
   JavaScript-Asset;
5. angemeldeter manueller Sicht- und Funktionscheck der App;
6. vorhandener Backup- und Commitnachweis unter
   `<FILZMANN-HOME>/.local/state/br-nextcloud-staging`.

Backups werden zunächst bewusst nicht automatisch gelöscht. Eine spätere
Retention wird erst nach einem erfolgreich erprobten Rückbau als getrennte,
getestete Betriebsänderung eingeführt.
