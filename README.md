# BR Nextcloud Apps

Dieser Parent-Workspace koordiniert die lokale Nextcloud-Entwicklungsumgebung,
gemeinsame App-Verträge und die getrennt versionierten Nextcloud-App-
Repositories. Er enthält selbst keinen deploybaren App-Code.

## Einstieg

- Repository- und DDEV-Überblick: [`docs/workspace.md`](docs/workspace.md)
- App-übergreifende Architektur: [`docs/architecture.md`](docs/architecture.md)
- Kanonischer systemweiter Zukunftsplan für openDesk-/Nextcloud-
  Future-Readiness, Cross-App-Rollouts und Suite-Module:
  [`docs/zukunftsplan.md`](docs/zukunftsplan.md)
- Datenschutzarchitektur und schrittweiser Rollout:
  [`docs/privacy-architecture.md`](docs/privacy-architecture.md)
- Maschinenlesbares Root-Schema für app-eigene Processing-Metadaten:
  [`docs/contracts/privacy-processing-metadata.schema.json`](docs/contracts/privacy-processing-metadata.schema.json)
- Öffentlicher Leitfaden für Privacy-Provider:
  [`docs/privacy-provider-guide.md`](docs/privacy-provider-guide.md)
- Portfolioentscheidung zur Berechtigungsmatrix:
  [`ADR 0003`](docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md)
- Verbindliche Codex-Grenzen: [`AGENTS.md`](AGENTS.md)
- Einheitliche App-Repository-Struktur:
  [`docs/app-repository-structure.md`](docs/app-repository-structure.md)
- Wiederholbare Arbeitsabläufe: [`.agents/skills/`](.agents/skills/)
- Kanonisches Repositoryinventar:
  [`config/workspace-repositories.tsv`](config/workspace-repositories.tsv)
- Offene, unverbindliche Learning Candidates:
  [`docs/learning-candidates.md`](docs/learning-candidates.md)

Abgeschlossene Planstände werden nicht als zweite Dokumentwahrheit gepflegt.
Ihr Nachweis liegt in Code, Tests, ADRs und der Git-Historie; offene Arbeit
steht ausschließlich im systemweiten Zukunftsplan oder in der zuständigen
App-Roadmap.

Normale App-Arbeit beginnt im Root des betroffenen App-Repositories. Dort
gelten die lokale `AGENTS.md` und die lokal mitgeführten Skills
`work-in-nextcloud-app` sowie `test-driven-change`.

## Schnelle Prüfungen

Je nach Umfang einen Einstieg wählen:

```bash
scripts/check-fast  # Parent
# oder einschließlich aller registrierten App-Tests:
scripts/check-full  # enthält check-fast bereits
```

`check-full` ist kein Releaseurteil. Das saubere AD-Suite-Delivery-Gate ist
`scripts/check-ad-suite-delivery` und wird nur für ausdrücklich beauftragte
Delivery-Arbeit verwendet.
