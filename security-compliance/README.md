# Security- und Nachweisvertrag

Dieser Ordner verbindet vorhandene Architektur-, Datenschutz-, Test- und
Delivery-Verträge zu einer schlanken Nachweiskette. Er ersetzt keine
app-lokale Fachregel und keine bestehende Testquelle. Der Stand ist an den
Grundgedanken von APP.6, APP.7, CON.8, OPS.1.1.6 und APP.3.1 des
BSI-IT-Grundschutzes orientiert. Eine BSI- oder IT-Grundschutz-Zertifizierung
liegt nicht vor und wird hier nicht behauptet.

## Kanonische Quellen

- `scope.json` bestimmt Repository-, Verantwortungs- und Plattformgrenzen.
- FLZ-Produktmengen stammen ausschließlich aus
  `localbase/resources/flz-product-catalog.json`; App-Laufzeitabhängigkeiten
  stammen ausschließlich aus der jeweiligen `appinfo/info.xml`. Der
  Security-Scope dupliziert beide Aussagen bewusst nicht.
- `bsi-mapping.json` ist die einzige zentrale Zuordnung von
  Anforderungsthemen zu Implementierung, Tests und Evidence.
- `threat-model.json` hält gemeinsame Controls und je App nur die
  abweichenden Assets, Grenzen und Bedrohungen.
- `vulnerability-exceptions.json` ist die einzige zentrale Liste akzeptierter
  Dependency-Sicherheitsausnahmen. `analysis-exceptions.json` enthält
  ausschließlich befristete, fingerprintgebundene Gitleaks-/Semgrep-
  Fehlalarme oder Restrisiken; ein Gitleaks-Fund darf nur als Fehlalarm
  klassifiziert werden. Beide Listen sind im Ausgangsstand bewusst leer.
- `scanner-tools.json` pinnt Gitleaks und OSV-Scanner je Linux-Architektur über
  SHA-256 sowie das Semgrep-Nonroot-Image über seinen vollständigen OCI-Digest.
  `gitleaks.toml`, `gitleaks-ignore.txt`, `semgrep-rules.yml` und
  `osv-scanner.toml` sind die festen zentralen Scannerkonfigurationen. Sie
  enthalten keine Suppression; es wird kein veränderlicher Registry-Regelsatz
  zur Laufzeit bezogen.
- App-spezifische Rechte, Datenklassen, Provider und Tests bleiben in der
  jeweiligen `AGENTS.md`, Architektur, Implementierung und Testsuite.

Alle maschinenlesbaren Dateien werden durch
`scripts/check-security-compliance` geprüft. Der Check löst Pfade gegen den
tatsächlichen Workspace auf und vergleicht den App-Scope mit
`config/workspace-repositories.tsv`. Ein erneutes Eintragen von Produktmengen
oder Runtime-Abhängigkeiten in `scope.json` macht den Check rot.

## Änderungswirkung

Für eine konkrete Dateiliste kann der Check zusätzlich die kleinste relevante
Security-Prüfung nennen:

```bash
scripts/check-security-compliance \
  --changed-file flzcalendar/appinfo/routes.php \
  --changed-file flzcalendar/lib/Service/CalendarAccessService.php
```

Der reale app-übergreifende Harness `scripts/check-apps` ruft denselben Check
mit `--workspace-diff` auf. Dabei werden alle gestagten, ungestagten und
ungetrackten App-Pfade aus den tatsächlichen Worktrees ausgewertet. Die
explizite Dateiliste bleibt für fokussierte lokale Diagnose und Contract-Tests
verfügbar.

Routen und öffentliche APIs lösen API-Contract- und Threat-Model-Review aus,
Autorisierung zusätzlich Permission-/Negativtests, persistente Daten- und
Privacy-Pfade die Processing-/Provider-Prüfung, Migrationen den vorhandenen
Migration-/Reinstallpfad und Dependency-Dateien SBOM-/Vulnerability-Review.
Ein Trigger verlangt eine inhaltliche Entscheidung, aber keine stupide
Pflichtänderung an Mapping oder Threat Model. Sind vorhandene Controls und
Nachweise weiterhin richtig, genügt deren Prüfung; nur eine tatsächlich neue
oder geänderte Aussage wird in den zentralen Dateien aktualisiert.

## Secure-Development- und Release-Ablauf

1. Fachliche und sicherheitsrelevante Invarianten sowie Scope bestimmen.
2. Verhaltensänderungen über `test-driven-change` mit sinnvollen Deny- und
   Fehlerfällen entwickeln.
3. App-lokale Tests und je nach Grenze Provider-/Consumer-, DDEV-,
   Reinstall-, Migrations- oder Zugriffs-Smokes ausführen.
