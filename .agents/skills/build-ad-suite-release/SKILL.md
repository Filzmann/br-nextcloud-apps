---
name: build-ad-suite-release
description: Build or validate AD-Suite and standalone AD-product release bundles with the repository's existing scripts. Use for explicit release-candidate, delivery-gate, installer, manifest, signing, or package requests; do not use for ordinary app development, production deployment, or publishing without explicit authorization.
---

# Build and validate AD-Suite releases

## Safety gates

1. Read Root `AGENTS.md`, `docs/workspace.md`, `ad-suite/AGENTS.md`, and the `AGENTS.md` of every included app.
2. Before a release candidate may be published or handed off as publishable, use the sibling skill `verify-nextcloud-future-compatibility` against the exact included commits. Require its green publication verdict, align every `info.xml` range and the suite ceiling, and rerun it when an upstream moving ref or candidate commit changed. An exploratory failure outside the declared range is acceptable only under that skill's limited-ceiling rule; a declared or explicitly targeted failure blocks the release.
3. Inspect status in Parent, `ad-suite`, `localbase`, `orgsuite`, `adcalendar`, `adplaner`, `adurlaub`, and `adroom`.
4. Stop on dirty included repositories unless the user explicitly accepts a non-release diagnostic build. Do not silently set `ALLOW_DIRTY=1` or `SKIP_TESTS=1`.
5. Stop the release workflow if the future-compatibility gate, any required fast test, or the Delivery Gate is red or unverified. A diagnostic build may expose a failure but must not be described or handed off as a release.
6. Building local artifacts is not authorization to sign, publish, deploy, enable apps, upgrade Nextcloud, or contact a production/staging system.
7. Signing requires explicit authorization and user-provided `SIGNING_KEY_DIR`/`NEXTCLOUD_ROOT`; never inspect or expose private key contents.

## Workflow

1. For a release candidate intended for publication, complete `verify-nextcloud-future-compatibility` first and apply its approved metadata and release-contract updates. A diagnostic build may skip this only when it is explicitly labelled non-publishable.
2. Run `scripts/check-ad-suite-delivery` for the actual clean local Delivery Gate. It runs the strict tracked `scripts/check-fast` path exactly once before the delivery-specific checks, so do not prepend a duplicate fast run. `scripts/check-full` validates the whole app workspace but is deliberately not a release verdict.
3. For an explicitly requested release build, choose a unique `RELEASE_LABEL` and run `scripts/build-ad-suite-release.sh`. Existing targets are intentionally not overwritten.
4. Keep generated archives under ignored `dist/`; do not stage them in Git.
5. Validate the generated `manifest.tsv`, `SHA256SUMS`, outer `.sha256`, one-root-folder archive contract, mandatory documentation, and absence of `.git`, tests, `AGENTS.md`, symlinks, secrets, internal paths, and foreign products.
6. Standalone product bundles contain `localbase`, `orgsuite`, and exactly one requested AD Fachprodukt. The full suite contains all supported products.
7. If authorized staging acceptance is in scope, use the real hosting configuration: domain CLI-PHP, configured app paths, PHP-FPM/domain user, and Static-Webserver user/group. Apply only minimal permission changes.
8. A staging installation passes only after `occ` status/app checks, migrations/jobs where relevant, static-server readability, public HTTPS 200 plus correct Content-Type for at least one CSS and JavaScript asset, visible UI, rollback, privacy, and fachliche acceptance checks.

## Reporting

List labels, artifact paths, manifest/checksum results, signing state, future-compatibility evidence and per-app/suite ceilings, executed tests, skipped DDEV/HTTP/access checks, repository cleanliness, and any remaining production-release blockers. Never describe a release candidate as production-approved without Simon's explicit approval.
