#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
runner="$root/scripts/run-v2-retention-runtime-acceptance"
probe="$root/tests/runtime/v2-retention-runtime-probe.php"
fixture_dir="$(mktemp -d "${TMPDIR:-/tmp}/flz-v2-runner-contract.XXXXXX")"
trap 'rm -rf "$fixture_dir"' EXIT

fail() {
    echo "V2 retention runtime runner contract failed: $*" >&2
    exit 1
}

[[ -x "$runner" ]] || fail 'runner is missing or not executable'
[[ -f "$probe" ]] || fail 'runtime probe is missing'
bash -n "$runner"
php -l "$probe" >/dev/null

if "$runner" --validate-result "$fixture_dir/missing.json" >/dev/null 2>&1; then
    fail 'missing result file was accepted'
fi

printf '{"schemaVersion":1,"overall":"passed","scenarios":{}}\n' > "$fixture_dir/incomplete.json"
if "$runner" --validate-result "$fixture_dir/incomplete.json" >/dev/null 2>&1; then
    fail 'incomplete result was accepted'
fi

cat > "$fixture_dir/complete.json" <<'JSON'
{
  "schemaVersion": 1,
  "overall": "passed",
  "runtime": {"nextcloud": "34.0.2", "database": "PostgreSQL"},
  "scenarios": {
    "fresh_install": {"status": "passed"},
    "reinstall": {"status": "passed"},
    "provider_discovery": {"status": "passed"},
    "activation_fail_closed": {"status": "passed"},
    "synthetic_delete": {"status": "passed"},
    "job_restart_idempotency": {"status": "passed"},
    "concurrent_execution": {"status": "passed"},
    "hold_conflict": {"status": "passed"},
    "rollback_before_commit": {"status": "passed"},
    "restore_quarantine": {"status": "passed"},
    "upgrade_path": {"status": "unsupported", "reason": "No authoritative prior V2 runtime snapshot exists in this development-phase workspace."}
  }
}
JSON
"$runner" --validate-result "$fixture_dir/complete.json" >/dev/null

for required in \
    'mktemp -d' \
    'trap cleanup EXIT' \
    'timeout' \
    'ddev stop --unlist' \
    'overall' \
    'upgrade_path'; do
    grep -Fq "$required" "$runner" || fail "runner misses required safety marker: $required"
done

for phase in \
    inspect \
    seed-delete \
    execute-delete \
    verify-idempotent \
    seed-concurrent \
    execute-concurrent \
    verify-concurrent \
    seed-rollback \
    execute-rollback \
    verify-rollback \
    seed-restore-quarantine \
    verify-restore-quarantine; do
    grep -Fq "'$phase'" "$probe" || fail "probe misses runtime phase: $phase"
done

echo 'V2 retention runtime runner contract passed.'
