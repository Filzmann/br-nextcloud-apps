# Compatibility evidence contract

Use one report for one immutable release-candidate revision set. Store it in
the ignored release workspace or another explicitly chosen non-secret artifact
location; do not add generated reports to an app repository by default.

## 1. Scope

Record:

- release-candidate label and UTC timestamp;
- every app ID, repository URL, branch, and commit;
- original `min-version` and `max-version`;
- official lifecycle status and testability of the declared minimum major;
- requested publication target and whether the run is diagnostic or a gate;
- approvals for app, Parent, DDEV/`occ`, staging, or external-state writes.

## 2. Upstream identity

For every tested major, record a row with:

| Field | Required value |
| --- | --- |
| Major | Integer Nextcloud major |
| Status | released or officially upcoming |
| Server ref | Exact tag, `stableNN`, or `master` |
| Server commit | Full commit ID |
| Version proof | Major read from the pinned `version.php` |
| Release notes | Official developer-manual URL |
| OCP source | Matching package version/ref and resolved commit or checksum |
| Retrieval time | UTC time refs were fetched |

For each additional Nextcloud-owned dependency or direct integration, record
its repository, why it is relevant, the compatibility mapping to the server
major, its ref and commit, and the changelog or migration source reviewed.

## 3. Per-app, per-major results

Use `green`, `red`, or `unverified`; never collapse skipped work into green.
Record command, exit code, and artifact/log location for each applicable row:

| Stage | Minimum evidence |
| --- | --- |
| Metadata | XML schema validation and effective dependency range |
| Source review | Critical changes, deprecations, and mapped repo changes |
| PHP static | Lint plus analysis against the matching OCP API |
| JavaScript static | Lint/build/tests against the app's locked dependencies |
| App tests | Complete repository-local PHP and JavaScript suites |
| Fresh install | Server install, app/dependency enable, DI and registration |
| Code check | Target server's `occ app:check-code`, if available |
| Runtime smoke | Relevant API, permission, job, UI, and asset checks |
| Upgrade | Previous major to target with synthetic existing data, if applicable |
| Combination | Standalone and suite/dependency combinations, if applicable |

For each red or unverified row, include the exact failure and whether it is an
app incompatibility, an upstream defect, an environment limitation, or missing
evidence. Do not waive a mandatory row inside the report.

## 4. Decision

Record:

- the first non-green major for each app;
- the highest contiguous green major for each app;
- whether a lower-bound review was triggered, its evidenced reason, and the
  explicit decision or unchanged lower boundary;
- whether each failed future major is inside the declared range, an explicit
  release target, or exploratory only, plus its publication impact;
- the suite ceiling, equal to the minimum of included app maxima;
- the proposed `info.xml` diff for each app;
- any Parent/installer/CI hard-coded version contract that must change;
- the post-update rerun and normal Delivery Gate result.

The verdict is `publishable compatibility gate: green` only when the report
matches the exact candidate commits, every declared major and explicit release
target is green, updated metadata matches the decision, and the standard clean
Delivery Gate is green. An exploratory failure above the truthful declared
ceiling is allowed only when it is recorded and no range gap is claimed. This
verdict does not authorize publishing or production use.

## 5. Mandatory negative cases

Reject the gate when any of these applies:

- a future major was inferred rather than named by official upstream sources;
- a branch name and `version.php` disagree;
- a moving ref was tested but its commit was not recorded;
- a major inside the claimed range failed or was skipped;
- `min-version` was raised or its tests were removed without a separately
  evidenced review and explicit approval;
- a temporary environment limitation was used as justification to drop an
  older Nextcloud major;
- a corresponding Nextcloud dependency repository could not be mapped;
- only documentation, lint, static analysis, or a page-load check was run;
- source metadata was changed before proof and the real updated source was not
  retested;
- an app maximum exceeds the lowest compatible required dependency;
- the report uses a different app commit from the packaged candidate;
- a hard-coded release check still enforces a stale maximum;
- a failed future target was treated as optional even though it was already
  declared or explicitly required for the release.
