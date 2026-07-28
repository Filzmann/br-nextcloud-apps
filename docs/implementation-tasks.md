# Freigegebene Parent-Umsetzungsaufgaben

Stand: 27. Juli 2026

Diese Datei enthält ausschließlich freigegebene, noch nicht umgesetzte
Aufgaben des Parent-Repositories. App-Code und app-spezifische Teilaufgaben
bleiben in den Roadmaps der getrennten App-Repositories. Jede
Verhaltensänderung benötigt einen eigenen Auftrag und folgt in den betroffenen
Repositories dem Skill `test-driven-change`.

## PARENT-AD-CATALOG – Produktkatalog koordinieren und prüfen

Status: bereit zur Umsetzung nach eigenem Cross-Repo-Auftrag

Abhängige Aufgaben:

- `LB-AD-CATALOG` in `localbase`
- `ORGS-AD-CATALOG` in `orgsuite`
- `RECR-AD-CATALOG` in `adrecruitment`
- `ADS-AD-CATALOG-DOCS` in `ad-suite`

Umfang:

- Den von LocalBase bereitgestellten, versionierten Katalog als einzige
  maschinenlesbare Quelle für stabile Produkt-ID, Produkttyp, Reihenfolge und
  technische Einstiegsroute verwenden.
- `adcalendar`, `adplaner`, `adurlaub`, `adroom` und `adrecruitment`
  aufnehmen. `localbase` und `orgsuite` bleiben ausdrücklich als
  Infrastruktur klassifiziert.
- Standalone-Fähigkeit, Menüzugehörigkeit und Aufnahme in Suite- oder
  Produktarchive als getrennte Eigenschaften modellieren. Die Aufnahme von
  `adrecruitment` in den Katalog erweitert ohne eigene Releasefreigabe keine
  bestehenden AD-Produktarchive.
- Sichtbare Labels nicht in Parent-Skripten festschreiben; sie werden von den
  jeweiligen Nextcloud-Apps lokalisiert.

Abnahmekriterien:

- Ein Parent-Contract-Test erkennt fehlende, doppelte und unbekannte
  Produkt-IDs, ungültige Reihenfolgen, nicht vorhandene App-Routen und
  widersprüchliche Infrastruktur-/Bundle-Eigenschaften.
- Installer, Releasebau und Delivery-Prüfungen lesen den Katalog oder werden
  ausdrücklich dagegen geprüft; es verbleibt keine unabhängige manuelle
  Produktliste.
- Bestehende Standalone- und Mehrproduktverträge bleiben durch positive und
  negative Tests erhalten; Navigation erweitert keine Fachberechtigung.
- Provider- und Consumer-Tests in LocalBase, OrgSuite und AD Recruitment sowie
  `scripts/check-fast` und der vollständige Workspace-Check sind grün.

## PARENT-DOC-REFS – Technische Dokumentreferenzen prüfen

Status: bereit zur Umsetzung

Umfang:

- Eindeutig maschinenlesbare Klassen-, Background-Job-, Skript- und
  Repositorypfade aus Betriebsdokumentation gegen die technische Quelle
  prüfen.
- Bereits vorhandene Versions-, Release- und Skill-Synchronisationschecks
  wiederverwenden und nicht duplizieren.
- Semantische Aussagen, Roadmap-Inhalte und frei formulierte Beispiele nicht
  als vermeintlich exakten technischen Vertrag behandeln.

Abnahmekriterien:

- Ein zunächst roter Fixture-Test belegt mindestens eine veraltete technische
  Referenz und wird durch den kleinsten neuen Check grün.
- Exakte Referenzen blockieren bei Nichtexistenz; heuristisch erkannte
  Referenzen melden zunächst nur eine Warnung mit enger, dokumentierter
  Ausnahme.
- Der Check ist in `scripts/check-fast` eingebunden und erzeugt bei korrekter
  Dokumentation keine Warnungen.

## PARENT-RC-CLEANUP – RC-Bereinigung explizit machen

Status: bereit zur Umsetzung

Umfang:

- Den automatischen Aufruf von
  `scripts/prune-ad-suite-release-candidates.sh` aus dem Release-Builder
  entfernen.
- Die Bereinigung erst nach erfolgreichem Neubau als eigenen, bewusst
  beauftragten Schritt dokumentieren.
- Vor dem Löschen die exakt erkannten Artefakte anzeigen; nur eng validierte
  lokale RC-Namen unter dem ausdrücklich angegebenen `dist`-Root zulassen.

Abnahmekriterien:

- Ein Release-Builder-Test beweist, dass ein normaler RC-Bau ältere RCs nicht
  löscht.
- Pruner-Tests belegen Vorschau, ausdrückliche Ausführung, Erhalt des
  angegebenen RCs und aller finalen Releases sowie Ablehnung ungültiger Labels
  und Ziele.
- Release-Skill und Betriebsdokumentation nennen Löschwirkung,
  Wiederherstellungsgrenze und den getrennten Bereinigungsaufruf.
- `scripts/check-fast` und das saubere AD-Suite-Delivery-Gate sind grün.
