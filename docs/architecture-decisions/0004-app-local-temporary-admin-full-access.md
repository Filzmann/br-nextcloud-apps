# ADR 0004: App-lokaler, zeitlich begrenzter Admin-Vollzugriff

- Status: angenommen
- Entscheidung: 2026-08-25
- Fortgeschrieben: 2026-09-17
- Geltungsbereich: alle von Simon verantworteten Nextcloud-Apps

## Kontext

Der native Nextcloud-Adminstatus darf nicht automatisch sämtliche fachlichen
Lese-, Schreib-, Freigabe- oder Verwaltungsrechte einer App erteilen. Ein
technischer Administrator benötigt weiterhin Zugriff auf den geschützten
Nextcloud-Adminbereich für Installation und technische Konfiguration. Dieser
technische Zugang ist kein fachlicher Vollzugriff und erteilt insbesondere
kein Recht, Vollzugriffsfreigaben zu vergeben oder zu widerrufen.

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
- Als Ziel ist ausschließlich ein aktuell bestätigtes natives
  Nextcloud-Administrationskonto zulässig. Der Adminstatus wird bei jedem
  fachlichen Zugriff erneut geprüft; eine inzwischen entzogene native
  Adminrolle macht eine noch gespeicherte Freigabe wirkungslos.
- Erteilen und widerrufen dürfen ausschließlich aktuell bestätigte Mitglieder
  der Nextcloud-Gruppe `Datenschutzbeauftragte`. Sie müssen nicht zugleich
  native Nextcloud-Admins sein. Nativer Adminstatus allein erteilt kein
  Freigabe- oder Widerrufsrecht; die Gruppenmitgliedschaft wird bei jeder
  schreibenden Freigabeaktion serverseitig erneut geprüft.
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
- Beim Betreten eines geschützten App-Pfads erhält ausschließlich ein
  bestätigtes natives Nextcloud-Administrationskonto ohne aktive Freigabe eine
  aussagekräftige, zustandssichere Meldung. Sie erklärt, dass der native
  Adminstatus keinen fachlichen Vollzugriff erteilt und eine app-lokale,
  zeitlich begrenzte Freigabe fehlt oder nicht aktiv ist, ohne fremde
  Freigaben, Beteiligte oder Auditdaten offenzulegen.
- Ist dieses Administrationskonto zugleich Mitglied der Gruppe
  `Datenschutzbeauftragte`, verlinkt die Meldung direkt auf die geschützte
  app-lokale Freigabesteuerung. Andere unberechtigte oder gewöhnliche Konten
  erhalten weder diese administrative Zustandsinformation noch einen
  Freigabelink. Die Verlinkung selbst erteilt keine Berechtigung.

Die app-lokale Freigabesteuerung muss für berechtigte Mitglieder der Gruppe
`Datenschutzbeauftragte` erreichbar sein, auch wenn sie keine nativen
Nextcloud-Admins sind. Sie bleibt von der ausschließlich technisch
administrierbaren Nextcloud-Appkonfiguration getrennt und wird weder zu einer
zentralen Freigaberuntime noch zu einem zentralen Speicher.

Apps ohne fachliche Daten oder ohne nativen Admin-Vollzugriff dokumentieren
die begründete Nichtanwendbarkeit. Sie prüfen sie bei jeder späteren
Scopeänderung erneut und bauen keinen wirkungslosen Schalter.

## Daten-, Provider- und Testvertrag

Freigabe- und Auditdatensätze sind personenbezogene Sicherheitsdaten. Der
app-eigene `PersonalDataProvider` weist Bezüge der betroffenen Ziel-,
freigebenden und widerrufenden UIDs aus; Drittpersonenbezüge bleiben
subjectgerecht begrenzt.
Der app-eigene `PermissionProvider` beschreibt den zeitgebundenen
Admin-Vollzugriff als nicht gruppenbezogene, standardmäßig inaktive
Bedingung. Die Berechtigungsmatrix darf aus dem nativen Adminstatus keine
aktive Fachfreigabe ableiten.

