# ADR 0002: Neutrale Standalone-Datenschutzplattform

- Status: angenommen
- Entscheidung: 2026-08-23
- Geltungsbereich: app-übergreifende Auskunft, Providerabdeckung,
  Retention-Koordination und öffentliche Entwicklerverträge
- Umsetzung: fortgeschritten; die Standalone-App besitzt den öffentlichen V1-Kern
  und ist kanonischer Owner. Der LocalBase-Retention-Pilot wurde nach grüner
  Lifecycle- und Rückbaumatrix entfernt; der getrennte PersonalData-Pilot bleibt
  bis zur Projektion der LocalBase-eigenen Personenwerte bestehen

## Kontext

Der LocalBase-Pilot beweist mit mehreren realen Fachapps, dass eine
providerbasierte Art.-15-Auskunft ohne direkten Zugriff auf fremde Tabellen
funktioniert. Das Ziel ist inzwischen nicht mehr nur eine interne FLZ-/BR-
Integration. Der Vertrag soll neutral, eigenständig installierbar und für
beliebige Nextcloud-App-Entwickler sowie öffentliche Einrichtungen nutzbar
werden.

LocalBase besitzt zugleich organisations-, kalender-, demo- und
suitebezogene Verantwortungen. Eine dauerhafte öffentliche Datenschutz-API in
dieser App würde fremde Provider an einen fachlich breiteren internen
Infrastrukturdienst binden. Gebündelte Kopien der Providerinterfaces würden
wiederum die notwendige gemeinsame Klassenidentität des In-Process-Vertrags
zerstören.

Der offizielle App-Store-Vertrag dokumentiert keine automatische
App-zu-App-Installation oder Versionsauflösung. Diese darf daher weder für
Consumer noch für Releasearchive vorausgesetzt werden.

## Entscheidung

Die Zielkomponente ist die neutrale Standalone-App und eigenständig
versionierte Nextcloud-Laufzeit-App
`flz_data_protection` der **Kategorie B** nach ADR 0001. Der öffentliche
Produktname lautet `Data Protection Center`, die deutsche Bezeichnung
`Datenschutz-Center`. Das Präfix `filzmann_` ist die bewusst gewählte,
appübergreifende Herausgeberkennung. Eine aktuelle Store-Prüfung ergab keine
sichtbare Namenskollision; die endgültige Store-ID-Reservierung bleibt Teil
einer späteren Veröffentlichung.

Die neutrale Standalone-App besitzt künftig geschlossen:

- öffentliche DTOs und Providerinterfaces;
- Registry, Versionshandshake und erwartete Providerabdeckung;
- Aggregation, Cursor-Paging und Vollständigkeitsstatus;
- Self-Service-, Admin- und Exportoberflächen;
- eigene Berechtigungs-, Audit-, Konfigurations- und Jobgrenzen;
- später die Koordination von Retention und Lifecycle-Ereignissen.

Fachapps behalten ihre Providerimplementierungen und bleiben alleinige
Eigentümerinnen ihrer Daten. Die Standalone-App liest oder verändert keine
fremden Tabellen, Entitäten, AppConfig-/UserConfig-Werte, Dateien oder
AppData-Strukturen direkt.

### Optionaler Integrationsvertrag ohne Daten-Fallback

Eine Fachapp bleibt ohne aktive Privacy-App fachlich funktionsfähig. Ihre
Providerintegration wird lazy registriert und erst bei einem kompatiblen
Registry-Aufruf instanziiert. Fehlt die Privacy-App, findet keine
Datenschutzaggregation statt; es wird kein alternativer Datenpfad geöffnet.

Ist die Privacy-App aktiv, werden App-ID, Vertragsversion, Subject-Typen,
Fähigkeiten und Paginggrenzen vor der Verwendung geprüft. Ein fehlender,
deaktivierter, fehlerhafter oder inkompatibler Provider wird kontrolliert als
`missing`, `failed` oder `partial` sichtbar. Es gibt **kein Daten-Fallback**
über SQL, Reflection, Volltextsuche, fremde Speicherpfade oder eine
Umdeutung von `IUserMigrator`-Archiven.

Da keine automatische App-zu-App-Installation vorausgesetzt wird, muss ein
öffentliches Release die optionale Integration, unterstützte
Vertragsversionen und das Verhalten ohne Privacy-App dokumentieren und in
einer sauberen Installationsmatrix prüfen.

### Öffentlicher Entwicklervertrag

