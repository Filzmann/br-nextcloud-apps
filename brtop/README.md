# brtop 0.1.0

Nextcloud-App-Prototyp für BR-TOPs, Ladungen, Protokollvorlagen und Beschlussdokumente.

## Status

Development-Prototyp. Nicht produktiv und nicht rechtssicher.

Noch nicht enthalten:

- automatische Ersatzmitgliedberechnung,
- geprüfte Nachladungslogik,
- Import von Urlaubs-/Krankmeldungen,
- echte E-Mail-Versendung,
- PDF/DOCX-Export,
- Rollen- und Rechtekonzept.

## Installation

```bash
cd /var/www/vhosts/betriebsrat-ad.de/cloud.betriebsrat-ad.de/apps
unzip /pfad/zu/brtop-0.1.0.zip
cd /var/www/vhosts/betriebsrat-ad.de/cloud.betriebsrat-ad.de
sudo -u betriebsrat php occ app:enable brtop
sudo -u betriebsrat php occ migrations:migrate brtop
```

Falls deine Nextcloud nicht unter diesem Pfad liegt, den Pfad entsprechend anpassen.

## Test

```bash
cd /var/www/vhosts/betriebsrat-ad.de/cloud.betriebsrat-ad.de
sudo -u betriebsrat php occ app:list | grep brtop
sudo -u betriebsrat php occ migrations:status brtop
```

Dann in Nextcloud öffnen:

```text
/apps/brtop
```

1. Demo-Sitzung anlegen.
2. Dokumente erzeugen.
3. In den Dateien prüfen, ob unter `BR-Sitzungen/...` Markdown-Dateien erzeugt wurden.
