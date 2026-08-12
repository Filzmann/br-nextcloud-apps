---
name: classify-shared-code
description: Classify proposed or existing cross-app code as a bundled library, an independent Nextcloud runtime app, or intentionally local code. Use for LocalBase analysis, shared-library extraction, moves between existing app repositories, new public cross-app contracts, or App-Store dependency reviews; do not use for ordinary single-app work with no shared-code or runtime-boundary decision.
---

# Classify shared code and runtime boundaries

## Establish scope

1. Work from the Parent root. Read `AGENTS.md` and
   `docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`
   completely.
2. Name every repository under analysis. Read its complete local `AGENTS.md`,
   locally referenced skills, and `git status --short` before inspecting it.
3. Treat this as read-only analysis unless the request expressly authorizes
   changes in every named repository. A Parent architecture decision does not
   authorize product-code or app-local control-file changes.
4. Stop before implementing public contracts, database or AppConfig
   migrations, permission changes, or cross-repository code movement. State
   affected files, tests, data/compatibility risk, and rollback, then obtain
   the approval required by the repository instructions.

## Build evidence

For each candidate component, record concrete definitions and call sites:

- current owner and source path;
- real runtime and test consumers;
- PHP autoload/DI, event, route/API, template/asset, package, build, or relative
  source-path coupling;
- tables, migrations, AppConfig/IUserConfig, files, jobs, administration,
  capabilities, public services, APIs and leading data sources;
- direct foreign-table, private configuration, internal class, controller,
  asset or filesystem access;
- behavior when a provider is missing, disabled or version-incompatible; and
- installation, update, deinstallation, rollback and release-package effects.

Label each statement `verifiziert`, `plausible Einordnung`, `unklar`, or
`derzeit nicht entscheidbar`. Do not infer consumers from naming alone.

## Decide A, B, or C

Before moving code to LocalBase or a shared package, prove all of the
following:

1. at least two concrete real uses;
2. identical semantics, including relevant negative and boundary cases;
3. the same reason to change, not merely similar implementation;
4. no unnecessary runtime coupling or cyclic dependency;
5. no direct foreign-table or private AppConfig, controller, asset, route or
   file-structure dependency;
6. clear source ownership, versioning and compatibility responsibility; and
7. understood effects on installation, update, deinstallation, rollback,
   consumer tests and release archives.

Classify only after that proof:

- **A — bundled library:** no independent Nextcloud lifecycle, central runtime
  data, administration or job. Require reproducible locked builds,
  app-specific namespace/global isolation, bundled production files, license
  inventory and consumer releases for updates.
- **B — independent Nextcloud runtime app:** shared state, leading data,
  migrations/jobs, central configuration/permissions/services or
  administration. Require a separately versioned app and small documented
  public APIs with a controlled missing/incompatible-provider result.
- **C — intentionally local:** semantics or change reasons differ, the code is
  trivial, or abstraction costs more than duplication. Document non-trivial
  local duplication.

If the evidence is incomplete, retain `noch zu prüfen`; do not extract code.
Never use the current LocalBase location as the classification reason.

## Evaluate delivery

Distinguish Quellcode-Abhängigkeit, gebundelte Produktionsabhängigkeit and
externe Nextcloud-App-Laufzeitabhängigkeit. For an App-Store candidate, require
one app root, all A runtime files inside it, no embedded second Nextcloud app,
documented and justified B prerequisites, license/attribution completeness,
neutral public names and examples, reproducible source/lock evidence, and a
clean-install test with exactly the declared prerequisites.

Automate only package facts that a deterministic check can prove. Keep
semantic identity, public suitability, runtime-app necessity and absence of
hidden coupling as explicit review decisions.

## Report

Return a component table with owner, consumers, evidence status, A/B/C
classification, reason, target structure, migration risk, Store impact and
priority. Separate implemented and verified facts from documented future
work, recommendations and unresolved decisions. Propose small sequential
migration steps; do not describe a planned extraction as completed.
