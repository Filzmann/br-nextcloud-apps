# Candidate-gebundenes Staging-Deployment

## Autoritative Zieltrennung

Jeder neue Work Order, Candidate, aktive Deployment-Marker und jede
Acceptance-Evidence bindet genau eine Ziel-ID. `teamcloud` ist das
autoritative Staging-Ziel für `localbase`, `orgsuite` und alle `flz_*`-
beziehungsweise `flz...`-Apps. `br-staging` bezeichnet
`https://staging.betriebsrat-ad.de` und ist das autoritative Ziel für
`brstunden` und `brtop`. Eine Abnahme oder Integrationsfreigabe von einem
anderen Ziel wird auch bei identischem Commit und Build-Digest verworfen.

`localbase` und `orgsuite` dürfen für die Laufzeitabhängigkeiten der BR-Apps
zusätzlich als exakt gepinnter Mirror nach `br-staging` installiert werden.
Der Workflow verlangt dafür den vollständigen, autoritativ auf `teamcloud`
ausgestellten Candidate mit dessen unveränderlichem Source-Commit,
Build-ID und Archivdigest. Der Checkout-Ref muss exakt dieser vollständige
Commit sein; Branches, Tags und andere veränderliche Refs werden verworfen.
Nach dem reproduzierbaren Paketbau müssen tatsächlicher Commit und Digest
exakt mit dem Teamcloud-Candidate übereinstimmen.
Der zentrale Candidate-Workflow startet den BR-Mirror erst, nachdem dasselbe
Candidate-Artefakt erfolgreich auf dem autoritativen Teamcloud-Ziel
bereitgestellt wurde; manuell angegebene Branch- oder Tag-Refs sind kein
zulässiger Mirror-Ersatz.

Nur die lokale Workflow-Prüfung erhält diese Candidate-Bindung. Das
BR-Server-Gate wird im Modus `runtime-mirror` ausdrücklich ohne
Candidate-, Deployment- oder Build-ID aufgerufen. Dadurch entsteht dort
kein aktiver Acceptance-Marker und keine BR-Candidate-/Abnahme-Evidence.
Der einfache Deploymentdatensatz hält Commit und Digest für die
Betriebsdiagnose fest; Candidate- und Abnahmequelle bleibt ausschließlich
`teamcloud`.

Der GitHub-Workflow verwendet für `teamcloud` weiterhin das Environment
`staging`. Für `br-staging` wird das getrennte Environment `staging-br`
verwendet. Dort müssen dieselben beiden Secrets
`STAGING_SSH_PRIVATE_KEY` und `STAGING_SSH_KNOWN_HOSTS` sowie die validierten
Variablen `STAGING_SSH_USER` und `STAGING_GATE_COMMAND` eingerichtet werden.
Der BR-Server benötigt vor dem ersten Lauf ein eigenes Forced-Command-Gate,
das den generischen Installer mit `--staging-target br-staging` aufruft. Bis
dieses Gate und ein auf `--staging-target br-staging` begrenzter
Acceptance-Service installiert und separat geprüft sind, ist der
Quellvertrag vorbereitet, ein BR-Deployment aber nicht betriebsbereit
freigegeben. Der vorhandene Teamcloud-Acceptance-Service wird entsprechend
auf `--staging-target teamcloud` festgelegt und akzeptiert keine BR-Marker.

Ein Push auf `main` installiert keinen ungeprüften Integrationsstand. Für eine
zuvor zentral ausgestellte Work Order wird stattdessen ein Branch
`candidate/<Work-Order-ID>` gepusht. Die App-Tests laufen auf diesem Branch.
Nur ihr erfolgreicher `workflow_run` aus dem auf dem Default-Branch
versionierten Caller `.github/workflows/candidate.yml` darf den zentralen
Workflow `.github/workflows/candidate-staging.yml` aufrufen. Pull Requests,
fehlgeschlagene Tests und andere Branchnamen stellen keinen Candidate aus und
deployen nicht.

Der zentrale Workflow baut aus dem exakten geprüften Commit ein
reproduzierbares Archiv. Anschließend fordert er ein kurzlebiges
GitHub-OIDC-Token an, dessen Audience den vollständigen Intake-Request bindet.
Der Acceptance-Service prüft Signatur und Zeitfenster sowie Repository-Name,
unveränderliche Repository-ID, Caller-Workflow, zentralen wiederverwendbaren
Workflow und beide Run-IDs. Er synchronisiert zuerst den Evidence-Store,
verlangt die vorab ausgestellte passende Work Order und liest die app-lokale
Markdown-Abnahmevorlage mit `git show` aus dem exakten Commit. Der
Candidate wird erst nach zentral bestätigtem Check- und Candidate-Evidence-
Push an den Deployment-Workflow übergeben. App-Repositories benötigen dafür
kein Evidence-Schreibsecret.