Der normative Entwicklerleitfaden ist
[`docs/privacy-provider-guide.md`](../privacy-provider-guide.md). Er legt den
kleinsten interoperablen Providervertrag, Art.-15-Metadaten, Status,
Cursor-Paging, Drittpersonenschutz, Geheimnisausschluss und ein
Contract-Test-Kit fest. Beispiele, Bezeichner und Testdaten bleiben neutral
und organisationsunabhängig.

Der Vertrag trennt ausdrücklich:

- `PersonalDataProvider` für verständliche Art.-15-Auskunft;
- Nextcloud `IUserMigrator` für Portabilität und Instanzmigration;
- `RetentionProvider` für Vorschau und spätere app-eigene Maßnahmen;
- `SubjectLifecycleProvider` für belastbare, semantisch getrennte Ereignisse.

Eine spätere Upstream-Initiative für eine implementierbare öffentliche
Nextcloud-OCP-Schnittstelle ist erwünscht, aber keine Voraussetzung für den
ersten Standalone-Vertrag. Bis zu einer tatsächlichen Aufnahme in OCP bleibt
die Privacy-App alleinige kanonische Eigentümerin ihrer API.

## Migration und Rollback

Die Migration ist kein Big Bang:

1. Name, App-ID, Lizenz, Repository und vorläufigen Nextcloud-Zielkorridor
   entscheiden; erledigt mit `flz_data_protection`, AGPL und dem noch vor
   Veröffentlichung nachzuweisenden Korridor 29 bis 35.
2. Den vorhandenen LocalBase-Vertrag charakterisieren und einen
   versionsbehafteten Provider-/Consumer-Contract-Test festschreiben.
3. Die Standalone-App mit Registry, Aggregator und einer synthetischen
   Referenzintegration anlegen; LocalBase bleibt währenddessen führend.
4. Genau einen bestehenden Provider mit Aktivierungs- und Versionsprüfung
   migrieren und Installation mit sowie ohne Privacy-App testen.
5. Weitere Provider einzeln migrieren. Es gibt zu keinem Zeitpunkt zwei
   aktive Aggregatoren oder zwei kanonische Vertragsversionen für denselben
   Consumer.
6. Erst nach vollständiger Providerumstellung Self-Service, Adminoberfläche
   und Audit aus LocalBase entfernen.
7. Retention-Ausführung, Lifecycle und Release-Gates bleiben getrennte
   Folgeaufträge.

Vor Schritt 6 ist der Rückbau durch Deaktivieren der neuen Integration und
Weiterbetrieb des charakterisierten LocalBase-Piloten möglich. Nach der
Entfernung benötigt ein Rückbau ein kompatibles LocalBase-Release; deshalb
wird diese Contract-Grenze erst nach sauberer Update-, Deinstallations- und
Rollbackmatrix überschritten. Personenbezogene Fachdaten werden bei der
Runtime-Migration weder kopiert noch transformiert.

## Folgen

- LocalBase ist nicht die dauerhafte Zielruntime des Datenschutzvertrags. Der
  Retention-Pilot ist entfernt; der verbleibende PersonalData-Pilot wird erst
  nach verlustfreier Projektion der LocalBase-eigenen Personenwerte abgebaut.
- Die neue App ist kein Bestandteil eines Fachapp-Archivs und keine
  stillschweigende Voraussetzung fachlicher Grundfunktionen.
- Behörden können über ein konfiguriertes Coverage-Profil festlegen, welche
  installierten Apps einen Provider liefern müssen. Eine Lücke bleibt
  sichtbar und verhindert ein irreführendes Vollständigkeitsurteil.
- Eine Store-Veröffentlichung bleibt bis zu neutraler Benennung,
  Versionshandshake, sauberer Installationsmatrix, öffentlicher Dokumentation
  und geklärter Deinstallationswirkung blockiert.
- Diese Entscheidung ersetzt ausschließlich die frühere Zielzuordnung der
  Privacy-Runtime zu LocalBase. Der belegte Pilotstand und alle allgemeinen
  Kategorie-A/B/C-Regeln aus ADR 0001 bleiben gültig.

## Nicht entschieden

- Governance und Maintainerkreis eines öffentlichen Vertrags;
- minimale unterstützte Nextcloud-Version;
- Zeitpunkt und Verfahren einer möglichen OCP-Upstream-Einreichung;
- rechtliche Standardfristen, Beschäftigungsdatenquelle und externe
  Identitätsprüfung.
