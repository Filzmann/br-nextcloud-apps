# ADR 0003: Berechtigungsmatrix im Portfolio IKT/Datenschutz

- Status: angenommen
- Entscheidung: 2026-08-23
- Geltungsbereich: Produktzuordnung, Navigation und künftige Integration der
  App `filzmann_permission_matrix`
- Umsetzung: abgeschlossen für Identität, Standalone-Navigation,
  OrgSuite-Entkopplung, Privacy-Provider, Retention-Preview und den
  versionsgebundenen Groupfolders-22.x-Adapter; fachliche
  Retentionmaßnahmen bleiben offen

## Kontext und verifizierter Stand

Die Berechtigungsmatrix ist fachlich keine Gremien-Arbeits-App. Sie erstellt
eine read-only Positivliste gruppenbezogener Nextcloud-Berechtigungen,
Snapshots, Baselines, Diffs und Exporte. Ihr Zielpublikum umfasst
Betriebsrat, IKT-Ausschuss, Datenschutz und IT-Administration, doch die
fachliche Verantwortung liegt bei IKT/Datenschutz und technischer Governance,
nicht bei Sitzungen, Beschlüssen oder sonstiger Gremienarbeit.

Im aktuellen Repository ist verifiziert:

- historische App-ID `br_permission_matrix`, eigenes Repository und eigener
  Release-Lebenszyklus;
- eigene Snapshot-, Zeilen-, Zellen-, Diff-, Export-, Adapterstatus- und
  Auditpersistenz;
- eigene Konfiguration, Administration, Hintergrundjob und `occ`-Kommandos;
- read-only Adapter auf öffentliche Nextcloud- beziehungsweise ausdrücklich
  versionierte Providerverträge;
- historischer Navigationsvertrag mit OrgSuite: `info.xml` nannte
  `orgsuite`, das Template lädt OrgSuite-Assets und verwendet
  `data-suite="br"`;
- inzwischen eigener berechtigungsgeprüfter Nextcloud-Hauptnavigationseintrag;
- personenbezogene Snapshot-, Export- und Auditbezüge, die ein
  subjectgebundener `PersonalDataProvider` aus app-eigenen Tabellen ausweist;
- ein read-only Retention-Preview ohne Ausführungspfad; fachliche
  Retentionentscheidungen und Maßnahmen fehlen weiterhin;
- ein app-lokaler, versionsgebundener Groupfolders-Adapter für die belegte
  22.x-Struktur auf Nextcloud 34, der Root-Rechte ohne Mount-Pfade oder
  Dateiinhalte projiziert und alle inkompatiblen oder unvollständigen Zustände
  als `UNKNOWN` behandelt.

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

## Groupfolders-Kompatibilitätsgrenze

`groupfolders` bleibt eine eigenständige fremde Nextcloud-Laufzeit-App. Der
Adaptercode ist bewusst app-lokal in der Berechtigungsmatrix und wird weder in
LocalBase noch in eine gemeinsame Bibliothek verschoben. Die offizielle App
stellt für die benötigte vollständige Ordnerliste derzeit keinen öffentlichen
OCP-Vertrag bereit. Deshalb ist der Zugriff auf
`OCA\GroupFolders\Folder\FolderManager::getAllFolders()` eine eng begrenzte,
read-only Ausnahme und kein allgemein freigegebener Cross-App-Zugriff.

Die Ausnahme gilt nur gemeinsam mit diesen Schutzgrenzen:

- ausschließlich Groupfolders 22.x auf Nextcloud 34;
- keine fremden Tabellen, Controller, Reflection, AppConfig-, Datei- oder
  Migrator-Fallbacks und keinerlei Schreiboperation;
- nur pseudonyme Ordnerreferenz, Nextcloud-Gruppen-ID, Root-Permission-Maske
  und ACL-Vollständigkeitsstatus verlassen den Adapter; Mount-Pfade,
  Datei-/Ordnernamen und Dateiinhalte bleiben ausgeschlossen;
- erweiterte ACLs, Team-/Circle-Zuordnungen, die historische App-ID
  `files_groupfolders`, abweichende Versionen und Quellfehler führen sichtbar
  zu `UNKNOWN` beziehungsweise `PARTIAL`;
- vor jedem Release mit diesem Adapter prüft
  `filzmann_permission_matrix/scripts/check-groupfolders-source-compatibility`
  einen
  frischen offiziellen Groupfolders-Checkout. Änderungen an Version,
  Ziel-Nextcloud, DTO-Feldern oder Methode blockieren die Freigabe, bis
  Adapter, Tests und Gate bewusst gemeinsam aktualisiert wurden.