Der Deployment-Workflow checkt denselben Commit aus, baut das Archiv erneut,
vergleicht dessen SHA-256 mit dem Candidate und übergibt Candidate-, Build-
und Deployment-ID an das vorhandene SSH-Gate. Der Installer hält je App genau
den aktuell aktiven Candidate-Stand zunächst als
`pending-https-verification` fest. Erst nachdem der Workflow die öffentlichen
CSS- und JavaScript-Assets über HTTPS mit dem richtigen Content-Type geprüft
hat, bindet ein eigener Gate-Aufruf denselben Candidate, Commit, Build und
dasselbe Deployment atomar als `verified`. Eine neue Branch-Version erzeugt
einen neuen Candidate; ein späteres Deployment ersetzt den aktiven Marker und
entzieht dem älteren Stand die Abnahmefähigkeit.

Beim ersten authentifizierten Abruf synchronisiert der Acceptance-Service
seinen privaten Evidence-Checkout, übernimmt ausschließlich einen vollständig
gebundenen `verified`-Marker und veröffentlicht daraus die unveränderliche
Deployment-Evidence zentral. Ein fehlender, noch ausstehender oder
widersprüchlicher Marker liefert keinen abnahmefähigen Candidate. GitHub
Actions erhält dafür keinen Schreibzugriff auf den Evidence-Store.

Nach dem Deployment übernimmt der Acceptance-Service die versionierte
Markdown-Prüfvorschrift des Candidates. Nur authentifizierte Konten mit der
Abnehmerrolle dürfen genau eine finale PASS- oder FAIL-Evidence erzeugen.
Abgeschlossene Evidence wird im privaten Repository
`Filzmann/filzmann-ai-evidence` dauerhaft abgelegt; Staging ist nicht die
einzige Kopie. Reverse Proxy, Servicekonto und Serverpfade werden aus der
tatsächlichen Zielumgebung konfiguriert und sind in diesem Quellstand nicht
betriebsbereit behauptet.

Der eigenständige Dienst läuft unter dem nicht interaktiven Benutzer
`filzmann_acceptance` und bindet ausschließlich an Loopback. Seine vom Harness
erzeugte systemd-Unit erhält Schreibzugriff nur auf den Evidence-Checkout und
Lesezugriff auf Proxy-Token sowie
`/var/lib/teamcloud-staging/active`. Installer und HTTPS-Verifier geben diesem
Benutzer per benannter POSIX-ACL ausschließlich Traverse auf das
Staging-Statusverzeichnis, Lesen/Traverse auf dessen `active`-Unterverzeichnis
und Lesen auf die jeweilige atomar ersetzte Markerdatei. Backups, Archive,
Nextcloud-Code und übrige Zustandsdateien bleiben unzugänglich.

Der Dienst scannt direkte `<app-id>.json`-Marker aller freigegebenen Apps.
Aufgelistet und finalisierbar sind nur Candidates, deren App, Candidate-ID,
Deployment-ID, Commit, Build und Digest mit ihrem aktuell `verified` Marker
übereinstimmen. Ein späteres Deployment entfernt oder ersetzt den Marker und
entzieht dem älteren Candidate sofort die Abnahmefähigkeit. Der Reverse Proxy
entfernt eingehende Identitäts- und Rollenheader und setzt sie erst nach
erfolgreicher Webauthentifizierung zusammen mit dem internen Proxy-Token neu.
Der einzelne Schreibendpunkt `/api/candidate-intake` liegt außerhalb dieser
Browser-Basic-Auth. Er akzeptiert weder Reviewer-Header noch Proxy-Token als
Berechtigung, sondern ausschließlich das für den Request ausgestellte und
vollständig geprüfte GitHub-OIDC-Bearer-Token. Der Proxy begrenzt dort
Requestgröße und Rate separat.

```text
automatische Prüfungen → READY → Candidate → Staging-Deployment
→ manuelle Staging-Abnahme → PASS-Evidence → filzmann-accept
→ getrennte Git-Integration
```

Die wiederverwendbaren Workflows liegen im Parent-Repository unter
`.github/workflows/candidate-staging.yml` und
`.github/workflows/deploy-staging.yml`. Die zwölf App-Repositories starten den
ersten nur über ihren erfolgreichen Candidate-`workflow_run`. Paketierung,
OIDC-Intake, Prüfsummenprüfung, Installation und Rollbacklogik bleiben dadurch
zentral gepflegt.