4. Parent-Änderungen mit `scripts/check-fast`, vollständige Workspace-Prüfung
   mit `scripts/check-full` prüfen. Beide sind kein Releaseurteil.
5. Ein Filzmann-Full-Suite-Release ist nur nach dem sauberen
   `scripts/check-flz-full-suite-delivery` freigabefähig. Der Builder erzeugt
   CycloneDX-SBOM, Quellcommit-/Versionsmanifest, SHA-256-Prüfsummen und eine
   maschinenlesbare Release-Evidence. Generierte Evidence bleibt im
   `dist`-/CI-Artefakt und wird nicht im Git-Tree gepflegt.

Der verbindliche Scannerlauf ist `scripts/run-security-scanners
--evidence-file <pfad>`. Er scannt den Parent und alle als `app` registrierten
Repositories: Gitleaks prüft nur den aktuellen Arbeitsbaum und gibt Secrets
vollständig redigiert aus, Semgrep läuft mit dem lokalen Regelsatz ohne
Container-Netzwerk, und OSV-Scanner prüft alle getrackten sowie ungetrackten,
nicht ignorierten Lockdateien gegen die öffentliche OSV-Datenbank. Optional
vorhandene app-lokale `resources/third-party-components.cdx.json` werden als
CycloneDX 1.6 gegen die App-ID, versionsgepinnte PURLs, sichere app-relative
Bundle-Pfade, lexikalisch deterministische Baumhashes und -dateizahlen sowie
explizit gepinnte Dateihashes validiert. Erst danach wird der gebundene
Drittanbieterbaum aus dem First-Party-Semgrep-Scope genommen und stattdessen
per OSV über seine PURLs geprüft. Das validierte app-lokale Inventar bleibt
die einzige Komponentenquelle und wird beim Build in die Release-SBOM
übernommen. Fehlende Lockdateien werden als null geprüfte Lockdateien sichtbar,
nicht als gefundene Dependency-Freiheit interpretiert. Ein ungültiges Inventar,
Download, Containerfehler, ungültiger Bericht, nicht akzeptierter Fund oder
eine fällige/abgelaufene Ausnahme macht den Lauf rot.

Semgrep-Berichte unterscheiden `warning` und `error` ausdrücklich. Insbesondere
bleiben gültige Findings aus einem Bericht mit `PartialParsing`-Warnung in der
Evidence erhalten; der betroffene Scan gilt dennoch nur als `partial` und ist
nicht releasefähig. Echte Parser-, Berichts- und Ausführungsfehler bleiben
fail-closed. Warn- und Fehlermeldungen werden ohne Scanner-Snippets oder freie
Meldungstexte, aber mit Typ und – soweit vorhanden – normalisiertem Pfad
festgehalten.

Alle Scannerprozesse erhalten eine sterile, explizite Umgebung. Gitleaks
ignoriert repository-lokale Konfigurationen, `.gitleaksignore` und
`gitleaks:allow`; Semgrep ignoriert `.gitignore`, `.semgrepignore` und
`nosem`; OSV überschreibt repository-lokale Konfiguration mit der neutralen
zentralen Datei. Der Quellumfang selbst bleibt die vorab erzeugte Menge aus
getrackten sowie ungetrackten, nicht per Git ignorierten Dateien. Ausnahmen
werden erst nach dem Scan durch die beiden zentralen, befristeten
Exception-Listen angewendet.

`SECURITY_SCANNER_TEST_MODE=1` ist ausschließlich ein Harness für
Test-Doubles. Seine Evidence trägt den Status `diagnostic`, ist ausdrücklich
nicht releasefähig und wird sowohl vom Release-Builder als auch vom
Release-Evidence-Generator abgelehnt. Der Contract-Test ergänzt die Doubles um
einen echten synthetischen Gitleaks-/Semgrep-/OSV-Lauf, der lokale
Suppressionsversuche nachweislich nicht wirksam werden lässt.

Der FLZ-Release-Builder führt diesen Lauf nach seinem Kollisionsguard und vor
dem ersten Release-Write aus. Die normalisierte, datensparsame Scanner-Evidence
wird gehasht in die Release-Evidence übernommen. Der wiederverwendbare
Staging-CI-Pfad prüft den Parent und die konkret auszurollende App vor dem
Paketbau. Die app-lokalen Pull-Request-Workflows bleiben eigene Repositories;
eine flächendeckende PR-Anbindung benötigt dort jeweils einen ausdrücklichen
Schreibauftrag.

Die Builder-Evidence unterscheidet tatsächlich ausgeführte und übersprungene
Builder-Tests sowie Kandidaten- und Dirty-Diagnosemodus. Der Builder prüft
Mapping und die drei obligatorischen Scanner selbst, behauptet
aber niemals die Veröffentlichbarkeit: Das Delivery-Gate und eine menschliche
Freigabe bleiben getrennt. Bestehende oder auch nur als Symlink belegte
Evidence-, Hash-, Archiv- und SBOM-Ziele werden vor dem ersten Release-Write
abgelehnt.

