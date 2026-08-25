# ADR 0004: App-lokaler, zeitlich begrenzter Admin-Vollzugriff

- Status: angenommen
- Entscheidung: 2026-08-25
- Geltungsbereich: alle von Simon verantworteten Nextcloud-Apps

## Kontext

Der native Nextcloud-Adminstatus darf nicht automatisch sämtliche fachlichen
Lese-, Schreib-, Freigabe- oder Verwaltungsrechte einer App erteilen. Ein
technischer Administrator benötigt weiterhin Zugriff auf den geschützten
Nextcloud-Adminbereich, damit Installation, Konfiguration und die hier
beschriebene Notfallfreigabe möglich bleiben. Dieser technische Zugang ist
kein fachlicher Vollzugriff.

Die Apps bleiben getrennt installierbar. Künftige Entwicklerinnen und
Entwickler müssen die Freigabe anhand des jeweiligen App-Zwecks, Datenmodells
und Berechtigungssystems selbst integrieren und prüfen können. Eine zentrale
Freigabe-App oder eine verpflichtende LocalBase-Abhängigkeit wurde deshalb
verworfen.

## Entscheidung

Die Vollzugriffssteuerung ist bewusst **Kategorie C – app-lokal**. Jede App
besitzt ihre eigene kanonische Freigabe- und Auditquelle. Es gibt keine
fremden Tabellen-, AppConfig- oder Runtime-Zugriffe und keine zentrale zweite
Wahrheit.

Für jede App mit fachlichem Vollzugriff gilt:

- Der Vollzugriff ist standardmäßig aus. Native Nextcloud-Administration
  allein erteilt keine fachlichen Rechte.
- Eine Freigabe gilt pro Admin und App. Gruppenweite oder pauschale
  Freigaben für alle Administratorinnen und Administratoren sind unzulässig.
- Nur eine aktuell als Nextcloud-Admin bestätigte Person darf als Ziel oder
  freigebende Person verwendet werden. Der Adminstatus wird bei jedem
  fachlichen Zugriff erneut geprüft; eine inzwischen entzogene native
  Adminrolle macht eine noch gespeicherte Freigabe wirkungslos.
- Jede Freigabe besitzt einen serverseitig gesetzten Beginn und ein
  verpflichtendes Ende. Das Ende liegt höchstens 24 Stunden nach Beginn.
  Clientzeit und frei übermittelte Startwerte sind nicht vertrauenswürdig.
- Eine fehlende oder abgelaufene Freigabe, ein Widerruf, ungültige Zeiten,
  unbekannte Konten und Speicher-/Prüffehler ergeben deny by default.
- Wiederholte Aktivierung erzeugt einen neuen Zeitraum. Frühere Zeiträume
  werden weder überschrieben noch gelöscht.
- Aktivierung, Beginn, geplantes Ende, Widerruf, tatsächliches Ende,
  freigebende UID und Ziel-Admin-UID werden in der app-eigenen Auditquelle
  nachvollziehbar gehalten. Technische Logs ergänzen diese Historie
  datensparsam, ersetzen sie aber nicht.
- Die UI zeigt aktiven Zustand und Ablaufzeit textlich an. Der Schalter ist
  nur eine Bedienoberfläche; jeder fachliche Service- und Controllerpfad
  prüft die Freigabe serverseitig.

Apps ohne fachliche Daten oder ohne nativen Admin-Vollzugriff dokumentieren
die begründete Nichtanwendbarkeit. Sie prüfen sie bei jeder späteren
Scopeänderung erneut und bauen keinen wirkungslosen Schalter.

## Daten-, Provider- und Testvertrag

Freigabe- und Auditdatensätze sind personenbezogene Sicherheitsdaten. Der
app-eigene `PersonalDataProvider` weist Bezüge der betroffenen Ziel- und
freigebenden UIDs aus; Drittpersonenbezüge bleiben subjectgerecht begrenzt.
Der app-eigene `PermissionProvider` beschreibt den zeitgebundenen
Admin-Vollzugriff als nicht gruppenbezogene, standardmäßig inaktive
Bedingung. Die Berechtigungsmatrix darf aus dem nativen Adminstatus keine
aktive Fachfreigabe ableiten.

Jede App belegt mindestens:

- Nicht-Admin, Admin ohne Freigabe und abgelaufene/widerrufene Freigabe
  bleiben ohne Vollzugriff;
- ein konkret freigegebener aktueller Admin erhält nur während seines
  app-eigenen Zeitraums Vollzugriff;
- ein anderer Admin profitiert nicht von dieser Freigabe;
- mehr als 24 Stunden, manipulierte UIDs/Zeiten und unbekannte Konten werden
  ohne Mutation abgewiesen;
- Aktivierung und Widerruf erzeugen die erwartete Historie;
- App-spezifische Fachrechte normaler Rollen bleiben unverändert.

Additive Migrationen verändern keine Bestandsfachdaten. Bei Rückbau wird die
vorherige App-Version wiederhergestellt; die neue Audit-Historie bleibt
unangetastet und erteilt ohne ausführenden Freigabecode keine Rechte.

## Klassifikation und Folgen

| Bestandteil | Eigentümer / Consumer | Evidenz | Kategorie | Grund und Zielstruktur | Risiko / Store-Auswirkung |
| --- | --- | --- | --- | --- | --- |
| Aktive Freigaben, Audit, Admin-UI und serverseitige Prüfung | jeweilige Fachapp | verifiziert durch ausdrückliche Produktentscheidung | C | app-eigene Daten und Fachrechte; keine zentrale Runtime oder Persistenz | additive Migration und app-eigene Releaseprüfung erforderlich; keine neue Store-Abhängigkeit |
| Gemeinsame Invariante und Prüfvorgaben | Parent und alle eigenen Apps | verifiziert | kein Runtime-Code | dieser ADR ist die einzige app-übergreifende Regelquelle; lokale Projektionen bleiben synchron | Cross-App-Vertragstests müssen Abweichungen sichtbar machen |

Die lokale Implementierung ist eine bewusste, nicht triviale Duplikation. Sie
ist gewollt, weil App-Zweck, fachliche Vollzugriffsflächen, bestehende
Berechtigungsservices, Migrationen, Audit- und Providerprojektionen
unterschiedliche Änderungsgründe besitzen. Gemeinsame Runtime-Klassen oder
eine zentrale Datenhaltung würden die eigenständige Installation und lokale
Verantwortung unnötig koppeln.
