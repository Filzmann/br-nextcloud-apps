---
name: build-ad-suite-release
description: Build or validate AD-Suite and standalone AD-product release bundles with the repository's existing scripts. Use for explicit release-candidate, delivery-gate, installer, manifest, signing, or package requests; do not use for ordinary app development, production deployment, or publishing without explicit authorization.
---

# Build and validate AD-Suite releases

## Safety gates

1. Read Root `AGENTS.md`, `docs/workspace.md`, `ad-suite/AGENTS.md`, and the `AGENTS.md` of every included app.
   Read and apply
   `docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md`.
2. Before a release candidate may be published or handed off as publishable, use the sibling skill `verify-nextcloud-future-compatibility` against the exact included commits. Require its green publication verdict, align every `info.xml` range and the suite ceiling, and rerun it when an upstream moving ref or candidate commit changed. An exploratory failure outside the declared range is acceptable only under that skill's limited-ceiling rule; a declared or explicitly targeted failure blocks the release.
3. Inspect status in Parent, `ad-suite`, `localbase`, `orgsuite`, `adcalendar`, `adplaner`, `adurlaub`, `adroom`, and `adrecruitment`.
4. Stop on dirty included repositories unless the user explicitly accepts a non-release diagnostic build. Do not silently set `ALLOW_DIRTY=1` or `SKIP_TESTS=1`.
5. Stop the release workflow if the future-compatibility gate, any required fast test, or the applicable target-specific Delivery Gate is red or unverified. A diagnostic build may expose a failure but must not be described or handed off as a release.
6. Building local artifacts is not authorization to sign, publish, deploy, enable apps, upgrade Nextcloud, or contact a production/staging system.
7. Signing requires explicit authorization and user-provided `SIGNING_KEY_DIR`/`NEXTCLOUD_ROOT`; never inspect or expose private key contents.

## Workflow

1. For a release candidate intended for publication, complete `verify-nextcloud-future-compatibility` first and apply its approved metadata and release-contract updates. A diagnostic build may skip this only when it is explicitly labelled non-publishable.
2. Select and name the delivery target before running a gate:
   - **Private AD-Suite/product delivery:** run
     `scripts/check-ad-suite-delivery`, then for an explicitly requested build
     choose a unique `RELEASE_LABEL` and run
     `scripts/build-ad-suite-release.sh`. This is the existing multi-app path.
   - **Official App-Store single-app candidate:** work from the named app
     repository, read its local instructions, and require its documented
     reproducible production-build command and app-local Store gate.
     Do not run or cite the AD-Suite builder or Delivery Gate as proof for this artifact.
     The Parent currently has no generic App-Store builder. If the app has no
     truthful build/gate that produces and tests exactly one app-root archive,
     stop and report that no publishable Store candidate can be produced.
3. For the private AD-Suite path, the clean Delivery Gate runs the strict
   tracked `scripts/check-fast` path exactly once, so do not prepend a duplicate
   fast run. `scripts/check-full` is deliberately not a release verdict.
4. For either authorized build path, keep generated archives under ignored
   build output; do not stage them in Git.
5. For the private AD-Suite path, validate the generated `manifest.tsv`,
   `SHA256SUMS`, outer `.sha256`, nested archive roots, mandatory documentation,
   and absence of `.git`, tests, `AGENTS.md`, symlinks, secrets, internal paths,
   and foreign products.
6. For an official App-Store candidate, validate the exact app-local output:
   one lower-case app root matching `appinfo/info.xml`, no second app root,
   required production files, no Root control/development/secret files, and
   the archive checks implemented by the named app's own Store gate. Do not
   infer a green Store verdict from private-suite manifests or checksums.
7. For every app, classify each dependency as Quellcode-Abhängigkeit,
   gebundelte Produktionsabhängigkeit, or externe Nextcloud-App-Laufzeitabhängigkeit.
   Verify that production dependencies were built, applicable Lock-Dateien
   are present and consistent, and required runtime
   files are contained below the app's single root. Reject a zweite App-Wurzel
   inside an App-Store archive.
8. Require an explicit review that documented runtime-app dependencies match
   the code, no direkten Zugriffe auf Datenbanktabellen anderer Apps or other
   private internals exist, Lizenzinformationen for bundled dependencies are
   complete, and the app passed a sauberen Installation with exactly its
   documented prerequisites. Static grep evidence alone is not a complete
   verdict for these review decisions.
9. The current private AD-Suite product bundles contain `localbase`,
   `orgsuite`, and exactly one requested AD Fachprodukt; the full suite contains
   all supported products. This multi-app delivery model is not an official
   single-app App-Store archive and must not be reported as one.
10. If authorized staging acceptance is in scope, use the real hosting configuration: domain CLI-PHP, configured app paths, PHP-FPM/domain user, and Static-Webserver user/group. Apply only minimal permission changes.
11. A staging installation passes only after `occ` status/app checks, migrations/jobs where relevant, static-server readability, public HTTPS 200 plus correct Content-Type for at least one CSS and JavaScript asset, visible UI, rollback, privacy, and fachliche acceptance checks.

## Reporting

List labels, artifact paths, manifest/checksum results, signing state, future-compatibility evidence and per-app/suite ceilings, executed tests, skipped DDEV/HTTP/access checks, repository cleanliness, and any remaining production-release blockers. Never describe a release candidate as production-approved without Simon's explicit approval.