Damit wird die vom fremden Projekt gewählte Vorgehensweise bei künftigen
Releases reproduzierbar erneut geprüft. Das Gate lädt selbst keinen Quellcode
und erhält den zu prüfenden offiziellen Checkout als expliziten Parameter.

## Navigation und aufgehobene BR-Kopplung

Die frühere OrgSuite-Kopplung und `data-suite="br"` waren ein historischer
Ist-Zustand, nicht die Zielklassifikation. Sie wurden am 23. August 2026 aus
`filzmann_permission_matrix` und OrgSuite entfernt. Die Matrix registriert
einen eigenen Nextcloud-Navigationseintrag und prüft dessen Sichtbarkeit über
ihren bestehenden serverseitigen `AccessService`. OrgSuite führt die Matrix nicht
mehr als BR-Ziel oder Weiterleitungsziel.

Das Ziel ist:

1. Die App ist auch ohne OrgSuite erreichbar und bedienbar.
2. Ein neutraler eigener Einstieg oder eine native Nextcloud-Einstellungs-
   beziehungsweise Navigationsfläche trägt die Domäne IKT/Datenschutz.
3. Ist eine übergreifende Privacy-/Governance-Navigation aktiv, darf sie einen
   kleinen optionalen Linkadapter anbieten, aber keine Matrix-Assets,
   Controller oder Rechte übernehmen.
4. Menüsichtbarkeit bleibt ohne Berechtigungswirkung.

Die konkrete Navigationsform ist damit als eigener Nextcloud-Einstieg
umgesetzt. Eine zweite unabhängig gepflegte Linkliste entsteht nicht.

## Technische Identität und öffentliche Eignung

Die frühere App-ID `br_permission_matrix` und der frühere PHP-Namespace
enthielten eine historische BR-Zuordnung. Am 23. August 2026 wurde die
öffentliche Zielidentität `filzmann_permission_matrix` umgesetzt. Der
Stagingbestand ist ausdrücklich nicht erhaltenswert; Produktions- oder
veröffentlichte Store-Bestände sind nicht bekannt. Eine Datenübernahme fand
nicht statt und der Stagingbestand wurde durch diesen Lauf nicht verändert.

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

Die Ausgangsbeispiele `Betriebsrat`, `IKT-Ausschuss`,
`Datenschutzbeauftragte` und `IT-Administration` bleiben erhalten, sind aber
vollständig anpassbar und keine vorausgesetzten Organisationsrollen. Im
Normalfall erhält die zuständige Beschäftigtenvertretung Leserechte: je nach
Organisation ein Betriebsrat oder Personalrat. IKT-Ausschuss und
Datenschutzbeauftragte sind sinnvolle weitere lesende Rollen für technische
Prüfung und datenschutzrechtliche Beratung. Administrative Konfigurations-,
Scan- und Baseline-Rechte bleiben auf eine kleine zuständige Gruppe wie
`IT-Administration` und native Nextcloud-Administratoren begrenzt. Die
Portfoliozuordnung allein erteilt niemandem Zugriff.

## Migrationsplan und Gates

1. **Root-Planung:** diese Produktgrenze, Providerpriorität und offenen
   Entscheidungen dokumentieren; kein App-Code.
2. **App-Auftrag:** aktuelles Navigations-, OrgSuite-, Daten-, Konfigurations-
   und Releaseinventar gegen den dann aktuellen Code bestätigen.
3. **Identitätsgate:** Ziel-ID `filzmann_permission_matrix`, fehlender
   Erhaltungsbedarf und Referenzen bestätigt; umgesetzt.
4. **Standalone-Navigation:** Erreichbarkeit ohne OrgSuite test-first
   hergestellt; vorhandene Rechte blieben unverändert.
5. **Privacy-Provider:** personenbezogene Snapshot-, Export- und Auditbezüge
   vollständig inventarisiert und subjectgebundenen Provider implementiert;
   optionale Benutzerlisten bleiben im Standardmodus deaktiviert und werden
   nicht als eigene Persistenz der Matrix ausgegeben.
6. **Retention:** technische Fristen und read-only Preview umgesetzt;
   Beweiswert, gesetzliche/fachliche Aufbewahrung und konkrete Maßnahmen
   bleiben vor einem Ausführungspfad zu entscheiden.
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
- Die historische ID `br_permission_matrix` bleibt nur zur Dokumentation des
  nicht migrierten Vorgängerstands erhalten.
- Noch zu entscheiden sind das öffentliche Store-Ziel und der konkrete
  optionale Portfolioadapter.
- Die Art.-15-Providerlücke ist geschlossen. Fachliche Retention- und
  Maßnahmenentscheidungen bleiben sichtbar und werden nicht durch die bloße
  thematische Zuordnung als gelöst dargestellt.
