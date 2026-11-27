# Inventar öffentlicher V1-Vertragskandidaten (FR-02)

Stand: 27. November 2026. Arbeitsergebnis zur FR-02-Entscheidung (P0): Welche
bestehenden Verträge zuerst als kleinste öffentliche V1-Grenze mit
Aktivierungs-/Versionshandshake, Fehlersemantik und additiver Kompatibilität
freigegeben und testbar gemacht werden. Keine zweite Datenquelle einführen.

| Vertrag | Anbieter | Consumer | Status | Nächste Entscheidung |
| --- | --- | --- | --- | --- |
| `CalendarContextProvider` V1 | flz_organization | flzcalendar, flzurlaub, flz_kapazitaet | dokumentiert in `flz_organization/docs/public-api-v1.md` | kleinste V1-Grenze + Handshake + fail-closed Fehlersemantik als Contract-Tests |
| `HolidayCalendarProvider` V1 | flz_organization | flzcalendar, flzurlaub | dokumentiert in `public-api-v1.md` | Cache-Status (`stale`/`unavailable`) als Vertragsbestandteil bestätigen |
| `OrganizationSnapshot` V1 | localbase | BR-Apps, flz_organization (Spiegel) | implementiert | Aktivierungs-/Versionshandshake und additive Kompatibilität entscheiden |
| `VacationAuthorizationSnapshot` V1 | localbase | flzurlaub, flzcalendar | implementiert | V1-Grenze und Fehlersemantik festschreiben |
| `BrMembershipSnapshot` V1 | localbase | brtop, brstunden | implementiert | dasselbe |
| `AbsenceQueryEvent` / `ScheduleConflictQueryEvent` / `IntegrationCapabilityQueryEvent` V1 | flz_organization | flzurlaub, flzcalendar | implementiert (Event-Vertrag) | fail-closed Verhalten bei fehlendem Provider als Contract-Test |
| `CapacityQueryEvent` V1 + `AssistantContextQueryEvent` V1 | flz_kapazitaet | flz_akut | implementiert, fail-closed | öffentlichen V1-Vertrag inkl. Handshake dokumentieren |
| `PersonalDataProvider` / `ProcessingMetadataProvider` / `RetentionProvider` V1 | jeweilige Fachapp → flz_data_protection | flz_data_protection (Standalone-V1-Zielruntime, ADR 0002) | implementiert | Provider-/Consumer-Contract-Tests je App vervollständigen (DP-08) |
| `SuiteTargetCatalog` (FLZ-/BR-Ziele) | orgsuite | Fachapps (Navigation) | implementiert | öffentlicher Katalogvertrag mit Aktivierungsprüfung |

Nächster Schritt je Vertrag: kleinste öffentliche V1-Grenze, Handshake,
Fehlersemantik und additive Kompatibilität in Provider- und Consumer-Repos
als Contract-Tests nachweisen, bevor die Grenze offiziell freigegeben wird.
