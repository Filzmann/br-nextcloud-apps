---
name: verify-workspace
description: Select and run the established fast or full verification path for Parent, DDEV, cross-app, release, or delivery changes. Use after repository-local changes or when asked to validate the workspace; do not use as a substitute for an app repository's own tests or to mutate DDEV/Nextcloud state without approval.
---

# Verify this workspace

## Choose the level

- Use `scripts/check-workspace-structure` for the complete repository inventory, direct-start instruction chains, local skill synchronization, TOML, role sandboxing, symlinks, and tracking state.
- Use `scripts/check-fast` for Parent documentation, Codex structure, shell scripts, ignore rules, or focused Parent contract changes.
- Use `scripts/check-apps` for the fast PHP and JavaScript entries of all app
  repositories registered in `config/workspace-repositories.tsv`.
- Use `scripts/check-full` for the complete local Workspace path: Parent fast
  checks plus all registered app repositories. It is not an AD-Suite release
  verdict.
- Use `scripts/check-ad-suite-delivery` only for the actual clean AD-Suite delivery/release gate. Use its explicit `--diagnostic` mode only to inspect a dirty in-progress workspace; that mode must end without a release verdict.
- Run the affected app repository's `php tests/run.php` and/or `node tests/run-js.mjs` directly when only that app is in scope. Parent wrappers do not replace app-local requirements.

## Execution order

1. Read applicable `AGENTS.md` files and inspect `git status --short` in every in-scope repository.
2. Run the smallest relevant app-local syntax/unit tests.
3. For Parent or non-delivery Workspace validation, run `scripts/check-fast` from the Parent.
4. For a complete Workspace validation, run `scripts/check-full`; it already includes `check-fast` and does not build release artifacts.
5. For explicit delivery work, run the clean `scripts/check-ad-suite-delivery` directly. It runs the strict tracked `check-fast` path exactly once, so do not prepend a duplicate fast run. It fails on every dirty Parent, product-documentation, infrastructure, or included AD-product repository. If the user requested diagnosis rather than a release verdict, use `scripts/check-ad-suite-delivery --diagnostic`; never infer or hide that mode.
6. DDEV/HTTP/access checks remain opt-in through the existing variables:
   - `RUN_DDEV_CHECKS=1`
   - `RUN_HTTP_SMOKES=1` plus the documented base URL and credentials
   - `RUN_ACCESS_MATRICES=1`
7. Do not enable those checks unless the user authorized the required local/external state and credentials. Never print secret values.
8. `scripts/check-fast` runs `git diff --check` and rejects tracked backup, dump, coverage, cache, build, clearly forbidden secret, and obvious private-key files except explicitly reviewed allowlist entries.
9. Finish with complete status/diff lists and a manual content review for secrets; the filename check is intentionally not presented as a comprehensive secret scanner.

## Reporting

Report every command and result, distinguish failures from sandbox/Docker limitations, and label the outcome `teilweise geprüft` or `nicht vollständig verifiziert` when a relevant level could not run. A green fast/full Workspace check and a diagnostic delivery run are not substitutes for a clean Delivery Gate.
