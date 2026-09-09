# Leitfaden für Nextcloud-Datenschutzprovider

Stand: 23. August 2026

Dieser Leitfaden beschreibt den geplanten öffentlichen Vertrag, mit dem eine
Nextcloud-App personenbezogene Daten für eine verständliche Art.-15-Auskunft
bereitstellt. Er ist eine Entwicklerempfehlung und keine Behauptung, eine App
oder Installation erfülle allein dadurch die DSGVO.

Normative Architektur- und Eigentumsentscheidungen stehen in
[`privacy-architecture.md`](privacy-architecture.md) und
[`ADR 0002`](architecture-decisions/0002-standalone-privacy-platform.md).

## Grundsatz

Die datenbesitzende App erzeugt selbst eine zulässige, strukturierte
Projektion. Die Privacy-App aggregiert nur. Sie kennt weder Tabellennamen noch
private Entitäten oder Speicherpfade eines Providers.

Es gibt keinen SQL-Fallback, keine Reflection fremder Modelle, keine
Volltextsuche durch fremde Dateien und keine direkte Abfrage fremder
AppConfig-/UserConfig-Werte. Ein fehlender Provider bleibt sichtbar
`missing`; er wird nicht durch Heuristik kaschiert.

## Minimalvertrag Version 1

Der kanonische Namespace ist
`OCA\FilzmannDataProtection\PublicApi\V1`. Der erste pre-release Contract-Kern
liegt in der Standalone-App; externe Consumer bleiben bis zur ausdrücklichen
Freigabe und einem vollständigen Contract-Test-Kit blockiert. Fachlich
benötigt Version 1 mindestens:

```php
interface PersonalDataProvider {
    public function descriptor(): ProviderDescriptor;
    public function collect(PersonalDataRequest $request): PersonalDataPage;
}
```

`ProviderDescriptor` enthält eine stabile Provider- und App-ID,
Vertragsversion, Anzeigename, unterstützte Subject-Typen, Fähigkeiten und
Paginggrenzen. Der Versionshandshake erfolgt vor dem ersten Datenabruf.

`PersonalDataRequest` enthält eine von der Privacy-App serverseitig gebundene
`DataSubjectRef`, Sprache, Auskunftszweck, Seitenlimit und je Provider einen
opaken Cursor. Vor dem Provideraufruf wird ausschließlich dessen eigener
Cursor aktiviert und dessen deklarierte maximale Seitengröße angewandt. Ein
Self-Service-Provider akzeptiert keine frei vom Browser gewählte UID.

`PersonalDataPage` enthält verständliche Dateneinträge, Einschränkungen,
Status und entweder einen nächsten opaken Cursor oder eine eindeutige
Endmarke. Cursor-Paging darf Datensätze weder überspringen noch doppelt
ausgeben; ein Provider muss Änderungen während eines längeren Exports
kontrolliert als `partial` kenntlich machen oder einen stabilen Snapshot
verwenden.

Jeder Dateneintrag besitzt außerdem eine stabile technische `reference`. Sie
dient der nachvollziehbaren Zuordnung innerhalb der Providerprojektion, ist
kein direkter Zugriffsschlüssel auf eine fremde Tabelle und enthält keine
Geheimnisse.

Die allgemeine fachliche Policy eines Datentyps wird nicht unabhängig in
jeder Ausgabe neu gepflegt. Sie stammt künftig aus dem app-lokalen Katalog
nach dem Root-Schema
[`privacy-processing-metadata.schema.json`](contracts/privacy-processing-metadata.schema.json).
Contract-Owner und öffentlicher Namespace bleiben dabei die bestehende App
`filzmann_data_protection` (`Data Protection Center`/`Datenschutz-Center`,
`OCA\FilzmannDataProtection`); es entsteht keine zusätzliche Provider-App.
V1 projiziert davon weiterhin nur die heute vertraglich vorgesehenen Angaben
in den konkreten `PersonalDataEntry`; eine eigenständige Katalogabfrage wird
nur als neue, versionierte öffentliche Fähigkeit ergänzt. Sie darf V1 nicht
still verbreitern und enthält keine personenbezogenen Laufzeitdaten.

