# Delivery-Gate der AD-Suite

Das Delivery-Gate bündelt die wiederholbaren Prüfungen vor jedem Releasekandidaten. Ein Release darf nur aus sauberen App-Repositories gebaut werden.

## Stufe 1: lokale Pflichtprüfung

```bash
cd ~/projects/br-nextcloud-apps
scripts/verify-ad-suite-delivery.sh
```

Geprüft werden:

- App-Metadaten und die festgelegte Nextcloud-34-/PHP-8.3-Kompatibilität,
- AGPL-Lizenz, README, Changelog und App-Anweisungen,
- fehlende lokale, ungültige oder WordPress-spezifische Produktionsverweise,
- Symlinks und Shell-Syntax,
- alle schnellen PHP- und JavaScript-Tests,
- reproduzierbarer Paketbau, Archivwurzeln und SHA-256-Prüfsummen.

## Stufe 2: Nextcloud-Container

```bash
RUN_DDEV_CHECKS=1 scripts/verify-ad-suite-delivery.sh
```

Diese Stufe ergänzt den Nextcloud-Status und prüft, ob alle sechs Apps aktiviert sind.

## PHP-Abdeckung

Die isolierten PHP-Tests können zusätzlich mit Xdebug/PHPCOV gemessen werden. Die Entwicklungsabhängigkeit wird aus dem versionierten Lockfile installiert und nicht in die App-Archive gepackt:

```bash
cd ~/projects/br-nextcloud-apps/nextcloud-dev
ddev xdebug on
cd ..
scripts/measure-ad-suite-php-coverage.sh
cd nextcloud-dev
ddev xdebug off
```

Der Bericht liegt unter `build/coverage/php-summary.tsv`. Das Skript erzwingt standardmäßig mindestens 40 Prozent Gesamt-Line-Coverage; ein bewusst höherer Grenzwert kann über `MIN_TOTAL_COVERAGE` gesetzt werden.

Baseline vom 14. Juli 2026 nach den Workflow-Härtungen:

| App | ausführbare Zeilen | abgedeckt | Line-Coverage |
| --- | ---: | ---: | ---: |
| LocalBase | 325 | 301 | 92,62 % |
| OrgSuite | 67 | 57 | 85,07 % |
| AD Kalender | 813 | 271 | 33,33 % |
| AD Planer | 764 | 338 | 44,24 % |
| AD Urlaub | 523 | 146 | 27,92 % |
| AD Raum | 412 | 104 | 25,24 % |
| Gesamt | 2.904 | 1.217 | 41,91 % |

JavaScript ist über Syntax-, Komponenten-, Contract- und Fake-DOM-Smokes abgesichert. Dafür wird noch keine Prozentzahl ausgewiesen: Ein V8-Wert wäre bei den teilweise statischen DOM-/Quellverträgen keine belastbare Aussage über tatsächlich ausgeführte Browserlogik. Browsernahe JS-Line-Coverage bleibt ein eigener Ausbaupunkt und wird nicht mit der PHP-Zahl vermischt.

## Stufe 3: authentifizierte HTTP-Smokes

Das verwendete Konto muss Nextcloud-Admin sein, weil der OrgSuite- und Raum-Smoke auch administrative Schutzgrenzen prüfen.

```bash
AD_SUITE_BASE_URL=https://nextcloud-dev.ddev.site \
AD_SUITE_USER=admin \
AD_SUITE_PASSWORD='…' \
RUN_DDEV_CHECKS=1 \
RUN_HTTP_SMOKES=1 \
scripts/verify-ad-suite-delivery.sh
```

Die HTTP-Smokes prüfen DOM-Verträge, API-Payloads, CSRF-Ablehnung, Adminschutz sowie selbstbereinigende Urlaub- und Raumbuchungsvorgänge.

## Stufe 4: Rechtematrizen

```bash
RUN_DDEV_CHECKS=1 \
RUN_ACCESS_MATRICES=1 \
scripts/verify-ad-suite-delivery.sh
```

Die Rechtematrizen erzeugen temporäre Konten und Gruppenmitgliedschaften für typische Allow-/Deny-Fälle und räumen sie auch bei Fehlern wieder auf. Sie verändern keine vorhandenen Fachdatensätze.

## Releaseentscheidung

Vor einer externen Übergabe müssen mindestens Stufe 1 bis 3 erfolgreich sein. Stufe 4 ist verpflichtend, wenn Organisation, Gruppen, Hierarchie oder Berechtigungen verändert wurden.

Ein erfolgreiches Gate ersetzt nicht:

- die Neuinstallation auf dem Ziel-Staging-System,
- Backup und geprüften Rückbau,
- Datenschutz- und Mitbestimmungsfreigabe,
- eine externe Sicherheitsprüfung,
- die fachliche Abnahme durch die Auftraggeberin.
