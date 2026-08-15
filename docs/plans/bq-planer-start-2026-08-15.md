# Freigabe und Start: AD BQ-Planer

Datum: 15. August 2026

## Entscheidung

Der zuvor nur vorgemerkte BQ-Planer wurde am 15. August 2026 ausdrücklich als
neue, separat versionierte Nextcloud-App `adbqplanung` freigegeben.

Die App ist Kategorie B und wird kanonische Quelle für BQ-Durchlaufprogramme,
Unterrichtstermine, Curriculum-Snapshots, Dozentinnen, später Kapazität und
Anwesenheit. AD Recruitment bleibt kanonische Quelle für Bewerbungen,
Zuordnungen, Eignungsentscheidungen und Einstellungsfreigaben.

## Freigegebener erster Schnitt

- Standarddauer sieben Arbeitstage, konfigurierbar zwischen 1 und 30;
- Standardstart Freitag, konfigurierbar auf Montag bis Freitag;
- erklärbare Monatsvorschläge, die übergebene Ferien-, Feiertags-, Brücken-
  und Sperrperioden vermeiden;
- beliebig viele zusätzliche, manuell initiierte Vorschlagsläufe;
- versionierte Curriculum-Vorlagen und pro Durchlauf veränderbare Snapshots;
- interne Haupt-PFK und abweichende interne oder externe Dozentinnen je Modul;
- eintägige Praxisreflexionen nach einem, drei und vier Kalendermonaten ab dem
  letzten Haupt-BQ-Tag; und
- eine admin-beschränkte, zugängliche Grundoberfläche.

## Bewusste Grenzen

Der erste App-Kern besitzt keine gemeinsame Laufzeitabhängigkeit. Ein späterer
Kalenderprovider und die Recruitment-Anbindung benötigen kleine,
aktivierungs- und versionsbewusste öffentliche Verträge sowie eigene
Provider-/Consumer-Tests. Bis dahin werden weder LocalBase- noch
Recruitment-Interna verwendet.

Persistenz, granulare Rollen, Veröffentlichung, Teilnehmerinnen, Anwesenheit,
Kommunikation und produktive Cross-App-Integration bleiben getrennte
Folgepakete. Für Dozentinnen- und Bearbeitungsreferenzen ist vor fachlicher
Fertigstellung ein konkreter PersonalDataProvider- und Retention-Vertrag
erforderlich.

## Rückbaugrenze

Solange keine öffentliche Consumerintegration ausgerollt ist, kann der erste
Schnitt durch Deaktivierung der App sowie Entfernung von Mount und
Workspace-Registrierung zurückgebaut werden. Bestehende Recruitment-Daten
werden weder migriert noch verändert.
