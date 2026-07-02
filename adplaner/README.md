# adplaner 0.1.5

Nextcloud-App-Prototyp fuer Dienstplaene und Urlaubsplanung in Assistenzteams.

## Status

Development-Prototyp. Nicht produktiv und nicht rechtssicher.

Enthalten:

- Assistenznehmer aus Nextcloud-Gruppen `ad-ASN-<Kuerzel>`, zum Beispiel `ad-ASN-ThoJa`, `ad-ASN-HaMü` oder `ad-ASN-RaKeLi`.
- EB-Recht fuer Nutzer*innen, die zugleich im Team und in einer Gruppe `ad-EB-*` sind.
- Monatlicher Wunschplan mit variabler Schichtliste; Standard ist 08-14, 14-20 und 20-08, Luecken und Ueberlappungen sind moeglich.
- Zuweisungen je Schicht: eigene Eintraege durch Assistenz, fremde Zuweisungen nur durch EB; EB-Konten selbst sind nicht schichtfaehig.
- Jahres-Urlaubsplan mit allen Tagen als Spalten und Assistenzkraeften als Zeilen.
- Urlaubswuensche als globale Eintraege pro Assistenz, sichtbar in allen Teams der Person.
- Optionale Urlaubssichtbarkeit ueber `ad-ASN-<Kuerzel>-Urlaub`; ohne diese Gruppe wird die Assistenznehmer-Gruppe selbst verwendet.
- Statuswechsel `planned`/`approved` nur durch EB.

Noch offen:

- Produktive Rechte- und Datenschutzpruefung.
- Feingranulare Urlaubsteilung, wenn nur ein Tag innerhalb eines Bereichs geaendert wird.
- Export, Benachrichtigungen und Dienstplan-Festschreibung.

## Installation

```bash
cd /var/www/vhosts/betriebsrat-ad.de/cloud.betriebsrat-ad.de/apps
cp -R /pfad/zu/adplaner .
cd /var/www/vhosts/betriebsrat-ad.de/cloud.betriebsrat-ad.de
sudo -u betriebsrat php occ app:enable adplaner
sudo -u betriebsrat php occ migrations:migrate adplaner
```

Dann in Nextcloud oeffnen:

```text
/apps/adplaner
```