Für die app-lokale Freigabehistorie gelten sechs Monate ab dem tatsächlichen
Ende der Freigabe als administrativ konfigurierbarer Standardwert. Das
tatsächliche Ende ist der frühere Zeitpunkt aus geplantem Ende und wirksamem
Widerruf. Mitglieder der Gruppe `Datenschutzbeauftragte` dürfen den Wert
verkürzen oder verlängern; Änderungen werden anhand dieses ursprünglichen
Triggers auch auf bereits vorhandene Historieneinträge angewendet. Nach
Fristablauf wird der Historieneintrag vollständig gelöscht; es bleibt weder
eine anonymisierte Spur noch eine Statistik. Eine aktive rechtliche oder
datenschutzrechtliche Sperre verhindert die Löschung; nur Mitglieder von
`Datenschutzbeauftragte` dürfen sie begründet und auditierbar aufheben. Nach
einem Restore wird die Frist vom ursprünglichen tatsächlichen Ende neu
bewertet, ohne eine Freigabe zu reaktivieren. Abgelaufene ungesperrte
Nachweise werden erneut zur automatischen Löschung eingeplant; eine manuelle
Einzelfreigabe ist dafür nicht vorgesehen.

Diese fachliche Entscheidung ist noch nicht als Retention-Ausführung
implementiert. Bis Policyversion und Wirksamkeitszeitpunkt,
Ausführungsreihenfolge, Atomarität, Nebenläufigkeit und Idempotenz,
Backupgrenze, Sperrdurchsetzung, Auditvollständigkeit, automatische
Wiederholungen, datensparsame Fehlermeldung, 30-tägiger technischer
Fehlernachweis, Fehlerrückbau sowie Provider-/Consumer-Verhalten appweise
freigegeben und getestet sind, bleibt jede automatische Löschung blockiert.

Jede App belegt mindestens:

- Nicht-Admin, Admin ohne Freigabe und abgelaufene/widerrufene Freigabe
  bleiben ohne Vollzugriff;
- ein konkret freigegebener aktueller Admin erhält nur während seines
  app-eigenen Zeitraums Vollzugriff;
- ein anderer Admin profitiert nicht von dieser Freigabe;
- ein Mitglied von `Datenschutzbeauftragte` kann unabhängig vom eigenen
  nativen Adminstatus für ein bestätigtes natives Administrationskonto
  erteilen und widerrufen;
- native Admins ohne Mitgliedschaft in `Datenschutzbeauftragte` sowie andere
  Nichtmitglieder können weder erteilen noch widerrufen; abgewiesene und
  manipulierte Mutationen verändern keine Freigabe oder Historie;
- mehr als 24 Stunden, manipulierte UIDs/Zeiten und unbekannte Konten werden
  ohne Mutation abgewiesen;
- Aktivierung und Widerruf erzeugen die erwartete Historie;
- die Eintrittsmeldung und ihr Direktlink folgen der vorstehenden
  Rollenmatrix und legen gewöhnlichen oder anderen unberechtigten Konten
  keine administrativen Zustände offen;
- die sechsmonatige Standardfrist und eine geänderte Frist werden für neue
  und vorhandene Historieneinträge anhand des tatsächlichen Endes korrekt
  berechnet, ohne vor Freigabe der Maßnahme Daten zu verändern;
- App-spezifische Fachrechte normaler Rollen bleiben unverändert.

Additive Migrationen verändern keine Bestandsfachdaten. Bei Rückbau wird die
vorherige App-Version wiederhergestellt; die neue Audit-Historie bleibt
unangetastet und erteilt ohne ausführenden Freigabecode keine Rechte.

## Klassifikation und Folgen

| Bestandteil | Eigentümer / Consumer | Evidenz | Kategorie | Grund und Zielstruktur | Risiko / Store-Auswirkung |
| --- | --- | --- | --- | --- | --- |
| Aktive Freigaben, Audit, Freigabesteuerung, Eintrittsmeldung und serverseitige Prüfung | jeweilige Fachapp | verifiziert durch ausdrückliche Produktentscheidung | C | app-eigene Daten und Fachrechte; keine zentrale Runtime oder Persistenz | app-lokale Autorisierungs-, UI-, Audit-, Provider- und Allow-/Deny-/Manipulationstests erforderlich; keine neue Store-Abhängigkeit |
| Gemeinsame Invariante und Prüfvorgaben | Parent und alle eigenen Apps | verifiziert | kein Runtime-Code | dieser ADR ist die einzige app-übergreifende Regelquelle; lokale Projektionen bleiben synchron | Cross-App-Vertragstests müssen Abweichungen sichtbar machen |

Die lokale Implementierung ist eine bewusste, nicht triviale Duplikation. Sie
ist gewollt, weil App-Zweck, fachliche Vollzugriffsflächen, bestehende
Berechtigungsservices, Migrationen, Audit- und Providerprojektionen
unterschiedliche Änderungsgründe besitzen. Gemeinsame Runtime-Klassen oder
eine zentrale Datenhaltung würden die eigenständige Installation und lokale
Verantwortung unnötig koppeln.
