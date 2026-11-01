# ADR 0004: App-lokaler, zeitlich begrenzter Admin-Vollzugriff

- Status: angenommen
- Entscheidung: 2026-08-25
- Fortgeschrieben: 2026-11-01
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

### Ausdrücklich beschlossene App-Ausnahme: FlzPlaner

Für `flzplaner` ist am 29. Oktober 2026 eine eng begrenzte Ausnahme von der
sonst ausschließlich der Gruppe `Datenschutzbeauftragte` zugewiesenen
Freigabeverwaltung beschlossen worden. Zusätzlich darf eine aktuell zuständige
Einsatzbegleitung den app-lokalen temporären Admin-Vollzugriff erteilen,
widerrufen und die dazugehörige Historie einsehen.

Eine zuständige Einsatzbegleitung liegt nur vor, wenn bei jeder einzelnen
Lese-, Freigabe- und Widerrufsaktion serverseitig beide Bedingungen zugleich
aktuell bestätigt werden:

- Mitgliedschaft in der kanonischen, aus dem Organisationsvertrag bezogenen
  EB-Rollengruppe; und
- Mitgliedschaft in mindestens einer aktuell vom Planer als Assistenzteam
  ermittelten Gruppe.

Die beiden Mitgliedschaften werden getrennt aus den aktuellen
Nextcloud-Gruppenzuordnungen ausgewertet. Eine frei benannte, abgeleitete oder
nur dem Namensschema ähnliche Kombinationsgruppe erteilt kein Recht. Fehlt die
EB-Rolle oder die aktuelle Teammitgliedschaft, ist die Teamgruppe unbekannt
oder manipuliert, oder schlagen Organisations-, Gruppen- oder
Quellenprüfungen fehl, bleibt die gesamte Freigabeverwaltung einschließlich
Historienzugriff fail-closed gesperrt. UI-Sichtbarkeit ersetzt diese Prüfung
nicht.

Diese Ausnahme erteilt ausschließlich `plan.admin-grant.manage`. Sie gibt der
Einsatzbegleitung selbst keinen fachlichen Admin-Vollzugriff, keine Rechte an
fremden Planungsdaten, keine Retention- oder Hold-Verwaltung und keine Rechte
in anderen Apps. Da die app-lokale Vollzugriffsfreigabe ein appweites und kein
teambezogenes Zielobjekt ist, darf eine aktuell bestätigte zuständige
Einsatzbegleitung sie für ein aktuell bestätigtes natives
Administrationskonto appweit erteilen oder widerrufen; daraus entstehen keine
weiteren teamübergreifenden Planerrechte.

Verliert eine erteilende Einsatzbegleitung später ihre EB-Rolle oder ihre
letzte aktuelle Teammitgliedschaft, verliert sie sofort den Zugriff auf
Steuerung und Historie und kann nicht mehr erteilen oder widerrufen. Eine
bereits wirksam erteilte Freigabe des Ziel-Admins wird dadurch nicht
rückwirkend verändert: Sie endet weiterhin spätestens nach 24 Stunden, durch
Verlust des nativen Adminstatus oder durch den Widerruf eines zu diesem
Zeitpunkt berechtigten Mitglieds von `Datenschutzbeauftragte` beziehungsweise
einer aktuell zuständigen Einsatzbegleitung. Erteilung und Widerruf halten
jeweils die tatsächlich handelnde UID in der app-lokalen Auditquelle fest.

Die Ausnahme gilt nur für `flzplaner` und darf weder auf andere Apps noch auf
weitere Planerberechtigungen verallgemeinert werden. Zusätzlich zu den
allgemeinen Nachweisen dieses ADR muss FlzPlaner automatisiert belegen:

- Erteilen, Widerrufen und Historienzugriff funktionieren für ein
  `Datenschutzbeauftragte`-Mitglied sowie für die aktuelle Schnittmenge aus
  EB-Rolle und einer bekannten aktuellen Teamgruppe;
- reine EB-Rolle, reine Teammitgliedschaft, unbekannte oder manipulierte
  Teamgruppen, native Administration allein und gewöhnliche Konten bleiben
  ohne Zugriff und ohne Mutation;
- Fehler der Organisations-, Gruppen- oder Berechtigungsquelle sowie der
  Verlust einer der beiden Mitgliedschaften wirken bei der nächsten Aktion
  fail-closed;
- abgewiesene Erteilungs- und Widerrufsversuche verändern weder aktive
  Freigaben noch Historie, während erfolgreiche Aktionen die handelnde UID
  korrekt auditieren; und
- der PermissionProvider projiziert die Oder-Verknüpfung aus
  `Datenschutzbeauftragte` und den zulässigen Team-und-EB-Schnittmengen, ohne
  daraus eine neue Organisationsgruppe oder ein fachliches Vollrecht
  abzuleiten.

