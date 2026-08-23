# ADR 0003: Berechtigungsmatrix im Portfolio IKT/Datenschutz

- Status: angenommen
- Entscheidung: 2026-08-23
- Geltungsbereich: Produktzuordnung, Navigation und künftige Integration der
  App `br_permission_matrix`
- Umsetzung: geplant; Ziel-ID entschieden, App-Code und bestehende
  Berechtigungen sind unverändert

## Kontext und verifizierter Stand

Die Berechtigungsmatrix ist fachlich keine Gremien-Arbeits-App. Sie erstellt
eine read-only Positivliste gruppenbezogener Nextcloud-Berechtigungen,
Snapshots, Baselines, Diffs und Exporte. Ihr Zielpublikum umfasst
Betriebsrat, IKT-Ausschuss, Datenschutz und IT-Administration, doch die
fachliche Verantwortung liegt bei IKT/Datenschutz und technischer Governance,
nicht bei Sitzungen, Beschlüssen oder sonstiger Gremienarbeit.

Im aktuellen Repository ist verifiziert:

- eigene App-ID `br_permission_matrix`, eigenes Repository und eigener
  Release-Lebenszyklus;
- eigene Snapshot-, Zeilen-, Zellen-, Diff-, Export-, Adapterstatus- und
  Auditpersistenz;
- eigene Konfiguration, Administration, Hintergrundjob und `occ`-Kommandos;
- read-only Adapter auf öffentliche Nextcloud- beziehungsweise ausdrücklich
  versionierte Providerverträge;
- direkter heutiger Navigationsvertrag mit OrgSuite: `info.xml` nennt
  `orgsuite`, das Template lädt OrgSuite-Assets und verwendet
  `data-suite="br"`;
- kein eigener Nextcloud-Hauptnavigationseintrag;
- personenbezogene Snapshot-, Export- und Auditbezüge, für die noch ein
  `PersonalDataProvider` und fachliche Retentionentscheidungen fehlen.

## Entscheidung

Die Berechtigungsmatrix wird perspektivisch dem Produktportfolio
**IKT/Datenschutz** zugeordnet. Sie bleibt eine eigenständige App der
**Kategorie B** nach ADR 0001 und wird nicht in die Privacy-App verschmolzen.

Die gemeinsame Portfoliozuordnung bedeutet:

- verständliche Auffindbarkeit im Bereich IKT/Datenschutz;
- abgestimmte, aber getrennte Navigation und Produktdokumentation;
- hohe Priorität für ihren Art.-15-Provider, Audit- und Retentionvertrag;
- mögliche spätere Anzeige als verwandtes Governance-Werkzeug;
- gemeinsame Qualitätsmaßstäbe für Transparenz, Vollständigkeit,
  Datenminimierung und Auditierbarkeit.

Sie bedeutet ausdrücklich nicht:

- Übertragung der Matrixdaten oder Berechtigungsbewertung an die Privacy-App;
- direkte Zugriffe der Privacy-App auf Matrix-Tabellen, Exporte oder
  AppConfig;
- gemeinsame Berechtigungen oder automatische Rollenvererbung;
- eine technische Voraussetzung der Matrix für Art.-15-Auskunft;
- eine technische Voraussetzung der Privacy-App für Scans, Baselines oder
  Exporte.

Die Berechtigungsmatrix bleibt ohne aktive Privacy-App vollständig
funktionsfähig. Die Privacy-App bleibt ohne Berechtigungsmatrix vollständig
fähig, Auskünfte ihrer tatsächlich registrierten Provider zu aggregieren;
ein erwartetes Coverage-Profil weist den fehlenden Matrix-Provider sichtbar
aus.

## Zielstruktur

| Bestandteil | Eigentümer | Ziel |
| --- | --- | --- |
| Scan, Adapter, Positivliste, Snapshots, Baselines und Diffs | Berechtigungsmatrix | unverändert app-lokal |
| Matrix-, Export- und Auditpersistenz | Berechtigungsmatrix | unverändert app-lokal |
| Matrixberechtigungen und Admin-Konfiguration | Berechtigungsmatrix | unverändert serverseitig führend |
| Art.-15-Auskunft zu Matrix-Personenbezügen | Berechtigungsmatrix | eigener `PersonalDataProvider` gegen den öffentlichen Privacy-Vertrag |
| Retention von Snapshots, Exportmetadaten und Audit | Berechtigungsmatrix | eigener `RetentionProvider`; fachliche Beweis- und Aufbewahrungswerte vor Maßnahmen entscheiden |
| Art.-15-Aggregation und Coverage | Privacy-App | nur öffentlicher Providervertrag, kein Fremddatenzugriff |
| Portfolioauffindbarkeit | perspektivisch IKT/Datenschutz | unabhängige Navigation oder kleiner optionaler, versionierter Adapter |

## Navigation und heutige BR-Kopplung

Die aktuelle OrgSuite-Kopplung und `data-suite="br"` sind historischer
Ist-Zustand, nicht die Zielklassifikation. Ihre Entfernung ist eine spätere
schreibende Änderung in `br_permission_matrix` und gegebenenfalls `orgsuite`
mit eigenem Auftrag, Rechte-/Navigationsprüfung und Rückbau.