Der additive Pilotvertrag verwendet dafür
`ProcessingMetadataProviderDescriptor`, `ProcessingMetadataCatalog` und
`ProcessingMetadataProvider`. Seine Registrierung ist lazy und
versionsgeprüft. Ein Provider liest ausschließlich den app-eigenen Katalog
`resources/privacy-processing.json`; ungültige Kataloge und fehlerhafte
Provider werden isoliert, ohne interne Fehlerdetails oder fremde Daten
offenzulegen. Der weitere Consumer-Rollout und das vollständige Coverage-Gate
bleiben bis zu gesondert freigegebenen Cross-Repository-Läufen offen.

Der erste reale Consumer ist `adroom`. Sein Provider wird im Parent gegen den
echten Vertrag und das Contract-Test-Kit der Standalone-App geprüft; der
app-lokale Test bleibt über kleine Test-Stubs auch ohne benachbarten Checkout
ausführbar. Diese Stubs sind keine Produktionsabhängigkeit und keine zweite
Runtime-Implementierung. Weitere Apps benötigen weiterhin jeweils eine
ausdrückliche Schreibfreigabe.

## Erforderliche Art.-15-Angaben

Ein Provider liefert je App und Datenart mindestens:

- Datenkategorie, verständliche Bezeichnung und freigegebene Attribute;
- Verarbeitungszweck;
- Herkunft der Daten, soweit bekannt;
- Empfänger oder Empfängerkategorien;
- Aufbewahrungsfrist oder nachvollziehbare Kriterien;
- Drittlandübermittlung oder deren Nichtvorliegen;
- automatisierte Entscheidungen oder deren Nichtvorliegen;
- Hinweis auf zurückgehaltene Drittpersonen- oder Sicherheitsinhalte;
- technische Referenz, die keine fremde interne Primärschlüsselbedeutung
  vortäuscht.

Passwörter, Hashes, Tokens, App-Passwörter, OAuth-/CalDAV-Zugangsdaten,
Schlüssel und andere Sicherheitsgeheimnisse werden nicht ausgegeben. Ihre
Existenz und ihr Zweck können ohne Geheimniswert beschrieben werden.

## Status und Vollständigkeit

- `complete`: alle vom Provider für Subject, Anfrage und Seite zugesagten
  Daten wurden geliefert;
- `partial`: eine bekannte Teilmenge wurde geliefert und die Einschränkung ist
  maschinen- sowie menschenlesbar benannt;
- `not_applicable`: der Subject-Typ wird unterstützt, für diese Person liegen
  aber keine auskunftspflichtigen Daten vor;
- `failed`: der Provider konnte seine Antwort nicht sicher erzeugen;
- `missing`: ein laut Coverage-Profil erwarteter Provider fehlt oder ist
  inkompatibel.

`complete` bezeichnet niemals die gesamte Nextcloud-Instanz, sondern nur den
zugesagten Providerumfang. Das Gesamturteil entsteht erst aus einem
administrativ nachvollziehbaren Coverage-Profil.

## Registrierung und Betrieb ohne Privacy-App

Die Providerintegration wird lazy über den typisierten Nextcloud-Event-
Dispatcher registriert. Die Fachapp bleibt ohne aktive Privacy-App vollständig
fachlich nutzbar. Providerklassen werden erst auf einen kompatiblen
Registry-Aufruf instanziiert; ein fehlender Vertrag endet weder in einem
PHP-Fatal-Error noch in einem alternativen Datenzugriff.

Vor einer öffentlichen Freigabe muss jede Consumer-App folgende Matrix
prüfen:

| Zustand | Erwartung |
| --- | --- |
| Privacy-App fehlt | Fachapp funktioniert; keine Auskunftsintegration |
| Privacy-App deaktiviert | Fachapp funktioniert; keine Providerinstanziierung |
| kompatible Version | Provider registriert sich genau einmal |
| inkompatible Version | kontrollierter `missing`-/Kompatibilitätsstatus |
| Providerfehler | `failed`; andere Provider laufen weiter |

Da Nextcloud keine automatische App-zu-App-Installation als Store-Vertrag
bereitstellt, darf eine Consumer-App weder Installationsreihenfolge noch eine
unkontrollierte Klassenauflösung voraussetzen.

## Abgrenzung zu nativen Nextcloud-Funktionen

`OCP\UserMigration\IUserMigrator` bleibt der native Vertrag für Export,
Import und Datenportabilität zwischen Instanzen. Er ersetzt keinen
`PersonalDataProvider`, weil ein Migrationsarchiv nicht automatisch Zwecke,
Empfänger, Aufbewahrung, Drittpersonenschutz oder verständliche
Vollständigkeit beschreibt.

