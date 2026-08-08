---
name: create-nextcloud-app
description: Create and wire a new, separately versioned Nextcloud app repository in this workspace. Use only when the user explicitly asks to create a new app or complete its Parent/DDEV registration; do not use for features in an existing app, speculative scaffolding, or shared-library extraction.
---

# Create a Nextcloud app repository

## Preconditions

1. Read the Parent `AGENTS.md` and `docs/workspace.md` completely.
2. Confirm the requested product area, app ID, repository directory, local URL, and DDEV mount name from the request. Stop if the app identity or repository boundary requires a product decision.
3. Check `git status --short` in the Parent and verify that the target directory does not contain unrelated work.
4. Do not install dependencies, change a running Nextcloud instance, initialize remote hosting, or create a commit without explicit authorization.
5. Read and apply
   `docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`.
   Record exactly one architecture choice for the new app:
   - ohne gemeinsame Laufzeitabhängigkeit;
   - mit gebundelter gemeinsamer Bibliothek;
   - mit begründeter Abhängigkeit zu einer anderen Nextcloud-App;
   - noch nicht entscheidbar.
   The last choice blocks completion of runtime and release wiring. Never add
   a LocalBase dependency by default.
6. If personal data is already part of the requested scope, read
   `docs/privacy-architecture.md` completely and identify the real subject
   types and person references before choosing a provider or retention design.

## Workflow

1. Create the app directory beside the existing app repositories and initialize its own Git repository.
2. Add an app-local `AGENTS.md` containing only the stable goal, local architecture/rights/test rules, stop conditions, and Definition of Done.
   Include the recorded shared-code/runtime architecture choice and its
   release consequence without copying the full Parent decision.
3. Copy the canonical `.agents/skills/work-in-nextcloud-app/SKILL.md` and `.agents/skills/test-driven-change/SKILL.md` into the same relative paths in the new app as regular files. Do not use symlinks and do not add app-specific rules to the copied skills.
4. Add an app-local `.gitignore`. Keep deployable app code exclusively in the app repository.
5. Add the app to `config/workspace-repositories.tsv` with kind `app`, its app ID, and required skills `work-in-nextcloud-app,test-driven-change`. This manifest entry is the canonical repository registration.
6. Add the app directory to the Parent `.gitignore`.
7. Add `nextcloud-dev/.ddev/docker-compose.<app-id>.yaml` with a lowercase host path below the locally determined workspace root and the mount `/var/www/html/html/custom_apps/<app-id>`. Do not encode a personal home directory in the workflow.
8. Update `docs/workspace.md` with app, URL, repository, mount, and DDEV configuration. Update the Root `AGENTS.md` only if a new durable cross-app invariant is introduced.
9. Add the app folder to `br-nextcloud-apps.code-workspace` when that file is the active workspace catalog.
10. When personal data belongs to the app scope, add an app-local planning
    task covering subject identifiers, `PersonalDataProvider`, third-person
    content, retention triggers, supported measures and tests according to
    `docs/privacy-architecture.md`. The provider need not be implemented in
    the initial scaffold, but the decision task must not disappear.
11. From the new Git root, confirm that `AGENTS.md` and both required local skills resolve locally, run the app's declared fast PHP and JavaScript checks, and inspect its Git status.
12. From the Parent, compare both local skills byte-for-byte with their canonical skills and run the structure checks. Run state-changing DDEV or `occ app:enable` only when explicitly requested or approved.

## Verification

- In the Parent: `scripts/check-fast`, `git diff --check`, `git status --short`, `git diff --stat`, and `git diff --name-only`.
- In the new app repository: its declared fast PHP/JavaScript tests and the same Git inspection commands.
- Confirm from the new app Git root that `AGENTS.md` and both local skills are discoverable without Parent or user-level skill paths.
- Confirm byte equality of both skills with `cmp` or the manifest-driven structure check.
- For an app with personal data in scope, confirm that its local planning
  names the open or implemented privacy-provider and retention work without
  claiming the Root runtime already exists.
- Run `REQUIRE_TRACKED_STRUCTURE=1 scripts/check-workspace-structure`; every new Parent and app control file must be tracked in its owning repository before the workflow can pass.
- Confirm the Parent does not track the new app's deployable files.
- If the app was activated with approval, verify `occ status`, `occ app:list`, expected migrations/jobs, an app CSS/JavaScript asset, and the visible UI.

Do not report a new app as complete while its local `AGENTS.md`, local skill copies, manifest registration, byte-equality checks, local discoverability checks, tracking gate, or app-local fast check is missing or failing. Stop and report instead of guessing when app ID, product ownership, data model, permission model, or cross-app contracts are unresolved.