Das Ziel ist:

1. Die App ist auch ohne OrgSuite erreichbar und bedienbar.
2. Ein neutraler eigener Einstieg oder eine native Nextcloud-Einstellungs-
   beziehungsweise Navigationsfläche trägt die Domäne IKT/Datenschutz.
3. Ist eine übergreifende Privacy-/Governance-Navigation aktiv, darf sie einen
   kleinen optionalen Linkadapter anbieten, aber keine Matrix-Assets,
   Controller oder Rechte übernehmen.
4. Menüsichtbarkeit bleibt ohne Berechtigungswirkung.

Die konkrete Navigationsform wird erst im App-Auftrag nach einer UI- und
Standalone-Prüfung entschieden. Eine zweite unabhängig gepflegte Linkliste
entsteht nicht.

## Technische Identität und öffentliche Eignung

Die App-ID `br_permission_matrix` und der PHP-Namespace enthalten eine
historische BR-Zuordnung. Am 23. August 2026 wurde für die öffentliche
Zielidentität `filzmann_permission_matrix` entschieden. Der Stagingbestand
ist ausdrücklich nicht erhaltenswert; Produktions- oder veröffentlichte
Store-Bestände sind nicht bekannt. Vor der technischen Umbenennung wird dies
noch einmal am tatsächlichen Zielstand verifiziert.

Die zuvor bewerteten Varianten waren:

1. technische ID aus Kompatibilitätsgründen behalten und ausschließlich
   Anzeigename, Beschreibung, Navigation und neutrale Defaults bereinigen;
2. neue neutrale App-ID mit explizitem Daten-, Konfigurations-, Upgrade-,
   Installations- und Deinstallationsvertrag.

Entschieden wurde Variante 2 als sauberer Identitätswechsel vor einer
öffentlichen Veröffentlichung. Wegen AppConfig, Routen, Namespaces, Jobs,
Kommandos, CI, Workspace- und Integrationsreferenzen bleibt eine bloße
Verzeichnisumbenennung unzulässig. Die neutral benannten Tabellen
`permission_matrix_*` können bei einem nachweislich wegwerfbaren Bestand
unverändert neu angelegt werden; eine Datenübernahme wird nicht unterstellt.

Auch die heutigen Standardgruppen `Betriebsrat`, `IKT-Ausschuss`,
`Datenschutz` und `IT-Administration` werden nicht durch diese Root-Planung
verändert. Vor einer neutralen Veröffentlichung ist zu entscheiden, ob
organisationsspezifische Defaults entfallen oder nur als explizites
Migrationsprofil bestehen bleiben. Die Portfoliozuordnung allein erteilt
niemandem Zugriff.

## Migrationsplan und Gates

1. **Root-Planung:** diese Produktgrenze, Providerpriorität und offenen
   Entscheidungen dokumentieren; kein App-Code.
2. **App-Auftrag:** aktuelles Navigations-, OrgSuite-, Daten-, Konfigurations-
   und Releaseinventar gegen den dann aktuellen Code bestätigen.
3. **Identitätsgate:** Ziel-ID `filzmann_permission_matrix` entschieden;
   vor Umsetzung den fehlenden Erhaltungsbedarf und alle Referenzen erneut
   bestätigen.
4. **Standalone-Navigation:** zunächst die Erreichbarkeit ohne OrgSuite
   test-first herstellen; vorhandene Rechte bleiben unverändert.
5. **Privacy-Provider:** personenbezogene Snapshot-, Export-, Audit- und
   optionale Benutzerlistenbezüge vollständig inventarisieren und einen
   subjectgebundenen Provider implementieren.
6. **Retention:** Beweiswert, gesetzliche/fachliche Aufbewahrung, Audit und
   mengenbasierte Snapshot-Retention trennen; zunächst nur Dry Run.
7. **Optionaler Portfolioadapter:** erst nach stabiler Standalone-App und
   Privacy-Vertragsversion einen kleinen Link-/Capability-Vertrag prüfen.
8. **Releaseprüfung:** Installation mit und ohne OrgSuite sowie mit und ohne
   Privacy-App, Update, Deinstallation, Rückbau, neutrale Namen/Fixtures und
   App-Store-Eignung belegen.

Jeder schreibende Schritt benötigt die ausdrückliche Freigabe der betroffenen
App-Repositories. Ein Rückbau der Navigationsänderung stellt den letzten
eigenständigen Matrix-Einstieg wieder her; Matrixdaten werden dabei nicht
migriert oder verändert.

## Folgen und offene Entscheidungen

- Die Root-Produktplanung behandelt die Matrix nicht mehr als Teil einer
  Gremienarbeits-Suite.
- `br_permission_matrix` bleibt bis zu einem eigenen Auftrag technisch und
  visuell unverändert.
- Noch zu entscheiden sind neutrale Defaultgruppen, Navigationsform,
  öffentliches Store-Ziel und der konkrete optionale Portfolioadapter.
- Art.-15-, Audit- und Retentionlücken bleiben sichtbar und werden nicht durch
  die bloße thematische Zuordnung als gelöst dargestellt.
