# BR Nextcloud Apps

Dieser Parent-Workspace koordiniert die lokale Nextcloud-Entwicklungsumgebung,
gemeinsame App-Verträge und die getrennt versionierten Nextcloud-App-
Repositories. Er enthält selbst keinen deploybaren App-Code.

## Einstieg

- Repository- und DDEV-Überblick: [`docs/workspace.md`](docs/workspace.md)
- App-übergreifende Architektur: [`docs/architecture.md`](docs/architecture.md)
- Datenschutzarchitektur und schrittweiser Rollout:
  [`docs/privacy-architecture.md`](docs/privacy-architecture.md)
- Verbindliche Codex-Grenzen: [`AGENTS.md`](AGENTS.md)
- Wiederholbare Arbeitsabläufe: [`.agents/skills/`](.agents/skills/)
- Kanonisches Repositoryinventar:
  [`config/workspace-repositories.tsv`](config/workspace-repositories.tsv)
- Offene, unverbindliche Learning Candidates:
  [`docs/learning-candidates.md`](docs/learning-candidates.md)
- Freigegebene Parent-Umsetzungsaufgaben:
  [`docs/implementation-tasks.md`](docs/implementation-tasks.md)

Normale App-Arbeit beginnt im Root des betroffenen App-Repositories. Dort
gelten die lokale `AGENTS.md` und die lokal mitgeführten Skills
`work-in-nextcloud-app` sowie `test-driven-change`.

## Schnelle Prüfungen

```bash
scripts/check-fast
scripts/check-full
```

`check-full` ist kein Releaseurteil. Das saubere AD-Suite-Delivery-Gate ist
`scripts/check-ad-suite-delivery` und wird nur für ausdrücklich beauftragte
Delivery-Arbeit verwendet.
