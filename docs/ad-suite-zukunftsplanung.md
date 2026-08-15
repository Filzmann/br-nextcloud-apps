# AD-Suite-Zukunftsplanung: Fachmodule und suiteweite Erweiterungen

Stand: 15. August 2026

Status: **VORGEMERKT – NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN**, sofern ein
Abschnitt nicht ausdrücklich durch einen datierten Entscheidungsbericht
freigegeben wurde.

## Zweck und Verbindlichkeitsgrenze

Diese Datei ist die kanonische Parent-Quelle für die fachliche und
architektonische Vormerkung der hier beschriebenen künftigen AD-Suite-Module
und suiteweiten Erweiterungen.
Sie ist weder eine freigegebene Umsetzungsaufgabe noch ein Scaffold-, Release-
oder Migrationsauftrag.

Insbesondere werden aufgrund dieser Planung nicht angelegt oder geändert:

- App-Repositories, `appinfo.xml`, Routen, Controller, Services oder UI;
- Datenbanktabellen, Migrationen, produktive Daten oder Dateispeicher;
- bestehende Fachapps, LocalBase-Verträge oder der Produktkatalog;
- Repositoryinventar, Installer, Releasearchive oder App-Store-Metadaten; und
- Tests für noch nicht freigegebene Apps.

Eine Umsetzung beginnt ausschließlich nach den Freigaben im Abschnitt
[`Freigabepunkte`](#freigabepunkte). Neue Apps werden danach einzeln mit dem
Skill `create-nextcloud-app` angelegt. Änderungen bestehender Apps oder
öffentlicher Verträge benötigen zusätzlich eine ausdrückliche Schreibfreigabe
für jedes betroffene Repository.

Normative, bereits geltende Regeln werden hier nicht dupliziert:

- Repository- und Arbeitsgrenzen: [`../AGENTS.md`](../AGENTS.md)
- app-übergreifende Architektur: [`architecture.md`](architecture.md)
- Shared-Code- und Laufzeitklassifikation:
  [`architecture-decisions/0001-shared-code-runtime-and-app-store.md`](architecture-decisions/0001-shared-code-runtime-and-app-store.md)
- Datenschutz-, Provider- und Retentionarchitektur:
  [`privacy-architecture.md`](privacy-architecture.md)
- ausschließlich freigegebene Parent-Aufgaben:
  [`implementation-tasks.md`](implementation-tasks.md)

## Leitbild und Schutzgrenzen

Die spätere AD Suite soll Personalbedarf, DPA-Wiederanbindung,
Recruiting/Basisqualifikation, offene Schichten und bestehende Ausfall- und
Kapazitätsprozesse nachvollziehbar verbinden. Ziel sind weniger
Informationsverlust und bessere Prozesssteuerung, nicht mehr Druck, Scoring
oder zusätzliche Verfügbarkeitspflichten.

Der DPA ist kein kurzfristiger Springerpool. Sein Hauptzweck bleibt die
mittelfristige, tragfähige Wiederanbindung unterdeckter Beschäftigter an
feste Teams. Die Prüfung einer DPA-Option für eine konkrete Akutschicht ist
ein begrenzter Integrationsfall der getrennten Schichtvermittlung.

Nicht vorgesehen sind insbesondere:

- Vermittelbarkeits-, Kooperations- oder Leistungs-Scores;
- Rankings, Ablehnungsquoten oder verdeckte Blacklists;
- automatische Kürzungs-, Sanktions-, Einstellungs- oder Teamentscheidungen;
- pauschale Personenmerkmale wie `schwer vermittelbar`;
- individuelle Krankheits- oder Ausfallprognosen;
- Diagnosen, BEM-Protokolle oder medizinische Begründungen;
- automatische Erweiterungen von Abruf-, Kapazitäts- oder
  Verfügbarkeitspflichten; und
- eine Vermischung von DPA-, Ausfallgeld- oder Kapazitätsstunden mit
  nachzuleistenden AZK-Minusstunden.

## Ausdrückliche Annahmen

- `AS` bezeichnet Assistenzbeschäftigte, `ASN` Assistenznehmer*innen und
  `EB` Einsatzbegleitungen.
- Für DPA, Ausfallgeld und Kapazitätslisten ist im aktuellen Workspace keine
  belastbare digitale führende Quelle nachgewiesen.
- AdPlaner bildet Wunschdienstplanung ab, aber keinen vollständigen
  Akutvermittlungsprozess.
- Die Ausfallgeldangaben `zwei Tage` und `einen Tag vorher` sind hinsichtlich
  Kalender- oder Arbeitstagen und genauer Fristberechnung noch zu präzisieren.
- Eine führende Quelle für Vertragsstunden, Beschäftigungsstatus,
  Qualifikationen, Austritte und Beschäftigungsende ist noch nicht bestimmt.
- Fachliche Eignung bedeutet ausschließlich Passung zu vorab freigegebenen
  sachlichen Anforderungen und erzeugt keinen Personenwert.

## Verifizierter Bestand und bestehende Ownership

| Repository | Verifizierte Berührungspunkte | Harte Abgrenzung |
| --- | --- | --- |
| `localbase` | Organisationsrollen, Bereiche, Hierarchie, Assistenzteamkonvention, Kalenderkontext, Feiertage und optionale Event-/Capability-Verträge | Keine DPA-, Personalbedarfs-, Schichtvermittlungs- oder Personalfalldaten; LocalBase wird kein Fachmonolith |
| `orgsuite` | AD-Navigation und Adminadapter | Keine Fachdaten und keine fachliche Berechtigungserweiterung |
| `adplaner` | Assistenzteams, variable Schichtdefinitionen, monatliche Schichtslots, Wünsche/Zuweisungen, Tagesnotizen und Monatsplanstatus | Kein Akutvermittlungs-Suchbogen, kein DPA-Fall und kein Ausfallgeldprozess; bestehende `shift_candidates` sind Planwünsche/Zuweisungen und keine Vermittlungsrangliste |
| `adcalendar` | Büro-Dienste und Termine je Nextcloud-UID sowie Dienstanzahl und Stunden im sichtbaren Zeitraum | Assistent*innen gehören ausdrücklich nicht in diese App; keine offenen Schichten oder Personalkapazitätsplanung |
| `adurlaub` | Geplante und genehmigte Urlaube sowie optionale Konfliktmeldungen | Abwesenheitssignal, aber keine Kapazitäts-, DPA- oder Forecastquelle; Notizen bleiben app-intern |
| `adroom` | Raumbelegungen; `BQ` ist lediglich ein möglicher Buchungszweck | Keine BQ-, Recruiting- oder Personalbedarfsplanung |
| `adrecruitment` | Stellen, Bewerbungen, gewünschte Wochenstunden, Recruitingpipeline, BQ-Durchläufe/-Zuordnungen, Bewertung und Einstellungsfreigabe | Kein rollierender betrieblicher Personalbedarfsforecast und keine DPA-Fälle |
| `ad-suite` | Öffentliche Produkt-, Installations- und Releaseübersicht | Keine Quelle für interne, nicht freigegebene Fachplanung |

Im untersuchten Stand wurden keine DPA-Fallsteuerung, kein digitaler
Ausfallgeldprozess, kein sequenzieller Suchbogen für offene Schichten, kein
rollierender 8–12-Wochen-Personalforecast und keine fachliche Excel-/CSV-
Importstrecke für diese Prozesse gefunden. Vorhandene Exporte und andere
idempotente Importmuster belegen keine fachliche Umsetzung dieser Module.

## Roadmap: vorgemerkt und nicht freigegeben

| Status | Modul | Fachlicher Scope | Expliziter Nicht-Scope | mögliche App-ID |
| --- | --- | --- | --- | --- |
| **VORGEMERKT – NICHT FREIGEGEBEN** | DPA-App | DPA-Fälle, Stundenbild, sachliche Einsatzbedingungen, Zuständigkeit, Wiedervorlage, Teamoptionen, Kennenlernen, Einarbeitung, Ergebnis, Verlauf, Abschluss und aggregierte Prozessauswertung | Akut-Springerpool, BEM-/Gesundheitsdaten, Personenranking, Kürzungs-/Sanktionsvorschläge und automatische Teamzuordnung | `addpa` |
| **VORGEMERKT – NICHT FREIGEGEBEN** | Schichtvermittlungs-App | Konkrete offene Schicht, sequenzieller Suchbogen, Kontakte, Ergebnisse, Stufenübergänge, Sonderzuschlag, Besetzungsstatus und aggregierte Strukturhinweise | Wunschdienstplanung, dauerhafte DPA-Wiederanbindung, automatische Kandidatenauswahl, Ablehnungsbewertung und Ausweitung von Meldepflichten | `adschichtvermittlung` |
| **VORGEMERKT – NICHT FREIGEGEBEN** | Personalbedarfs-/Forecast-Modul | Rollierende Bedarfskorridore, Checkpoints, Szenarien und Forecast-vs-Ist | Bewerbungsakte, individuelle Krankheitsprognose und automatische Einstellungsentscheidung | etwa `adpersonalbedarf`; erst nach Ownership-Entscheidung |
| **VORGEMERKT – NICHT FREIGEGEBEN** | Ausfallgeld-/Kapazitätsmodul | Nur falls der heutige Tabellenprozess später eine eigene führende digitale Quelle benötigt | DPA-Fallsteuerung und Suchbogen offener Schichten | derzeit nicht entscheidbar |
| **FREIGEGEBEN – ERSTER KERN IMPLEMENTIERT, FOLGEPAKETE GEGATET** | BQ-Planungs-App | Durchlaufprogramm, Kapazität, einzelne Unterrichtstermine, Ressourcen, Warteliste und Anwesenheit | Bewerbungsakte, Eignungsentscheidung und automatische Einstellungsfreigabe | `adbqplanung`; Freigabe und Schnitt in `plans/bq-planer-start-2026-08-15.md` |
| **VORGEMERKT – NICHT FREIGEGEBEN** | Aggregiertes Reporting | Datenschutzgeprüfte appübergreifende Kennzahlen und Strukturhinweise | Operative Fremddatenbankabfragen oder Personen-Dashboards | zunächst kein eigenes Modul festlegen |
| **VORGEMERKT – NICHT FREIGEGEBEN** | Appübergreifender L10N-Rollout | Sichtbare Texte aller bestehenden und künftigen BR-/AD-Apps vollständig über aktive Nextcloud-Locale und Nextcloud-l10n ausgeben | Änderung technischer IDs, API-Schlüssel, Statuswerte, ISO-Daten, Monatsnummern, Schichtzeiten oder fachlicher Semantik | kein eigenes Modul; appweise Umsetzung nach Pilotentscheidung |

Die bestehende Namenskonvention ist `ad` plus kleingeschriebener Fachbegriff
ohne Trennzeichen. Die genannten IDs sind Vorschläge, keine Reservierungen.

### Laufzeit- und Shared-Code-Klassifikation

| Bestandteil | Aktueller Owner/Consumer | Evidenzstatus | A/B/C | Ziel und Begründung | Risiko/Storewirkung | Priorität |
| --- | --- | --- | --- | --- | --- | --- |
| DPA-Fallsteuerung | noch kein Owner; DPA, Vermittlung und Forecast wären Consumer | fachlicher Bedarf belegt, technische Quelle fehlt | B | Eigenständige App wegen führender Fachdaten, Zuständen, Rechten, Retention und Administration | hoch: personenbezogene Fälle, Standalone- und Deinstallationsvertrag | 1 |
| Schichtvermittlung | noch kein Owner; Teamplanung, DPA und Kapazitätsquellen wären Provider | fachlicher Bedarf belegt, technische Quelle fehlt | B | Eigenständige App wegen eigener offener Schicht, Suchhistorie, Audit und Rechte | hoch: Personenbezug, Nebenläufigkeit, optionale Provider | 1 |
| Persistenter Personalbedarfsforecast | noch kein Owner; Recruitment, DPA und Strukturaggregate wären Provider | plausible Einordnung | B, falls persistiert | Eigenständiger fachlicher Dienst, nicht Teil der Bewerbungsakte | hoch: Scheingenauigkeit, Fehlsteuerung und Datenschutz | 2 |
| BQ-Durchlaufprogramm und Ressourcenplanung | `adbqplanung`; AD Recruitment verwaltet weiterhin den manuellen Recruiting-Fallback | App-Grenze freigegeben und erster Planungskern implementiert; Persistenz und Integration offen | B | Eigenständige App wegen Kapazitäten, Einzelterminen, Ressourcen, Anwesenheit und eigener Zustände | hoch: Teilnehmerbezug, Kalenderkonflikte, Standalone- und Integrationsvertrag | 1 |
| App-spezifische Tabellenimporte | jeweilige künftige Fachdaten-App | Formate noch unbekannt | C, zunächst | Importprofil bleibt beim Datenowner; keine universelle Personaldaten-Excel-Schicht | mittel: Quellqualität und Korrekturen | 2 |
| Reine DTOs/Serialisierungsschemata | spätere Provider und Consumer | noch zu prüfen | A möglich | Nur bei mindestens zwei semantisch identischen Verwendungen und reproduzierbarem Build | mittel: Versions- und Namespaceisolation | 3 |
| In-Process-Events/Capabilities | spätere Provider und Consumer | noch zu prüfen | B-Vertrag | Benötigen eine kontrollierte gemeinsame Klassenidentität und verständliches Missing-Provider-Verhalten | hoch: Laufzeitkopplung und Store-Voraussetzung | 2 |
| App-lokale Reports | jeweilige Fachapp | plausible Einordnung | C | Bleiben lokal, solange Semantik und Änderungsgrund verschieden sind | niedrig | 3 |
| Persistiertes Cross-App-Reporting | noch kein Owner | derzeit nicht entscheidbar | B nur bei realem Bedarf | Keine Reporting-App allein wegen ähnlich aussehender Diagramme | hoch: Zweitkopien und Rückauflösbarkeit | 4 |

Neue Apps erhalten keine automatische LocalBase-Abhängigkeit. Ein öffentlicher
Vertrag braucht vor Umsetzung eine eigene Provider-/Consumer-, Versions-,
Installations-, Update-, Deinstallations- und Store-Entscheidung.

## Daten-Ownership und zentrale Referenzen

| Daten/Entität | Führender Besitzer | Stabile Referenz |
| --- | --- | --- |
| DPA-Fall, Stundenbild, Wiedervorlage und Verlauf | DPA-App | eigene unveränderliche `dpa_case_id` |
| Teamoption, Kennenlernen und Einarbeitung | DPA-App | eigene `team_option_id` mit typisierter Teamreferenz |
| Konkrete offene Schicht | Schichtvermittlungs-App | eigene `open_shift_id`, optional typisierte `source_shift_ref` |
| Suchlauf, Stufe, Kontakt, Ergebnis und Übergangsgrund | Schichtvermittlungs-App | `search_run_id`, `stage_id`, `contact_id` |
| AdPlaner-Schichtslot und Teamkonfiguration | AdPlaner | bestehende lokale ID plus Teamcode |
| Büro-Dienst/Termin | AD Kalender | bestehende lokale Kalendereintrags-ID |
| Urlaub | AD Urlaub | bestehende lokale Urlaubs-ID |
| Bewerbung, Stelle, BQ-Durchlauf und BQ-Zuordnung | AD Recruitment | bestehende lokale Recruitment-IDs |
| BQ-Programm, Unterrichtstermin, Kapazität und Anwesenheit | `adbqplanung` | eigene stabile IDs; keine Umdeutung bestehender Recruitment-IDs |
| Organisationsrollen und -bereiche | bestehender Organisationsvertrag | stabile semantische Schlüssel |
| Nextcloud-Konto | Nextcloud | Nextcloud-UID; nicht mit Beschäftigtennummer gleichsetzen |
| Beschäftigten-/Vertragsstammdaten | noch unbekannte führende Quelle | vor Umsetzung klären; nicht in LocalBase erfinden |
| Bedarfskorridor, Szenario und Checkpoint | mögliches Forecast-Modul | eigene versionierte IDs |
| Importlauf und Herkunft | jeweils importierende Eigentümer-App | `import_batch_id`, Quelltyp, Hash, Tabellenblatt und Zeilenschlüssel |
| Reportingprojektion | jeweiliger Provider oder späteres Reporting-Modul | stabile Quellreferenz und Aggregatperiode |

Cross-App-Referenzen sind typisiert und bestehen mindestens aus
`provider_app_id`, `entity_type`, stabiler ID und Vertragsversion. Direkte
Fremdtabellen-FKs sind ausgeschlossen. Ein minimaler fachlicher Snapshot darf
für historischen Nachweis gespeichert werden, ersetzt aber nie die führende
Quelle.

## Konzeptionelle Schnittstellen

Die folgenden Namen sind Platzhalter, keine implementierten Verträge:

| Provider → Consumer | Minimaler Vertrag |
| --- | --- |
| Teamquelle → DPA/Schichtvermittlung/Forecast | `TeamSnapshot` mit stabiler Teamreferenz, Status, Qualität und zulässigen fachlichen Merkmalen |
| Schichtquelle → Schichtvermittlung | `OpenShiftReported` mit Quellreferenz, Zeitraum, Team und sachlichen Anforderungen |
| DPA → Schichtvermittlung | Autorisierte Query nach konkret passender Akutoption; keine vollständige DPA-Liste und kein Ranking |
| Kapazitäts-/Ausfallgeldquelle → Schichtvermittlung | Minimale Zeitfenster, Prozessstatus und erfüllter Kontaktstatus; Prozesse bleiben getrennt |
| Schichtvermittlung → DPA/Forecast | Aggregierter `StructuralGapObserved`; keine Ablehnungsprofile |
| DPA → Forecast | Manuell bestätigte, aggregierte und realistisch passende Stundenkorridore; keine pauschale Fallzahl |
| AD Recruitment → Forecast | Aggregierte Pipeline nach BQ/Status/Stundenkorridor sowie verbindlich eingestellte Zugänge |
| Forecast → Recruitment/BQ | Freigegebener Bedarfskorridor und konfigurierbare Zielkapazität; keine automatische Einstellungsentscheidung |
| BQ-Planung ↔ AD Recruitment | Stabile externe Durchlaufreferenz sowie notwendige Ereignisse für Bestätigung, Umbuchung, Abbruch, Nichtantritt und Abschluss; keine Bewerbungsakte und keine automatische Eignungsentscheidung |
| Fachapps → Reporting | Datensparsame Aggregatprojektionen mit Perioden-, Qualitäts- und Vollständigkeitsstatus |

Ein fehlender, deaktivierter oder inkompatibler Provider bleibt ein
kontrollierter Stand. Er erweitert keine Rechte und darf nicht zu einem
unkontrollierten Laufzeitfehler führen.

## Soll-Prozess der Schichtvermittlung

1. Offene Schicht mit Team, Zeitraum, Quellreferenz und sachlichen
   Mindestanforderungen erfassen.
2. Doppelanlagen über Quellreferenz oder fachlichen Idempotenzschlüssel
   verhindern.
3. Stufe **Team** öffnen, geeigneten Personenkreis fachlich prüfen sowie
   Kontakte und Ergebnisse dokumentieren.
4. Nur mit dokumentiertem sachlichem Übergangsgrund Stufe **Vertretung**
   öffnen.
5. Danach **DPA** als ergänzende Akutoption prüfen; der DPA-Hauptzweck bleibt
   unverändert.
6. Danach getrennt **Ausfallgeld / vorhandene Kapazitätslisten** prüfen.
   DPA-Kapazität und Ausfallgeldstatus werden nicht zu einem gemeinsamen
   Stundenkonto verrechnet.
7. Danach **sonstige AS** prüfen.
8. Einen Sonderzuschlag gegebenenfalls als sachliches Merkmal des konkreten
   Vermittlungsvorgangs erfassen.
9. Mit `besetzt`, `unbesetzt`, `entfallen` oder `abgebrochen` abschließen.
10. Wiederkehrende Muster ausschließlich aggregiert nach Team, Zeitraum und
    Schichtart als Strukturhinweis bereitstellen.

Jede Stufe benötigt Zeitpunkt, prüfende Rolle, betrachteten fachlichen Scope,
Kontakte, Ergebnis und Übergangsgrund. Parallele Bearbeitung muss durch
Versionierung oder Sperren vor stillen Überschreibungen geschützt werden.

## Soll-Prozess der DPA-App

1. Fall aufgrund belegter Unterdeckung eröffnen.
2. Abgesicherte Vertragsstunden, bereits geplante Stunden und Zielkorridor
   für feste Teamstunden getrennt erfassen.
3. Nur erforderliche arbeitsorganisatorische Einsatzbedingungen
   dokumentieren; BEM, Diagnose und sensible Begründung bleiben außerhalb.
4. Zuständige Funktion, nächsten Schritt und Wiedervorlage bestimmen.
5. Neue oder frei werdende Teamstunden als Prüftrigger empfangen.
6. Teamoption fachlich prüfen und mit Passungsvoraussetzungen dokumentieren.
7. Kennenlernen und gegebenenfalls Einarbeitung als eigene Zustände planen
   und auswerten.
8. Ergebnis sachlich festhalten: geeignet, nicht geeignet, zurückgestellt,
   abgebrochen oder weitere Klärung; daraus entsteht kein Personenwert.
9. Bei teilweiser Wiederanbindung Stundenbild und Ziel fortschreiben.
10. Nach stabiler Wiederanbindung abschließen; Wiederöffnung ist ein eigener
    nachvollziehbarer Übergang.

`Objektiv nicht passend` und `trotz konkret geeigneter Option keine
Mitwirkung` bleiben getrennte Sachverhalte. Keiner erzeugt automatisch
Sanktion, Kürzung, Score oder dauerhaftes Personenetikett.

## Rollierender Recruiting-/BQ-Prozess

| Zeitpunkt | Inhalt |
| --- | --- |
| T−12 bis T−8 Wochen | Erster Bedarfskorridor aus erwarteten Teamstunden, Wachstum, Austritten/Reduzierungen, Fluktuationsband, strukturellen Lücken, saisonaler Reserve, bereits eingestellten AS, realistischer DPA-Passung und Recruitingpipeline |
| T−6 bis T−4 Wochen | Aktualisierung mit neu bestätigten Team-/ASN-/Fluktuationsdaten, Schichtstrukturhinweisen, DPA-Optionen und Pipelinebewegungen |
| vor BQ | Fachlich freigegebene Feinsteuerung der Teilnehmerzahl; die derzeitige Größe 6 ist ein konfigurierbarer Parameter, kein Codewert |
| nach BQ | Tatsächliche Teilnahme, Ergebnis und Einstellungsfreigaben aggregiert zurückmelden |
| nach späterer DPA-Runde | Tatsächlich wiedergewonnene feste Teamstunden und nicht realisierte Annahmen zurückmelden |
| nächste Planung | Abweichungsursachen auswerten und Korridorannahmen versioniert anpassen |

Jeder Korridor besitzt mindestens Unter-, Arbeits- und Obergrenze. Saisonale
Krankenstandsreserven dürfen ausschließlich aus ausreichend großen
historischen Aggregaten entstehen. Kleine Zellen, individuelle Verläufe und
Gesundheitsdaten sind ausgeschlossen.

Es gibt keinen Abzug `Anzahl DPA-Fälle`. Berücksichtigt werden nur manuell
bestätigte, für Prognosezeitraum und Bedarf realistisch passende
Stundenkorridore. Bereits eingestellte AS werden nicht systematisch gegenüber
DPA-Beschäftigten nachrangig behandelt.

## BQ-Planungs-App: Analyse und Zielplan

### Freigabestand vom 15. August 2026

Die eigenständige Kategorie-B-App `adbqplanung` und ihr erster
dependency-armer Planungskern sind freigegeben und angelegt. Verbindlich sind
der Standard von sieben konfigurierbaren Arbeitstagen, der konfigurierbare
Starttag mit Freitag als Standard, erklärbare Vorschläge unter Berücksichtigung
übergebener Sperrperioden, Curriculum-Snapshots, eine interne Haupt-PFK mit
Modulabweichungen sowie Praxisreflexionen nach einem, drei und vier Monaten.

Noch nicht freigegeben oder implementiert sind persistente Tabellen, das
granulare Rollenmodell, produktive Kalender- und Recruitment-Verträge,
Teilnehmerinnen, Anwesenheit und Kommunikation. Der vollständige erste Schnitt
und seine Rückbaugrenze stehen im datierten Bericht
`plans/bq-planer-start-2026-08-15.md`.

### Verifizierter Altstand

Der außerhalb dieses Workspaces untersuchte WordPress-Altversuch
`flz_ad_basisqualifikation` ist keine übernehmbare Planungsanwendung, sondern
eine frühe Terminliste mit Titel, Startdatum, Enddatum und anlegender
WordPress-Benutzer-ID. Die Oberfläche kann Durchläufe anlegen, ändern, löschen
und tabellarisch anzeigen.

Ein Vorschlagsalgorithmus sucht einen Freitag in der zweiten oder dritten
Monatswoche, legt ein siebentägiges Zeitfenster an und versucht, Berliner
Schulferien und Feiertage zu vermeiden. Der Titel wird als
`BQ YYYY-<römischer Monat>` gebildet. „Abgeschlossen“ bedeutet lediglich,
dass das Enddatum in der Vergangenheit liegt.

Nicht vorhanden sind Kapazitäten, Teilnehmer*innen, Wartelisten, einzelne
Unterrichtstage, Inhalte, Lehrende, Räume, Anwesenheit, Konflikte, belastbare
fachliche Zustände, Umbuchungen, Absagen, Benachrichtigungen und eine
verbindliche Verbindung zum Bewerbungsprozess.

Nutzbar bleiben nur die Produktideen eines monatsbezogenen Terminvorschlags,
nachvollziehbarer Ferien-, Feiertags- und Sperrzeitwarnungen, manuell
änderbarer Vorschläge und einer kompakten Liste kommender und vergangener
Durchläufe.

Der Altcode wird nicht übernommen. Er enthält unter anderem öffentliche
`nopriv`-AJAX-Registrierungen, uneinheitliche Capability-Namen, automatisch an
alle Rollen vergebene Rechte, SQL-Stringbildung, beim Deaktivieren gelöschte
Tabellen, fest eingebaute und seit 2025 veraltete Feriendaten sowie einen rein
datumsabhängigen Abschlussstatus.

Auch die fachlichen Altannahmen werden nicht still übernommen: AD Recruitment
verwendet derzeit `BQ MM/YY` und ungefähr zehntägige Durchläufe, nicht römische
Monatszahlen und starre sieben Tage. Kalenderregeln erzeugen deshalb zunächst
nur Vorschläge. Verbindliche Dauer, Wochentage und Sperrzeiten benötigen eine
fachliche Entscheidung.

### Empfohlene Produktgrenze

AD Recruitment bleibt die kanonische Quelle für Bewerbung, BQ-Zuordnung,
Auswahlergebnis und daraus folgende Einstellungsfreigabe. Die eigenständige
BQ-Planungs-App ist die kanonische Quelle für
Durchlaufprogramm, Kapazität, Unterrichtstermine, Ressourcen und Anwesenheit.

Die Verbindung erfolgt ausschließlich über einen kleinen versionierten
Capability-/Event-Vertrag. AD Recruitment speichert dabei nur eine stabile
externe Durchlauf-ID und den für den Bewerbungsprozess notwendigen Snapshot.
Fehlt die BQ-App, bleibt die vorhandene manuelle Durchlaufverwaltung
vollständig nutzbar. Direkte Zugriffe auf Tabellen, Controller oder Assets der
jeweils anderen App sind ausgeschlossen.

### Zielmodell

Ein BQ-Durchlauf besitzt mindestens:

- stabile ID, sichtbare Bezeichnung und Planungsjahr;
- Zeitraum, Zeitzone, Kapazität und optional Wartelistenkapazität;
- Zustand `Entwurf`, `veröffentlicht`, `bestätigt`, `laufend`, `abgeschlossen`
  oder `abgesagt`;
- einzelne Termine mit Beginn, Ende, Thema, verantwortlicher Person und
  optionaler Raumreferenz; sowie
- versionierte Änderungen und einen nachvollziehbaren Verlauf.

Eine Teilnahme besitzt eine stabile Referenz zur Bewerbung beziehungsweise
Person, aber keine Kopie der vollständigen Bewerbungsakte. Ihr Zustand ist
`vorgemerkt`, `bestätigt`, `Warteliste`, `abgesagt`, `nicht angetreten`,
`teilgenommen` oder `abgebrochen`. Das Recruitment-Ergebnis `geeignet` oder
`nicht geeignet` bleibt davon getrennt und wird weiterhin ausdrücklich durch
das Personalreferat gesetzt.

### Umsetzungspakete in empfohlener Reihenfolge

#### BQ-01 – Fachentscheidungen und Vertrag – teilweise umgesetzt

Vor jeder Schema- oder App-Entscheidung werden verbindlich geklärt:

- typische und minimale/maximale Dauer sowie reguläre Unterrichtstage;
- Berliner Ferien/Feiertage als harte Sperre, Warnung oder nur Präferenz;
- Kapazität, Überbuchung und Wartelistenverfahren;
- Rollen für Planung, Lehre, Anwesenheit und Ergebnisfreigabe;
- erforderliche Teilnehmerdaten und deren Aufbewahrung; und
- ob der erste Ausbau lokal in AD Recruitment bleibt oder eine neue,
  separat versionierte App ausdrücklich beauftragt wird.

Ergebnis ist ein freigegebener Zustands- und Berechtigungsvertrag mit
zulässigen Übergängen, Nebenwirkungen, Konflikten und Rückbaugrenze.

#### BQ-02 – Durchlauf- und Terminplanung – Planungskern begonnen

- Jahres- und Listenansicht mit Entwürfen und veröffentlichten Durchläufen.
- Manuelles Anlegen sowie erklärbarer Terminvorschlag auf Basis einer
  austauschbaren Kalenderquelle.
- Einzeltermine, Pausen/Sperrzeiten, Kapazität und optimistische Sperren.
- Warnungen für Überlappungen, Ferien, Feiertage, fehlende Termine und
  unplausible Dauer; keine stille automatische Verschiebung.
- Absage statt Löschen, sobald ein Durchlauf veröffentlicht oder referenziert
  wurde.

#### BQ-03 – Teilnehmerplanung und Umbuchung

- Vormerken, bestätigen, auf Warteliste setzen, absagen und kontrolliert in
  einen anderen Durchlauf verschieben.
- Kapazitätskonflikte atomar behandeln und wiederholte Requests idempotent
  machen.
- Umbuchungen erhalten den Verlauf und lösen keine automatische
  Einstellungsfreigabe aus.
- In AD Recruitment die bereits offene Verschiebung zwischen Durchläufen
  zuerst lokal vervollständigen; sie bildet zugleich den Fallback für eine
  spätere Integration.

#### BQ-04 – Ressourcen und Tagesprogramm

- Themen beziehungsweise Module je Termin, verantwortliche Lehrende und
  interne Hinweise planen.
- Räume und Kalender nur optional über kleine Provider-Verträge anbinden;
  ohne `adroom` oder `adcalendar` bleiben lokale Freitextangaben möglich.
- Ressourcen- und Zeitkonflikte vor Veröffentlichung sichtbar machen.

#### BQ-05 – Anwesenheit, Abschluss und Recruitment-Rückmeldung

- Anwesenheit datensparsam pro Termin erfassen und korrigierbar auditieren.
- Teilnahmeabschluss und fachliches Recruitment-Ergebnis getrennt halten.
- Nur notwendige Ereignisse oder Snapshots an AD Recruitment liefern:
  bestätigt, umgebucht, abgebrochen, nicht angetreten und abgeschlossen.
- Eignungsentscheidung und Einstellungsfreigabe bleiben bewusste, getrennte
  Personalaktionen.

#### BQ-06 – Kommunikation und Betrieb

- Terminbestätigung, Änderung und Absage über versionierte Vorlagen und eine
  idempotente Outbox vorbereiten; Testumleitung und zeitliche Planung nach dem
  Recruitment-Muster verwenden.
- Datenschutz-Auskunft, Retention und Drittpersonenbezug vor fachlicher
  Fertigstellung ergänzen.
- Import-, Export-, Backup- und Wiederanlaufpfade erst nach konkretem Bedarf
  festlegen.

### Nachweis und Freigabegates

Jedes Paket wird testgetrieben umgesetzt. Zustands- und Rechteänderungen
belegen erlaubte und verweigerte Fälle einschließlich ausbleibender
Nebenwirkungen. Für persistente Änderungen sind additive Migration,
Neuinstallation, Upgrade mit synthetischen Bestandsdaten, Wiederholbarkeit und
Integrität nachzuweisen. Kalenderlogik wird mit einer injizierten Uhr und
versionierten Kalenderdaten getestet.

Die neue eigenständige App ist durch den Entscheidungsbericht vom 15. August
2026 freigegeben. Ein Datenbankschema, ein Berechtigungsmodell oder ein
öffentlicher Cross-App-Vertrag beginnt weiterhin erst nach der jeweils
ausdrücklich erforderlichen Freigabe. Der Altversuch ist eine fachliche
Fundstelle, aber weder Migrationsquelle noch kompatible Laufzeitabhängigkeit.

## Rollen- und Berechtigungsmatrix

`L` bedeutet Lesen, `B` Bearbeiten, `A` nur Aggregat und `–` keinen Zugriff.

| Rolle | DPA-Fall | offene Schicht | Kontakte/Suchbogen | Forecast | Recruiting/BQ | Reporting |
| --- | --- | --- | --- | --- | --- | --- |
| DPA-Funktion | L/B | nur konkrete autorisierte DPA-Anfrage | eigene DPA-Rückmeldung | A | A, soweit erforderlich | A |
| EB/Teamverantwortung | nur zugewiesene Teamoption | L/B für eigene Teams | Team-/Vertretungsstufen | Bedarfsmeldung für eigene Teams | – | A eigenes Team |
| Vermittlung | minimale freigegebene DPA-Passung | L/B | L/B | A | – | A |
| Recruiting/Personalplanung | A | A | A-Strukturhinweise | L/B | L/B nach Recruitment-Rechten | A |
| Verwaltung/Lohn | nur erforderliches Stundenbild, falls freigegeben | Zuschlags-/Abrechnungsdaten ohne Fallnotizen | – | A | bestehende enge Vertragsstammdatensicht | A |
| BR | grundsätzlich A; Personenbezug nur nach eigener Rechts-/Rollenentscheidung | A | A | A | A | A |
| Beschäftigte | optional eigene Kapazitätsmeldungen und eigene transparente Daten | eigene Anfragen/Antworten | nur eigene Kontakte | – | – | – |
| technische App-Administration | Konfiguration, kein automatischer Fachzugriff | Konfiguration, kein automatischer Fachzugriff | – | Konfiguration | – | technische Statusdaten |
| Nextcloud-Administration | kein automatischer fachlicher Vollzugriff ohne ausdrückliche Entscheidung | ebenso | ebenso | ebenso | bestehender Vertrag gesondert prüfen | technische Administration |

Alle Schreibaktionen bleiben CSRF-geschützt, serverseitig autorisiert und
minimiert auditiert. Auditprotokolle enthalten keine Diagnosen, BEM-Inhalte
oder unnötigen Freitext.

## Excel-/CSV-Übergangsstrategie

1. Bestehende Dateien, Tabellenblätter, Spalten, Verantwortliche und
   Aktualisierungsrhythmen inventarisieren.
2. Pro Datenbesitzer ein eigenes Importprofil definieren; keine universelle
   `Personaldaten-Excel` schaffen.
3. CSV als erste kontrollierbare Austauschform bevorzugen; XLSX erst nach
   Format-, Lizenz- und Produktionsdependency-Entscheidung.
4. Vor jedem Import einen Dry Run mit Spalten-, Typ-, Pflichtfeld-, Zeitraum-,
   Referenz- und Dublettenprüfung liefern.
5. Herkunft speichern: Quellsystem, Dateihash, Blatt, Zeilenschlüssel,
   Importzeit, importierende UID und Profilversion.
6. Idempotenz über Quellkennung plus stabilen fachlichen Zeilenschlüssel
   sicherstellen.
7. Korrekturen als neue Importrevision behandeln und Fachentscheidungen nicht
   still überschreiben.
8. Ungültige oder widersprüchliche Zeilen in eine sichtbare Quarantäne
   stellen.
9. Export mit denselben stabilen IDs, Statuswerten und Herkunftsangaben
   ermöglichen.
10. Tabellen erst ablösen, wenn Nutzen, Vollständigkeit, Rückbau und
    fachliche Abnahme belegt sind.

DPA-Kapazität, Ausfallgeld, offene Schichten, Recruiting und Forecast erhalten
getrennte Profile und Ownership.

## Spätere Migrations- und Teststrategie

- Appübergreifende L10N wird appweise und testgetrieben ausgerollt. Vor dem Pilot
  werden Pilot-App, Reihenfolge, unterstützte Locales, Fallbackvertrag und
  Rohtext-Gate separat freigegeben.
- Pro App werden mindestens deutsche Ausgabe, eine weitere Locale, Fallback,
  Monats-/Jahresgrenzen, Pluralformen, Platzhalter und Escaping in PHP und
  JavaScript geprüft. Abkürzungen entstehen nicht durch Abschneiden.
- Technische IDs, API-Schlüssel, persistierte Statuswerte, ISO-Daten,
  Monatsnummern und Schichtzeiten bleiben von der Lokalisierung unberührt.
- Vor jeder Migration Datenwörterbuch, Altschema, Varianten,
  Transformationsregeln, Integritätsbedingungen und Rückbaugrenze festlegen.
- Zuerst Import-Staging und Dry Run, danach kontrollierte Übernahme.
- Fresh-Install- und Upgrade-Tests mit synthetischen Bestandsdaten.
- Importtests für Wiederholung, geänderte Datei, Dublette, fehlende Referenz,
  ungültige Zeit, Teilfehler und abgebrochenen Lauf.
- Provider- und Consumer-Contract-Tests für jede öffentliche Schnittstelle.
- Zustandsmodelltests für erlaubte und verbotene DPA- und
  Vermittlungsübergänge.
- Rechteprüfungen mit Allow, Deny und manipulierten direkten Requests.
- Datenschutztests für Datenminimierung, Ausschluss von BEM-/Gesundheitsdaten,
  Audit, Auskunft, Export und Retention.
- Nebenläufigkeitstests für doppelte Schichtöffnung, parallele Kontakte und
  konkurrierende Abschlüsse.
- Aggregationstests gegen kleine Zellen und Rückauflösbarkeit.
- Accessibility-, Tastatur-, Fokus-, Responsive- und Scroll-Smokes.
- Appweise Einführung mit getrenntem Rollback; kein Big-Bang und keine
  rückwirkende Änderung veröffentlichter Migrationen.

## Risiken und noch zu treffende Entscheidungen

- Führende Quelle und stabile ID für Teams, Beschäftigte, Vertragsstunden,
  Qualifikationen und Beschäftigungsstatus.
- Abgrenzung von Schichtslot, offener Schicht und bestätigtem Dienst.
- Fachliche Eignungskriterien, Ausschlüsse und verantwortliche
  Entscheidungsrolle.
- Datenschutzrechtliche Zulässigkeit der DPA-Personensicht in der
  Akutvermittlung.
- Bedeutung, Fristberechnung und Nachweis der zwei Ausfallgeldkontakte.
- Abgrenzung verpflichtender DPA-Kapazität von freiwilliger Mehrkapazität.
- Zuschlagsarten, Gültigkeit, Genehmigung und Abrechnungsübergabe.
- Korridorformel, Aggregationsschwellen und saisonale Reserve.
- Ownership des rollierenden Forecasts.
- Umgang mit bereits eingestellten, noch nicht fest angebundenen AS.
- DPA-Fallabschluss, Wiederöffnung, Archivierung und Retention.
- Aufbewahrungsfristen für Kontakte, Ablehnungen, Importquellen und Audits.
- Trennung von BEM/medizinischen Gründen und operativen Einsatzbedingungen.
- Standalone-/Store-Modell und versionierte Laufzeitverträge für neue Apps.
- Öffentliche Eignung der vorgeschlagenen App-Namen und IDs.
- Pilot-App, Rolloutreihenfolge, unterstützte Locales, Fallback und
  verbindliches Rohtext-Gate für die appübergreifende Lokalisierung.

## Priorisierter späterer Backlog

Jeder Eintrag bleibt bis zu einer getrennten Freigabe ohne
Umsetzungswirkung.

| Priorität | Eintrag |
| --- | --- |
| P0 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Fachbegriffe, Prozessverantwortung, Datenquellen und stabile IDs verbindlich entscheiden |
| P0 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Datenschutz-/BEM-Abgrenzung und zulässige DPA-Sicht der Vermittlung freigeben |
| P0 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** App-Grenzen, App-IDs, Standalone-/Store-Modell und öffentliche Verträge beschließen |
| P0 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Rollen-, Berechtigungs- und Auditmodell fachlich und datenschutzrechtlich abnehmen |
| P1 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** DPA-Zustands- und Stundenmodell spezifizieren |
| P1 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Suchbogen und Übergangsregeln der Schichtvermittlung spezifizieren |
| P1 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Ausfallgeld-/Kapazitätsregeln und freiwillige Mehrkapazität formal beschreiben |
| P1 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** 8–12-Wochen-Forecast, Korridore, Checkpoints und konfigurierbare BQ-Größe spezifizieren |
| P1 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Excel-/CSV-Dateninventar und Importprofile erstellen |
| P1 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Datenschutzklassen, Auskunft, Retention und Drittpersonenbezug je neuer App festlegen |
| P2 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Provider-/Consumer-Verträge mit Versionierung und Standalone-Fehlerfällen entwerfen |
| P2 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Aggregierte Strukturhinweise und Schutz vor kleinen oder rückauflösbaren Gruppen spezifizieren |
| P2 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Migrations-, Rollback-, Test- und Abnahmematrix erstellen |
| P2 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Appübergreifenden L10N-Piloten, App-Reihenfolge, Locales, Fallback und Rohtext-Gate festlegen |
| P3 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Erst nach separater Freigabe neue App-Repositories mit `create-nextcloud-app` anlegen |
| P3 | **NICHT FREIGEGEBEN – NICHT IMPLEMENTIEREN:** Erst danach eine Pilotimplementierung testgetrieben in genau einem neuen Repository beginnen |

## Dokumentenownership und RAG-/Suchstruktur

### Kanonische Ablage

| Informationsart | Kanonische Quelle | Darf diese Datei enthalten? |
| --- | --- | --- |
| bindende Arbeits- und Stop-Regeln | Root- beziehungsweise app-lokale `AGENTS.md` | nur Verweis |
| dauerhaft geltende Cross-App-Architektur | `docs/architecture.md` und ADRs | nur Verweis und noch nicht entschiedene Zielgrenze |
| Datenschutz-/Retentionvertrag | `docs/privacy-architecture.md` | nur modulspezifische offene Entscheidung, keine Duplikation |
| nicht freigegebene künftige AD-Module | diese Datei | ja, kanonisch |
| freigegebene Parent-Aufgaben | `docs/implementation-tasks.md` | nein |
| freigegebene App-Aufgaben | jeweilige app-lokale `ROADMAP.md` | nein |
| unentschiedene wiederverwendbare Beobachtungen | `docs/learning-candidates.md` | nein |
| öffentliche Produkt-/Releaseplanung | getrenntes Repository `ad-suite` | erst nach Produktfreigabe |
| historische abgeschlossene Pläne | `docs/plans/` mit sichtbarem historischen Status | nein |

Damit wird verhindert, dass eine Vormerkung durch Retrieval zusammen mit
freigegebenen Aufgaben als Implementierungsauftrag erscheint. Übersichten
verlinken auf diese Datei; sie kopieren weder Backlog noch Fachverträge.

### Strukturprüfung vom 9. August 2026

- **Konsistent:** Root-`README.md`, `AGENTS.md`, Architektur,
  Datenschutzarchitektur, freigegebene Parent-Aufgaben und Learning
  Candidates besitzen getrennte Rollen.
- **Konsistent:** Die historischen Dateien unter `docs/plans/` sind sichtbar
  als historisch markiert und werden nicht vom Root-Einstieg als aktuelle
  Aufgabenquelle geroutet.
- **Konsistent:** `dist/`, `build/`, `nextcloud-dev/html/` und getrennte
  App-Repositories sind im Parent-Git ignoriert. Ein externer RAG-Indexer
  muss dieselben Grenzen ausdrücklich übernehmen; `.gitignore` allein
  garantiert das nicht.
- **Bewusste Wiederholung:** Kurze Sicherheits- und Repositorygrenzen in
  `AGENTS.md` fassen normative Dokumente für die Arbeitssteuerung zusammen.
  Das ist eine direkte Instruktionskette und kein Anlass, Fachplanung dort zu
  duplizieren.
- **Behobene Lücke:** Für nicht freigegebene appübergreifende
  Zukunftsplanung gab es keine eindeutige Parent-Quelle. Diese Datei schließt
  die Lücke und wird nur aus den drei Root-Einstiegen verlinkt.
- **Verifizierter Drift, hier nicht geändert:** Der historische
  Normalisierungsplan verlangt Roadmaps ohne Abschnitt `Umgesetzt`; mehrere
  aktuelle app-lokale Roadmaps enthalten wieder umgesetzte Aufgaben oder
  Aufgaben mit umgesetzt markierten Teilpunkten. Das betrifft getrennte
  Repositories und braucht einen eigenen, ausdrücklich autorisierten
  Bereinigungslauf. Retrieval sollte bis dahin aktuelle Fachverträge aus
  `AGENTS.md`/`docs/architecture.md` höher gewichten als Statusprosa in
  Roadmaps.
- **Kein Duplikat:** Diese Planung gehört weder in
  `docs/implementation-tasks.md` noch in `docs/learning-candidates.md`, weil
  sie weder freigegebene Parent-Umsetzung noch unbewertete Beobachtung ist.
- **Kein vorzeitiger Produkteintrag:** `ad-suite/ROADMAP.md`, Produktkatalog
  und Repositoryinventar bleiben unverändert, bis eine Produkt- und
  App-Freigabe vorliegt.

Empfohlene RAG-Priorität: `AGENTS.md` und ADRs vor Architekturdokumenten,
Architekturdokumente vor dieser Zukunftsplanung, diese Zukunftsplanung vor
Roadmaps, historische Pläne nur bei ausdrücklich historischem Kontext.
Generierte `dist/`-/`build/`-Inhalte, Runtime-Core, Dependencies und
Abdeckungsberichte gehören nicht in den Wissensindex.

## Freigabepunkte

Vor dem ersten Implementierungsschritt müssen offiziell freigegeben werden:

1. Fachlicher Scope und Nicht-Scope beider Apps.
2. Endgültige App-Namen und technischen App-IDs.
3. Zuständiger Datenbesitzer für Teams, Beschäftigtenidentität,
   Vertragsstunden, Qualifikationen und Beschäftigungsstatus.
4. DPA-Stundenmodell einschließlich abgesicherter, geplanter, verpflichtend
   gemeldeter und freiwilliger Stunden.
5. Vollständiges DPA-Zustandsmodell einschließlich Abschluss und
   Wiederöffnung.
6. Vollständiges Schicht-, Suchstufen- und Kontaktzustandsmodell.
7. Verbindliche Eignungsmerkmale, Ausschlüsse und menschliche
   Entscheidungsverantwortung.
8. Ausfallgeldfristen, Kontaktpflicht, Kurzfristkennzeichnung und
   Erfüllungsnachweis.
9. Regeln für Sonderzuschlag, Genehmigung und Abrechnungsübergabe.
10. Ownership und Berechnung des 8–12-Wochen-Bedarfskorridors.
11. Aggregationsschwellen und zulässige saisonale Datenbasis.
12. Rollen-, Berechtigungs-, Vertretungs- und Auditmatrix.
13. Datenschutzklassen, Zweckbindung, Drittpersonenbezug, Auskunft,
    Archivierung, Retention und Löschung.
14. Operative BEM-/Gesundheitsdaten-Abgrenzung.
15. Excel-/CSV-Quellen, Importprofile, Quellverantwortung, Korrektur- und
    Rückbauverfahren.
16. Versionierte Cross-App-Verträge, Missing-Provider-Verhalten und
    Kompatibilitätsstrategie.
17. Standalone-, Installations-, Deinstallations-, Rollback- und
    App-Store-Modell.
18. Pilotreihenfolge, TDD-Nachweis, Testmatrix, Abnahmekriterien und
    Repositoryfreigabe für jede neue App.
19. Pilot-App, App-Reihenfolge, unterstützte Locales, Fallbackvertrag und
    Rohtext-Gate für den appübergreifenden L10N-Rollout.
