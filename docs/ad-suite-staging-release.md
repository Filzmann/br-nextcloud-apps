# AD-Suite auf einem Staging-Server installieren

Diese Anleitung gilt für den ersten internen Releasekandidaten der AD-Suite auf Nextcloud 34 mit PHP ab 8.3. Die bisher verifizierte Referenzumgebung verwendet MySQL/MariaDB.

## Enthaltene Apps und Reihenfolge

1. `localbase`
2. `orgsuite`
3. `adcalendar`
4. `adplaner`
5. `adurlaub`
6. `adroom`

`localbase` muss vor allen anderen Apps aktiviert sein. Die vier Fachapps benötigen zusätzlich `orgsuite`.

## Server vorab prüfen

Alle `occ`-Befehle werden im Nextcloud-Root als HTTP-Benutzer ausgeführt, unter Debian/Ubuntu üblicherweise `www-data`.

```bash
cd /var/www/nextcloud
sudo -u www-data php occ status
sudo -u www-data php occ config:system:get dbtype
sudo -u www-data php occ integrity:check-core
php -v
```

Webserver und CLI müssen dieselbe unterstützte PHP-Hauptversion verwenden. Das Verzeichnis `custom_apps/` muss existieren und für den vorgesehenen Deploymentprozess beschreibbar sein.

## Backup und Rückbau

Vor einer Installation auf einer bereits genutzten Instanz mindestens sichern:

- Nextcloud-Datenbank,
- `config/`,
- `data/`,
- vorhandenes `custom_apps/`,
- gegebenenfalls das Theme.

Nach ausgeführten App-Migrationen ist ein Downgrade durch bloßes Zurückkopieren alten App-Codes nicht sicher. Der Rückbau erfolgt durch Wiederherstellung des zusammengehörigen Datenbank-, Konfigurations-, Daten- und App-Backups.

## Upload prüfen

Das Suite-Bundle und die danebenliegende Prüfsumme gemeinsam übertragen:

```bash
sha256sum --check ad-suite-nc34-rc1.tar.gz.sha256
tar -xzf ad-suite-nc34-rc1.tar.gz
cd ad-suite-nc34-rc1
sha256sum --check SHA256SUMS
```

`manifest.tsv` dokumentiert pro App Version, Git-Commit, SHA-256 und Signaturstatus.

## Apps entpacken

```bash
sudo tar -xzf localbase-*.tar.gz -C /var/www/nextcloud/custom_apps/
sudo tar -xzf orgsuite-*.tar.gz -C /var/www/nextcloud/custom_apps/
sudo tar -xzf adcalendar-*.tar.gz -C /var/www/nextcloud/custom_apps/
sudo tar -xzf adplaner-*.tar.gz -C /var/www/nextcloud/custom_apps/
sudo tar -xzf adurlaub-*.tar.gz -C /var/www/nextcloud/custom_apps/
sudo tar -xzf adroom-*.tar.gz -C /var/www/nextcloud/custom_apps/
sudo chown -R www-data:www-data /var/www/nextcloud/custom_apps/{localbase,orgsuite,adcalendar,adplaner,adurlaub,adroom}
```

Jedes Archiv enthält genau den zur App-ID passenden Wurzelordner. Keine Ordner umbenennen.

## Apps aktivieren

Auf einem leeren Staging-System können die Apps direkt in Abhängigkeitsreihenfolge aktiviert werden. Auf einer bereits benutzten Instanz empfiehlt sich für den Installationszeitraum der Wartungsmodus.

```bash
cd /var/www/nextcloud
sudo -u www-data php occ app:enable localbase
sudo -u www-data php occ app:enable orgsuite
sudo -u www-data php occ app:enable adcalendar
sudo -u www-data php occ app:enable adplaner
sudo -u www-data php occ app:enable adurlaub
sudo -u www-data php occ app:enable adroom
sudo -u www-data php occ status
sudo -u www-data php occ app:list --enabled
```

`--force` darf nicht verwendet werden. Beim Aktivieren führt Nextcloud die noch ausstehenden App-Migrationen aus. Meldet `occ status` danach `needsDbUpgrade: true`, wird im Wartungsfenster `sudo -u www-data php occ upgrade` ausgeführt.

## Signaturen

Interne, unsignierte RC-Archive sind auf einem privaten Staging-Server installierbar. `occ integrity:check-app <app-id>` meldet dann, dass keine Signatur vorhanden ist und überspringt die Dateiintegritätsprüfung.

Sobald offizielle app-spezifische Zertifikate vorliegen, kann der Release-Builder mit `SIGNING_KEY_DIR` und `NEXTCLOUD_ROOT` signierte Archive erzeugen. Private Schlüssel dürfen niemals im Workspace, Releasearchiv oder auf dem Webserver abgelegt werden.

## Organisationskonfiguration

Nach der Aktivierung im Nextcloud-Adminbereich der OrgSuite prüfen:

- Rollen- und Gruppen-IDs,
- Bereiche Nordost, West und Süd,
- Leitungshierarchie,
- Kalender-Peerrechte,
- Urlaubs-Peerrechte,
- Raumstammdaten.

Anschließend ausschließlich synthetische Staging-Konten den benötigten Nextcloud-Gruppen zuordnen. Demo-Seed-Befehle werden nicht automatisch ausgeführt und sollen auf einem realitätsnahen Staging-System nur nach bewusster Entscheidung verwendet werden.

## Abnahmekriterien

- Suite-Menü bleibt in allen Fachapps sichtbar.
- Normale Konten sehen nur eigene, gemeinsame oder unterstellte Sichten.
- Direkte API-Aufrufe auf verbotene Ziele werden serverseitig abgewiesen.
- Schreibzugriffe ohne CSRF-Token ergeben HTTP 412.
- Kalender, Standarddienste, Urlaub und Meetinglücken greifen korrekt ineinander.
- Raumüberschneidungen werden mit HTTP 409 verhindert.
- Vertikales App-Scrolling und horizontaler Tabellenoverflow funktionieren.
- Nextcloud-Log enthält nach den Abnahmeläufen keine neuen Appfehler.

Erst nach erfolgreicher Abnahme werden reale Personaldaten oder produktionsnahe Importe in Betracht gezogen.
