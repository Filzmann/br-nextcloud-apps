---
name: create-nextcloud-app
description: Create and wire a new, separately versioned Nextcloud app repository in this workspace. Use only when the user explicitly asks to create a new app or complete its Parent/DDEV registration; do not use for features in an existing app, speculative scaffolding, or shared-library extraction.
---

# Create a Nextcloud app repository

## Preconditions

1. Read the Parent `AGENTS.md`, `docs/workspace.md`, and
   `docs/app-repository-structure.md` completely.
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
6. If personal data or app-specific permissions are already part of the
   requested scope, read `docs/privacy-architecture.md`,
   `docs/privacy-provider-guide.md`, and the public permission-provider V1
   guide in `flz_permission_matrix/docs/permission-provider-v1.md`.
   Identify the real subject types, person references, canonical permission
   source, and relevant secondary stores before choosing the provider design.

## Workflow

1. Create the app directory beside the existing app repositories and initialize its own Git repository.
2. Create the complete app-local documentation baseline from
   `docs/app-repository-structure.md`: `README.md`, `ROADMAP.md`,
   `CHANGELOG.md`, `LICENSE`, `AGENTS.md`, `docs/architecture.md`, and
   `docs/manual-acceptance.md`. Keep their responsibilities separate:
   current scope in README, only open work in ROADMAP, completed changes in
   CHANGELOG, detailed current architecture in the architecture document,
   repeatable manual checks in the acceptance document, and only binding
   work, security, architecture, stop, and verification rules in AGENTS.
   Add the standard documentation index to README and documentation
   responsibility section to AGENTS.
3. The app-local `AGENTS.md` includes the recorded shared-code/runtime
   architecture choice and its release consequence without copying the full
   Parent decision.
4. Copy the canonical `.agents/skills/work-in-nextcloud-app/SKILL.md` and `.agents/skills/test-driven-change/SKILL.md` into the same relative paths in the new app as regular files. Do not use symlinks and do not add app-specific rules to the copied skills.
5. Add an app-local `.gitignore` and `appinfo/info.xml`. Keep deployable app code exclusively in the app repository.
6. Add the app to `config/workspace-repositories.tsv` with kind `app`, its app ID, and required skills `work-in-nextcloud-app,test-driven-change`. This manifest entry is the canonical repository registration.
7. Add the app directory to the Parent `.gitignore`.
8. Add `nextcloud-dev/.ddev/docker-compose.<app-id>.yaml` with a lowercase host path below the locally determined workspace root and the mount `/var/www/html/html/custom_apps/<app-id>`. Do not encode a personal home directory in the workflow.
9. Update `docs/workspace.md` with app, URL, repository, mount, and DDEV configuration. Update the Root `AGENTS.md` only if a new durable cross-app invariant is introduced.
10. Add the app folder to `br-nextcloud-apps.code-workspace` when that file is the active workspace catalog.
11. Record an app-local provider decision for personal data and app-specific
    permissions. If either belongs to the initial scope, make the applicable
    `PersonalDataProvider` or `PermissionProvider`, its consumer contract
    test, relevant negative cases, and any honest partial-coverage warning
    part of the first affected feature. Do not report that feature or the
    first release complete while known relevant data, secondary stores,
    permission scopes, or subject types are omitted. If a provider is not
    applicable, document why from the app's purpose and re-evaluate the
    decision when its scope changes. Keep retention triggers, supported
    measures, and third-person content as explicit app-local work. Do not add
    a SQL, reflection, file, AppConfig, or migration-export fallback when a
    standalone provider consumer is missing.
12. From the new Git root, confirm that `AGENTS.md` and both required local skills resolve locally, run the app's declared fast PHP and JavaScript checks, and inspect its Git status.
13. From the Parent, compare both local skills byte-for-byte with their canonical skills and run the structure checks. Run state-changing DDEV or `occ app:enable` only when explicitly requested or approved.

## Verification

- In the Parent: `scripts/check-fast`, `git diff --check`, `git status --short`, `git diff --stat`, and `git diff --name-only`.
- In the new app repository: its declared fast PHP/JavaScript tests and the same Git inspection commands.
- Confirm from the new app Git root that `AGENTS.md` and both local skills are discoverable without Parent or user-level skill paths.
- Confirm byte equality of both skills with `cmp` or the manifest-driven structure check.
- For an app with personal data or app-specific permissions in scope, confirm
  that the applicable `PersonalDataProvider` and `PermissionProvider` plus
  their contract tests cover the implemented feature. Otherwise confirm an
  explicit, purpose-based not-applicable decision. Open retention work remains
  visible without claiming that the Root runtime already executes measures.
- Run `REQUIRE_TRACKED_STRUCTURE=1 scripts/check-workspace-structure`; every new Parent and app control file must be tracked in its owning repository before the workflow can pass.
- Confirm the Parent does not track the new app's deployable files.
- If the app was activated with approval, verify `occ status`, `occ app:list`, expected migrations/jobs, an app CSS/JavaScript asset, and the visible UI.

Do not report a new app as complete while its local `AGENTS.md`, local skill copies, manifest registration, byte-equality checks, local discoverability checks, tracking gate, or app-local fast check is missing or failing. Stop and report instead of guessing when app ID, product ownership, data model, permission model, or cross-app contracts are unresolved.