Der heutige Gate-Stand umfasst Tests, Provider-/Consumer-Verträge,
Support-Range, Metadaten, reproduzierbare Archive, Hashes, Releaseinhalt,
Gitleaks, einen kleinen projektspezifischen Semgrep-Regelsatz und OSV-Scans
vorhandener Lockdateien sowie validierter app-lokaler Third-Party-PURLs.
GitHub-Actions und das zentrale Coverage-Tooling
bleiben reale Supply-Chain-Bestandteile; OSV deckt Action-Provenienz nicht ab.
Auch die lokalen Semgrep-Regeln und Gitleaks ersetzen weder Code Review noch
einen externen Penetrationstest.

## Vulnerability Management

Eingang erfolgt über den privaten Meldeweg aus der Root-`SECURITY.md`. Die
technische Triage bestimmt betroffene App/Version, Ausnutzbarkeit, Schwere,
Reichweite, Daten-/Rechtewirkung und verfügbare Abhilfe. Danach folgen Fix im
zuständigen Repository, ein Regressionstest auf der niedrigsten
aussagekräftigen Ebene, alle betroffenen Contract-/Release-Gates, ein
freigegebener Release und bei externer Betroffenheit ein Advisory.

Eine Dependency-Ausnahme benötigt zusätzlich den exakten Scanner-Fingerprint,
Repository und Lockdateipfad sowie mindestens Komponente, direkte oder
transitive Beziehung, bekannte Schwachstelle und Schwere, Fixverfügbarkeit,
Begründung, kompensierende Controls, Reviewdatum und Ablaufdatum. Abgelaufene
Ausnahmen machen den zentralen Check rot. Eine Semgrep-Ausnahme bindet analog
Scanner, Regel, Repository, Pfad, Zeile und den SHA-256 des tatsächlichen
Fundinhalts; der daraus gebildete Fingerprint ändert sich bei geändertem Inhalt.
Gitleaks erlaubt nur dokumentierte Fehlalarme, keine pauschale
Risikoakzeptanz eines tatsächlichen Secrets. Aktuell existiert keine
akzeptierte Ausnahme.

## Schlanke Incident Response

- Bekannte Schwachstelle oder Berechtigungsumgehung: betroffenen
  Release-/Deploymentpfad stoppen, Reichweite und Logs datensparsam sichern,
  Deny-Regressionstest erstellen, Fix und kontrollierten Release/Rollback
  entscheiden.
- Kompromittierte Dependency oder Build-Action: Nutzung stoppen, Lock/Ref und
  erzeugte Artefakte bestimmen, betroffene Releases neu bauen oder
  zurückziehen und SBOM/Hashes mitführen.
- Versehentlich veröffentlichtes Secret: Secret sofort außerhalb des
  Repositories widerrufen/rotieren, Reichweite feststellen, History-Änderung
  nur ausdrücklich freigegeben durchführen und Scanner-/Regressionlücke
  schließen. Das Entfernen aus einem Commit allein gilt nicht als Widerruf.
- Fehlerhaftes Security-Release: Auslieferung stoppen, sicheren vorherigen
  Stand oder Roll-forward wählen, Integrität und Datenkompatibilität prüfen
  und Entscheidung samt Hashes und Testergebnis festhalten.

Organisatorische Incident-Verantwortung, externe Eskalationskontakte und
Meldepflichtentscheidungen sind nicht im Repository festgelegt und bleiben
`DECISION-REQUIRED`.

## Support und EOL

Maschinenlesbare Zustände sind `supported`, `security-fixes-only` und
`end-of-life`. Aktuell ist nur der aktive Entwicklungsstand beschrieben; eine
verbindliche Produktdauer oder Security-Fix-Frist ist nicht beschlossen.
Nextcloud-Minimum und nachgewiesenes Maximum stammen weiterhin ausschließlich
aus `appinfo/info.xml` und dem Future-Compatibility-Gate. Vor einem
Production-Freeze müssen Versionen, Übergänge, Fristen, Advisory-Kanal und
Owner ausdrücklich entschieden werden.

## Evidence- und Zertifizierungsgrenze

Die Matrix und automatischen Artefakte belegen nur die dort referenzierten
Kontrollen für den geprüften Quell- und Laufzeitstand. Externe Penetrationstests,
organisatorische Wirksamkeitsprüfungen, formale Risikoakzeptanz und eine
Zertifizierungsentscheidung bleiben außerhalb des automatischen Repository-
Nachweises.
