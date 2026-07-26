# Unverbindliche Learning Candidates

Stand: 26. Juli 2026

Diese Liste konserviert wiederverwendbare Beobachtungen für eine spätere
Abnahme. Kein Eintrag ist eine geltende Regel, ein freigegebener
Architekturvertrag oder ein beauftragter Codeumbau. Die Übernahme erfolgt
ausschließlich nach erneuter Evidenzprüfung und ausdrücklicher Freigabe gemäß
dem Skill `evaluate-learning-candidate`.

## Verifizierte Candidates

### Kanonischer AD-Produkt- und Menükatalog

- Ebene: beide
- Ziel: Konfiguration plus Contract-Test
- Status: verifiziert, nicht freigegeben
- Evidenz: Produktfolge, Labels und Routen sind in LocalBase, OrgSuite,
  Installer, Releasebau und Tests mehrfach hinterlegt.
- Vorgeschlagener Inhalt: Stabile Produkt-IDs, Rollen, Reihenfolge und Routen
  einmal pflegen; sichtbare Labels appweise lokalisieren.

### Berechtigungsmatrix aus Organisationssnapshot

- Ebene: beide
- Ziel: öffentlicher read-only LocalBase-Vertrag und Consumer-Tests
- Status: verifiziert, nicht freigegeben
- Evidenz: Die Berechtigungsmatrix erkennt `ad-EB-*`, `ad-PFK-*` und
  Urlaubssuffixgruppen mit eigenen Regexen, während die Fachapps den
  konfigurierten Organisationsvertrag verwenden.
- Vorgeschlagener Inhalt: Rohgruppen für Revision und Export erhalten,
  fachliche Rechtebedeutung aus einem validierten Organisationssnapshot lesen.

### Gemeinsamer BR-Gruppenvertrag

- Ebene: beide
- Ziel: kleiner LocalBase-Vertrag plus additive Consumer-Migration
- Status: verifiziert, nicht freigegeben
- Evidenz: BRTop und BRStunden führen Mitglieds-, Vorsitz- und
  Stellvertretungsgruppen mehrfach und teilweise unterschiedlich
  konfigurierbar.
- Vorgeschlagener Inhalt: Einen konfigurierbaren Vertrag mit vollständigen
  Allow-/Deny- und Bestandsmigrationstests entwerfen.

### Administrierbare AD-Kalenderdefaults

- Ebene: App
- Ziel: AD-Kalender-Administration
- Status: verifiziert, nicht freigegeben
- Evidenz: Kopano-/CalDAV-Vorgabe und sichtbarer Zielkalendername stehen in
  mehreren PHP- und JavaScript-Schichten.
- Vorgeschlagener Inhalt: Je Wert eine validierte serverseitige Quelle;
  bestehende Werte bleiben Migrationsdefaults, technische DAV-IDs stabil.

### BR-Dokumentstammdaten und Vorlagen

- Ebene: App
- Ziel: BRTop-/BRStunden-Administration und versionierte Vorlagen
- Status: verifiziert, nicht freigegeben
- Evidenz: Gremienname, Anschrift, Ausschusscodes, Ausstellungsort und sichtbare
  Dokumenttexte sind teilweise organisationsspezifisch fest codiert.
- Vorgeschlagener Inhalt: Gesetzlich beziehungsweise fachlich feste Semantik
  vorab von organisationsspezifischen Stammdaten und Vorlagentexten trennen.

### Locale-fähige Datumsnamen und Nextcloud-l10n

- Ebene: beide
- Ziel: appweise Darstellung und späterer Warn-/Contract-Check
- Status: verifiziert beziehungsweise für die vollständige l10n-Migration
  plausibel, nicht freigegeben
- Evidenz: Mehrere Apps verwenden manuelle Monats-/Wochentagsarrays,
  Substring-Abkürzungen und direkt sichtbare deutsche Rohtexte.
- Vorgeschlagener Inhalt: Datumsnamen aus aktiver Locale erzeugen; ISO-Daten,
  Enum-Werte und technische Schlüssel unverändert lassen; l10n vertikal je App
  einführen.

### Dokumentationsdrift technisch prüfen

- Ebene: Parent
- Ziel: Skript/Test
- Status: verifiziert, nicht freigegeben
- Evidenz: README-Versionen weichen von `info.xml` ab; Betriebsdokumentation
  nennt einen verschobenen Background-Job; Roadmaps enthalten Ist-Stand;
  lokale Kontrollkopien werden nur manuell aktualisiert.
- Vorgeschlagener Inhalt: Metadaten-, Job-, Roadmap- und
  Skill-Synchronisationschecks erst nach gesonderter Codefreigabe ergänzen.

### Explizite RC-Bereinigung

- Ebene: Parent
- Ziel: Release-Skill beziehungsweise Release-Skript
- Status: verifiziert, nicht freigegeben
- Evidenz: Der Releasebau löscht bei RC-Labels automatisch ältere lokale
  RC-Artefakte, ohne dass der Skill diese Nebenwirkung nennt.
- Vorgeschlagener Inhalt: Bereinigung sichtbar dokumentieren oder durch einen
  ausdrücklichen Schalter beauftragen.

## Bereits eingeordnete Beobachtungen

Die folgenden Punkte sind keine offenen Learning Candidates:

- Kalenderkontext und gemeinsamer Ferien-/Feiertagsvertrag sind im aktuellen
  uncommitted Arbeitsstand bereits umgesetzt und benötigen Abnahme statt einer
  erneuten Regelaufnahme.
- Providerendpunkte, HTTPS-/SSRF-Grenzen und Runtime-Sicherheitslimits sind
  bestehende Code- und Sicherheitsverträge.
- Externe OrgSuite-Links sind ein Roadmapziel.
- Organisations-, Schicht- und Sitzungsvorgaben, die bereits konfigurierbare
  Bootstrapdefaults sind, bleiben bestehende Verträge.
- Ein einzelner verbleibender Hardcode ist ein konkreter Code-/Testbefund und
  kein dauerhaftes Learning.