## Sicherheits- und Betriebsvertrag

Für den Lebenszyklus gilt die
[Entwicklungsphasenregel](architecture.md#entwicklungsphase-und-kompatibilitätsbedarf).
Dieses Deployment beschreibt die Entwicklungs-/Integrationsinstanz. Ein
Schema-Break ohne Upgradepfad wird als gezielter Reinstall ausgeführt und darf
nicht über den gewöhnlichen Codeaustausch unten als Upgrade ausgegeben werden.
Die Fehler- und Rückbaugrenzen des Installers bleiben auch auf STAGING nötig.

## Einmaliger Rebranding-Reinstall beim nächsten Staging-Lauf

Der nächste Staging-Lauf nach dem technischen Markenwechsel ist ausdrücklich
kein normales In-Place-Deployment. Es gibt keinen produktiven Datenbestand und
keinen freigegebenen Upgradepfad von den alten App-IDs. Der Lauf wird als
separat freizugebender, vollständiger Clean Install vorgemerkt; die in diesem
Änderungsauftrag vorgenommenen Repositoryänderungen führen ihn nicht aus.

### Candidate-Deployments während des Clean Installs anhalten

Vor dem ersten Candidate-Push der Rebranding-Stände erhält das GitHub-
Environment `staging` vorübergehend eine verpflichtende manuelle Freigabe.
Alternativ werden seine beiden Deployment-Secrets kontrolliert entzogen. Kein
wartender Candidate-Deployment-Job wird freigegeben, bevor der frische
Staging-Baseline-Stand vorbereitet und der Clean Install ausdrücklich
beauftragt wurde. Ein normaler Push auf `main` löst kein Deployment aus.

Zuvor werden die externen GitHub-Repositories auf ihre bereits in Metadaten
und CI verwendeten kanonischen Slugs umbenannt: `nextcloud-adcalendar` nach
`nextcloud-flzcalendar`, `nextcloud-adplaner` nach `nextcloud-flzplaner`,
`nextcloud-adurlaub` nach `nextcloud-flzurlaub`, `nextcloud-adroom` nach
`nextcloud-flzroom`, `nextcloud-recruitment` nach
`nextcloud-flzrecruitment`, `nextcloud-filzmann-permission-matrix` nach
`nextcloud-flz-permission-matrix` und `ad-suite` nach `flz-full-suite`.
Für `flzbqplanung` und `flz_data_protection`, deren lokale Repositories noch
keinen `origin` besitzen, werden die kanonischen Ziele
`nextcloud-flzbqplanung` und `nextcloud-flz-data-protection` angelegt oder
zugeordnet. Erst danach werden die lokalen Remotes ausdrücklich auf die neuen
Ziele gesetzt. Der Parent-Commit mit Workflow und SSH-Allowlisten wird vor den
App-Commits auf `main` bereitgestellt.

Vor dem Reset werden nur weiterhin benötigte externe Testkonten, native
Nextcloud-Gruppen und nicht reproduzierbare Testkonfigurationen datensparsam
gesichert oder über die vorhandenen Setup-Schritte reproduzierbar gemacht.
Fachliche Entwicklungsdaten werden nicht migriert. Danach werden die alten
Apps vollständig deaktiviert und entfernt:

- `adcalendar` → `flzcalendar`
- `adplaner` → `flzplaner`
- `adurlaub` → `flzurlaub`
- `adroom` → `flzroom`
- `adrecruitment` → `flzrecruitment`
- `adbqplanung` → `flzbqplanung`
- `filzmann_data_protection` → `flz_data_protection`
- `filzmann_permission_matrix` → `flz_permission_matrix`

Weil auch gemeinsame LocalBase-Verträge, AppConfig-Schlüssel und Schemaquellen
technisch umbenannt wurden, umfasst der Clean Install außerdem `localbase` und
`orgsuite`. Bevor neue Apps installiert werden, muss belegt sein, dass keine
alte App-ID, kein alter App-Code und kein altes App-Schema beziehungsweise
AppConfig-Fragment mehr als aktive Quelle verwendet wird. Der bevorzugte Weg
ist deshalb ein frischer Staging-Baseline-Stand von Nextcloud statt einer
unvollständigen Einzelbereinigung. Erst danach werden `localbase`,
`flz_data_protection`, `flz_permission_matrix`, die neuen FLZ-Fachapps
einschließlich des weiterhin nur für Staging vorgesehenen `flzbqplanung` und
– ab zwei aktiven FLZ-Produkten – `orgsuite` frisch installiert. Beide
Plattform-Apps und der BQ-Planer verwenden dabei denselben geprüften
Staging-Workflow wie die übrigen registrierten Apps; ihre Aufnahme in Staging
ändert weder Bundle- noch Release-Reife.

### Installationsreihenfolge des Clean Installs

Nach dem belegten Entfernen aller Altquellen werden die neuen Apps in dieser
Reihenfolge installiert und jeweils vollständig geprüft, bevor der nächste
Schritt freigegeben wird:

1. `localbase`
2. `flz_data_protection`
3. `flz_permission_matrix`
4. `flzcalendar`, `flzplaner`, `flzurlaub`, `flzroom` und `flzrecruitment`
5. `flzbqplanung` ausschließlich als nicht releasefähiges Staging-Produkt
6. `orgsuite`, sobald mindestens zwei FLZ-Fachprodukte aktiv sind

Die Reihenfolge ist ein Deploymentvertrag, keine neue Laufzeitabhängigkeit:
optionale Provider bleiben optional und fehlende Fachapps bleiben ein gültiger
Standalone-Zustand. Die temporäre GitHub-Freigabesperre wird erst nach
Appstatus-, Schema-, Asset-, Provider-/Consumer- und Sichtprüfung aufgehoben.

Der gesonderte Lauf prüft anschließend Appstatus und Installationsschema,
mindestens ein CSS- und JavaScript-Asset je installierter App über HTTPS, die
sichtbare Filzmann-Oberfläche sowie die notwendigen Rollen-, Provider- und
Consumer-Verträge. Die konkreten Hosting-/`occ`-Befehle werden erst im
freigegebenen Staging-Auftrag aus der tatsächlichen Umgebung abgeleitet; DDEV-
oder Entwicklungsannahmen werden nicht übernommen.

- SSH läuft ausschließlich als Hosting-/Domainbenutzer `filzmann`.
- Es wird weder ein Root-Passwort noch ein anderes Serverpasswort in GitHub
  gespeichert.
- Ein dedizierter Ed25519-Schlüssel ist nur für dieses Staging-Deployment zu
  verwenden. Der Eintrag in `authorized_keys` erzwingt zusätzlich zu
  `restrict` ausschließlich `/usr/local/bin/teamcloud-staging`; das Gate
  validiert den ursprünglichen Clientbefehl selbst.
- GitHub vertraut nur dem vorab auf dem Server verifizierten Ed25519-Host-Key;
  der Workflow verwendet kein dynamisches `ssh-keyscan`.
- Installiert werden Ein-Wurzel-Archive ohne `.git`, `.github`, Tests,
  Agentensteuerung, Abhängigkeit-Caches oder Symlinks.
- Das SSH-Gate akzeptiert ausschließlich `upload`, `install` und `cleanup`.
  Fertige Archive bleiben Eigentum von `filzmann`. Eine gezielte POSIX-ACL
  erteilt `simonbeyer_sys` nur Traverse auf Incoming- und Run-Verzeichnis sowie
  Leserecht auf das Archiv; Gruppen, andere Benutzer und der Installer erhalten
  kein Schreibrecht.
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
restrict,command="/usr/local/bin/teamcloud-staging" ssh-ed25519 <PUBLIC-KEY> teamcloud-staging-github
```

Ohne die feste `command=`-Option wäre `restrict` allein kein Forced-Command-
Vertrag und der Schlüssel dürfte nicht verwendet werden.

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

Vor Installation des Gates müssen `setfacl` und `getfacl` aus dem
POSIX-ACL-Paket verfügbar sein. Auf Debian-/Ubuntu-Systemen liefert sie das
Paket `acl`. Die Voraussetzung und der Zielbenutzer werden vor Gate-Nutzung
explizit geprüft:

```bash
command -v setfacl
command -v getfacl
id simonbeyer_sys
```

Das Gate prüft dieselben Voraussetzungen bei Upload, Install und Cleanup erneut
und bricht bei fehlenden Werkzeugen, unbekanntem Zielbenutzer oder abweichenden
ACLs fail-closed ab.

Die vier geprüften Parent-Dateien werden zunächst als `filzmann` in ein
temporäres Bootstrap-Verzeichnis hochgeladen. Anschließend werden sie als
Root unveränderlich für den Deploymentbenutzer installiert:

```bash
install -d -o root -g root -m 755 /usr/local/libexec/teamcloud-staging
install -o root -g root -m 755 \
  /tmp/teamcloud-staging-bootstrap/install-staging-app.sh \
  /usr/local/libexec/teamcloud-staging/install-staging-app.sh
install -o root -g root -m 755 \
  /tmp/teamcloud-staging-bootstrap/verify-staging-deployment-marker.php \
  /usr/local/libexec/teamcloud-staging/verify-staging-deployment-marker.php
install -o root -g root -m 755 \
  /tmp/teamcloud-staging-bootstrap/teamcloud-staging-install \
  /usr/local/sbin/teamcloud-staging-install
install -o root -g root -m 755 \
  /tmp/teamcloud-staging-bootstrap/teamcloud-staging \
  /usr/local/bin/teamcloud-staging

install -d -o filzmann -g <FILZMANN-GRUPPE> -m 700 \
  /var/tmp/teamcloud-staging-incoming
install -d -o simonbeyer_sys -g psacln -m 750 \
  /var/lib/teamcloud-staging

setfacl -b -k /var/tmp/teamcloud-staging-incoming
setfacl -m u:simonbeyer_sys:--x,g::---,m::--x,o::--- \
  /var/tmp/teamcloud-staging-incoming
getfacl -n /var/tmp/teamcloud-staging-incoming
```

Die Gruppen- und Other-Einträge müssen effektiv `---` bleiben. Die in `ls -l`
angezeigten Gruppenbits bilden bei erweiterten ACLs die ACL-Maske ab und sind
deshalb kein Ersatz für die Prüfung mit `getfacl`.
Das Gate repariert diesen administrativ gesetzten Zustand nicht. Upload,
Install und Cleanup lehnen einen abweichenden Owner oder ACL-Vertrag des
Incoming-Roots geschlossen ab; nur das neu erzeugte Run-Verzeichnis und das
fertige Archiv erhalten beim Upload ihre gezielten ACLs.

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

Vor dem ersten realen Staging-Gate-Lauf wird die tatsächliche ACL-Wirkung mit
einem synthetischen Upload geprüft. `<UNBETEILIGTER-BENUTZER>` wird durch ein
vorhandenes, weder an Upload noch Installation beteiligtes Konto ersetzt:

```bash
printf 'synthetischer ACL-Test\n' | \
  sudo -u filzmann /usr/local/bin/teamcloud-staging \
    upload 999999 1 flz_permission_matrix

probe=/var/tmp/teamcloud-staging-incoming/999999-1-flz_permission_matrix/flz_permission_matrix.tar.gz
test "$(stat -c %U -- "$probe")" = filzmann
getfacl -n "$probe"
sudo -u simonbeyer_sys test -r "$probe"
! sudo -u simonbeyer_sys test -w "$probe"
! sudo -u <UNBETEILIGTER-BENUTZER> test -r "$probe"
sudo -u filzmann /usr/local/bin/teamcloud-staging \
  cleanup 999999 1 flz_permission_matrix
```

Dieser Test installiert das synthetische Archiv nicht. Er belegt Eigentum,
Traverse-/Leserecht, fehlendes Schreibrecht, Fremdbenutzer-Ablehnung und den
weiterhin funktionierenden Cleanup getrennt vom Installationswrapper.

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
Danach werden die Caller-Änderungen auf dem tatsächlichen Default-Branch jedes
App-Repositories übernommen. Der Acceptance-Service bindet denselben
Caller-Ref und die unveränderliche GitHub-Repository-ID in seiner versionierten
Allowlist. So verweist kein App-Workflow zwischenzeitlich auf einen noch nicht
vorhandenen wiederverwendbaren Workflow und ein anderer Branch erhält keine
OIDC-Berechtigung.

Der erste kontrollierte Lauf erfolgt mit genau einer App. Er muss folgende
Nachweise liefern:

1. grüne App-Tests vor dem Deploy-Job;
2. erfolgreiche SSH-Host-Key- und Key-Authentifizierung;
3. grüne Archiv-, Prüfsummen-, `occ status`-, Aktivierungs- und Upgradechecks;
4. HTTP 200 sowie passender Content-Type für mindestens ein CSS- und ein
   JavaScript-Asset;
5. einen danach atomar auf `verified` gesetzten, exakt an Candidate,
   Deployment, Commit, Build-ID und Build-Digest gebundenen aktiven Marker;
6. angemeldeter manueller Sicht- und Funktionscheck der App;
7. vorhandener Backup- und Commitnachweis unter
   `<FILZMANN-HOME>/.local/state/br-nextcloud-staging`.

Backups werden zunächst bewusst nicht automatisch gelöscht. Eine spätere
Retention wird erst nach einem erfolgreich erprobten Rückbau als getrennte,
getestete Betriebsänderung eingeführt.