Eine App sollte möglichst einen gemeinsamen internen, berechtigungsgeprüften
Projektionsservice verwenden und daraus getrennt Art.-15-Bericht und
`IUserMigrator`-Export erzeugen. Die Ausgabeformate und Sicherheitsgrenzen
bleiben verschieden.

`RetentionProvider` und `SubjectLifecycleProvider` sind ebenfalls getrennte
Verträge. Eine Auskunft löst niemals nebenbei Löschung, Anonymisierung oder
Statusänderungen aus.

Der aktuelle öffentliche V1-`RetentionProvider` ist absichtlich ein reiner
Preview-Vertrag: `descriptor()`, `policies()` und `preview()` stehen zur
Verfügung, `execute()` nicht. Jede Policy ist versioniert und benennt
Datenklasse, Zweck, Trigger, Frist und ausschließlich die Maßnahme `REVIEW`.
Die Fachapp berechnet Cutoff und Kandidaten selbst; die zentrale App erhält
weder SQL noch fremde Tabellenkenntnis. Fehlerhafte, doppelte oder
inkompatible Provider werden isoliert sichtbar.

Das operative Dashboard ist serverseitig geschützt. Native
Nextcloud-Admins besitzen nach Neuinstallation kein automatisches fachliches
Leserecht. Konfigurierte Datenschutz-Prüfgruppen erhalten es ausdrücklich;
ein einzelnes Administrationskonto kann nur über die app-lokale,
auditierbare Freigabe für höchstens 24 Stunden zugreifen. Das technische
Konfigurationsrecht bleibt davon getrennt und erteilt kein REVIEW-Recht.

## Contract-Test-Kit

Die Standalone-App enthält im öffentlichen V1-Namespace das technische
`PersonalDataProviderContractTestKit`. Es prüft je synthetischem Szenario den
Versionshandshake, den unterstützten Subject-Typ, die providergebundene
Cursorübergabe, die maximale Seitengröße und den Cursorfortschritt. Die
Consumer-App ergänzt damit mindestens folgende eigene Sicherheits-, Daten-
und Betriebsszenarien:

- eigene Person erfolgreich, fremde oder manipulierte Person abgewiesen;
- keine Nebenwirkung einer Auskunft;
- Geheimnisse und nicht freigegebene Drittpersonendaten fehlen;
- `complete`, `partial`, `not_applicable` und `failed` sind erreichbar und
  korrekt begründet;
- Cursor-Grenzen, letzte Seite, Wiederholung und veränderter Datenstand;
- fehlende, deaktivierte und inkompatible Privacy-App ohne Fatal Error;
- Providerfehler blockiert keinen nachfolgenden Provider;
- synthetische, neutrale und datenschutzarme Fixtures.

Das Test-Kit prüft den technischen Vertrag, nicht die rechtliche
Vollständigkeit der konkreten Dateninventur. Diese bleibt Reviewaufgabe der
datenbesitzenden App und der verantwortlichen Stelle.

Verifizierte Workspace-Beispiele sind `adroom` für kontextbewahrend ersetzten
Drittpersonen-Freitext und `filzmann_permission_matrix` für eine
subjectgebundene Projektion mehrerer eigener Nachweistabellen. Letztere zeigt
nur freigegebene Snapshot-, Export- und Auditmetadaten; freie Dateinamen,
Inhalte, Auditdetails und fremde Benutzerbezüge bleiben in der Fachapp.

## Reviewfragen vor Veröffentlichung

1. Welche realen Personentypen und Identifier unterstützt die App?
2. Welche Tabellen, Dateien, AppData-, DAV-, Share-, Konfigurations-, Cache-
   und Exportbestände gehören zum Inventar?
3. Welche Einträge enthalten Inhalte oder Rechte anderer Personen?
4. Welche Geheimnisse werden nur als Kategorie beschrieben?
5. Wie werden mehr als eine Seite und gleichzeitige Änderungen behandelt?
6. Woran erkennt der Aggregator den vollständigen Providerumfang?
7. Welche Vertragsversionen sind kompatibel und wie wird Inkompatibilität
   diagnostiziert?
8. Verwenden Art.-15-Auskunft, Portabilität und Retention dieselbe kanonische
   Fachdatenprojektion ohne ihre Zwecke zu vermischen?