### Ausdrücklich beschlossene App-Ausnahme: Filzmann Kalender

Für `flzcalendar` ist am 29. Oktober 2026 eine eng begrenzte Ausnahme
beschlossen worden. Ein bei jedem Zugriff aktuell bestätigtes natives
Nextcloud-Administrationskonto erhält in dieser App ohne zusätzliche
app-lokale temporäre Freigabe `calendar.entry.manage-all`. Die Ausnahme umfasst
das Lesen und Verwalten aller Filzmann-Kalendereinträge sowie die Installation
des ausschließlich synthetischen Kalender-Demo-Packs. Technische OAuth-,
CalDAV- und Kalenderdefault-Konfiguration bleibt wie zuvor native
Administration. Persönliche Konnektoren bleiben ausschließlich für das eigene
angemeldete Konto verwaltbar.

Die Ausnahme gilt nur für `flzcalendar`. Sie erteilt keine Rechte in einer
anderen Fachapp, erweitert keine Organisationsrolle und ändert weder deren
Freigabe-, Audit- noch Datenschutzvertrag. Folgende Schutzmaßnahmen bleiben
verbindlich:

- Der native Adminstatus wird bei jedem fachlichen Zugriff serverseitig über
  Nextclouds Gruppenverwaltung erneut geprüft. Navigation und UI-Sichtbarkeit
  erteilen keine Rechte.
- Normale Kalenderkonten benötigen weiterhin eine kanonische Organigrammrolle.
  Fremdbearbeitung durch Fachrollen entsteht ausschließlich über direkte
  Hierarchiekanten oder ausdrücklich aktivierte Peer-Berechtigungen mit deren
  Bereichsgrenzen.
- Gemeinsame Meetingmutationen leiten kein eigenes Vollrecht ab, sondern
  verlangen das Bearbeitungsrecht für jeden beteiligten Kalender.
- Der app-eigene PermissionProvider weist die Ausnahme ausdrücklich als
  `nextcloud-admin` aus; die Berechtigungsmatrix darf sie nicht als
  gruppenbezogene Fachrolle darstellen.
- Die frühere Kalender-Freigabesteuerung besitzt keine UI, Route oder
  schreibende beziehungsweise autorisierende Laufzeitverwendung mehr. Bereits
  vorhandene Historieneinträge bleiben unverändert, erteilen niemals Rechte
  und werden ausschließlich subjectgebunden read-only über den
  PersonalDataProvider projiziert. Ihre beschlossene Retention bleibt davon
  unberührt.

Eine Ausweitung dieser Ausnahme auf eine weitere App oder einen weiteren
fachlichen Scope benötigt eine neue ausdrückliche Entscheidung und
Fortschreibung dieses ADR. Die allgemeinen Regeln dieses ADR bleiben für alle
anderen Apps unverändert.

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
| Planer-Ausnahme für die Freigabeverwaltung durch aktuelle Team-und-EB-Schnittmengen | `flzplaner` | verifiziert durch ausdrückliche Produktentscheidung vom 29. Oktober 2026 | C | eng begrenzte app-lokale Ausnahme nur für `plan.admin-grant.manage`; aktuelle Organisations- und Gruppenprüfung bleibt fail-closed | Allow-/Deny-, Rollenverlust-, Quellenfehler-, Mutationsfreiheits-, Audit- und PermissionProvider-Nachweise erforderlich; keine Wirkung auf andere Planerrechte oder Apps |
| Native Kalender-Adminausnahme und read-only erhaltene frühere Freigabehistorie | `flzcalendar` | verifiziert durch ausdrückliche Produktentscheidung vom 29. Oktober 2026 | C | eng begrenzte App-Ausnahme; aktueller nativer Adminstatus ist die einzige Vollzugriffsbedingung, frühere Freigaben bleiben ohne Autorisierungswirkung app-lokal | Server-Allow-/Deny-, Rollenverlust-, Fremdobjekt-, Meeting-Atomaritäts-, Provider- und Runtimeprüfungen erforderlich; keine Wirkung auf andere Apps |
| Gemeinsame Invariante und Prüfvorgaben | Parent und alle eigenen Apps | verifiziert | kein Runtime-Code | dieser ADR ist die einzige app-übergreifende Regelquelle; lokale Projektionen bleiben synchron | Cross-App-Vertragstests müssen Abweichungen sichtbar machen |

Die lokale Implementierung ist eine bewusste, nicht triviale Duplikation. Sie
ist gewollt, weil App-Zweck, fachliche Vollzugriffsflächen, bestehende
Berechtigungsservices, Migrationen, Audit- und Providerprojektionen
unterschiedliche Änderungsgründe besitzen. Gemeinsame Runtime-Klassen oder
eine zentrale Datenhaltung würden die eigenständige Installation und lokale
Verantwortung unnötig koppeln.
