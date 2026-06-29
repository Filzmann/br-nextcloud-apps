# AGENTS.md – BR Nextcloud Apps

## Projekt

Lokale Nextcloud-Entwicklungsumgebung für die Betriebsrats-App `brtop`.

Projektwurzel:

    ~/projects/br-nextcloud-apps

DDEV-/Nextcloud-Projekt:

    ~/projects/br-nextcloud-apps/nextcloud-dev

App-Quellcode:

    ~/projects/br-nextcloud-apps/brtop

Lokale App-URL:

    https://nextcloud-dev.ddev.site/apps/brtop/

Nextcloud-App-ID:

    brtop

## Grundregeln

Terminal/CLI vor GUI.

Änderungen klein, prüfbar und rückbaubar halten.

Keine Commits, kein Push und kein Deployment ohne ausdrückliche Freigabe durch Simon.

DDEV wird immer aus diesem Ordner gesteuert:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev

Wichtige Befehle:

    ddev start
    ddev stop
    ddev restart
    ddev describe
    ddev launch /apps/brtop/
    ddev exec -d /var/www/html/html php occ status
    ddev exec -d /var/www/html/html php occ app:list | grep -i brtop

## Mount

Die App wird per DDEV-Bind-Mount eingebunden:

    ~/projects/br-nextcloud-apps/brtop
    -> /var/www/html/html/custom_apps/brtop

Konfiguration:

    nextcloud-dev/.ddev/docker-compose.brtop.yaml

Der Mount muss den lowercase-Pfad `~/projects/...` verwenden. Nicht `~/Projects/...`.

## Relevante App-Dateien

    brtop/appinfo/info.xml
    brtop/appinfo/routes.php
    brtop/lib/Controller/PageController.php
    brtop/lib/Controller/ApiController.php
    brtop/lib/Service/OdtTemplateRenderer.php
    brtop/templates/index.php
    brtop/templates/odt/protokoll-template.odt
    brtop/js/main.js
    brtop/css/style.css

## Fachliche BR-Logik

Standardstruktur einer BR-Sitzung:

    1. Protokolle
    2. Personelle Angelegenheiten
    3. Arbeitsorganisatorisches
    4. Bericht aus den Sprechstunden seit der letzten Sitzung
    5. Weitere Tagesordnungspunkte

Unter TOP 2 derzeit berücksichtigt:

    2.1 Personelle Einzelmaßnahmen nach § 99 BetrVG
    2.2 Vorläufige personelle Maßnahmen nach § 100 BetrVG
    2.3 Anhörungen zu Kündigungen nach § 102 BetrVG

§ 101 BetrVG wird aktuell ignoriert.

Einladung:

- §99-, §100- und §102-Fälle dürfen kompakt unter „Personelle Angelegenheiten“ zusammengefasst werden.

Protokoll:

- §99-, §100- und §102-Fälle müssen getrennt aufgeführt werden.

Beschlüsse:

- Jeder beschlussrelevante Fall erhält ein eigenes Beschlussdokument.
- Das gilt auch dann, wenn mehrere Fälle gemeinsam abgestimmt wurden.

## Default-Beschlussfragen

Bei Zustimmungsverweigerung:

    Wer verweigert die Zustimmung zu <<Maßnahme>> und widerspricht ihr damit?

Bei allgemeinen Beschlüssen:

    Wer stimmt <<Maßnahme>> zu?

Allgemeine Beschlusstypen:

- Entsendung / Schulung
- Betriebsvereinbarung
- Ausschuss- oder Arbeitsauftrag
- Beauftragung / Verfahren
- Protokollgenehmigung
- organisatorischer Beschluss

Sonderfall §100 BetrVG:

    Wer bestreitet, dass die vorläufige Durchführung der personellen Maßnahme <<Maßnahme>> aus sachlichen Gründen dringend erforderlich ist?

Sonderfall §102 BetrVG:

    Wer widerspricht der beabsichtigten Kündigung <<Maßnahme>> gemäß § 102 BetrVG?

## ODT-Vorlage

Aktuelle Protokollvorlage:

    brtop/templates/odt/protokoll-template.odt

Anforderungen:

- Kopfzeile mit `{{GREMIENNAME}}` und `{{GREMIUM_ADRESSE}}`
- keine Seitenrahmen
- Rahmen nur um bestimmte Elemente
- Inhaltsverzeichnis als echtes ODT-Verzeichnisobjekt
- Formatvorlagen möglichst: Standard, Textkörper, Überschrift 1, Überschrift 2, Überschrift 3
- Beschlusskasten als wiederverwendbares Element
- Protokoll und Beschlussdokumente sollen denselben Beschlusskasten verwenden

## Prüfungen nach Änderungen

PHP-Syntax:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev
    ddev exec php -l /var/www/html/html/custom_apps/brtop/lib/Controller/ApiController.php
    ddev exec php -l /var/www/html/html/custom_apps/brtop/lib/Service/OdtTemplateRenderer.php
    ddev exec php -l /var/www/html/html/custom_apps/brtop/templates/index.php

Logs:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev
    ddev exec -d /var/www/html/html tail -n 160 data/nextcloud.log
    ddev logs -s web | tail -n 120

Bei JS-/CSS-/Template-Cacheproblemen App-Version in `brtop/appinfo/info.xml` erhöhen und App neu aktivieren:

    cd ~/projects/br-nextcloud-apps/nextcloud-dev
    ddev exec -d /var/www/html/html php occ app:disable brtop
    ddev exec -d /var/www/html/html php occ app:enable brtop

## Git-Regeln

Vor Commit immer zeigen:

    git status --short
    git diff --stat
    git diff --name-only

Nicht ohne ausdrückliche Freigabe:

    git add .
    git commit
    git push

Statt `git add .` gezielt Dateien hinzufügen.

## Arbeitsweise für Codex

Vor Änderungen:

1. Relevante Dateien lesen.
2. Problem knapp benennen.
3. Minimalen Patch vorschlagen oder anwenden.
4. Keine Architekturänderung ohne Begründung.

Nach Änderungen:

1. Syntax prüfen.
2. Relevante grep-Prüfung ausführen.
3. DDEV/Nextcloud nur neu starten, wenn nötig.
4. Ergebnis knapp melden.
