#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
gate="$workspace/scripts/br-staging"
wrapper="$workspace/scripts/br-staging-install"
workflow="$workspace/.github/workflows/deploy-staging.yml"
candidate_workflow="$workspace/.github/workflows/candidate-staging.yml"
runbook="$workspace/docs/staging-deployment.md"
stage="$(mktemp -d)"

cleanup() {
    rm -rf "$stage"
}
trap cleanup EXIT

fail() {
    echo "FEHLER: $*" >&2
    exit 1
}

assert_contains() {
    local file="$1"
    local text="$2"
    grep -Fq -- "$text" "$file" || fail "$file enthält nicht: $text"
}

[[ -x "$gate" ]] || fail "BR-SSH-Gate fehlt oder ist nicht ausführbar: $gate"
[[ -x "$wrapper" ]] || fail "BR-Installationswrapper fehlt oder ist nicht ausführbar: $wrapper"
command -v setfacl >/dev/null || fail 'setfacl fehlt für den ACL-Contract-Test.'
command -v getfacl >/dev/null || fail 'getfacl fehlt für den ACL-Contract-Test.'

[[ "$(head -n 1 "$gate")" == '#!/bin/bash -p' ]] \
    || fail 'Gate verwendet keinen festen privilegierten Bash-Interpreter.'
assert_contains "$gate" "incoming_root='/var/tmp/br-staging-incoming'"
assert_contains "$gate" "gate_owner='simon'"
assert_contains "$gate" "installer_user='betriebsrat'"
assert_contains "$gate" "install_wrapper='/usr/local/sbin/br-staging-install'"
assert_contains "$wrapper" "state_dir='/var/lib/br-staging'"
assert_contains "$wrapper" "nextcloud_root='/var/www/vhosts/betriebsrat-ad.de/site1'"
assert_contains "$wrapper" "php_bin='/opt/plesk/php/8.3/bin/php'"
assert_contains "$wrapper" "verifier='/usr/local/libexec/br-staging/verify-staging-deployment-marker.php'"
assert_contains "$wrapper" "active_reader='betriebsrat_acceptance'"
assert_contains "$wrapper" '--staging-target br-staging'
assert_contains "$workflow" 'BR_STAGING_GATE_COMMAND: ${{ vars.STAGING_GATE_COMMAND }}'
assert_contains "$workflow" 'echo "STAGING_GATE_COMMAND=$BR_STAGING_GATE_COMMAND" >> "$GITHUB_ENV"'
assert_contains "$workflow" 'if [[ "$DEPLOYMENT_MODE" == authoritative-candidate ]]'
assert_contains "$candidate_workflow" 'staging-target: br-staging'
assert_contains "$runbook" 'STAGING_GATE_COMMAND`: `br-staging`'
assert_contains "$runbook" '/var/www/vhosts/betriebsrat-ad.de/site1'
assert_contains "$runbook" 'simon ALL=(betriebsrat) NOPASSWD: /usr/local/sbin/br-staging-install'

# Funktionen werden isoliert mit temporären Pfaden und Benutzern geprüft.
# Die produktiven Konstanten bleiben beim direkten Gate-Aufruf fest gesetzt.
source "$gate"

[[ "$(app_mode brstunden)" == candidate && "$(app_mode brtop)" == candidate ]] \
    || fail 'BR-App-Allowlist ist unvollständig.'
[[ "$(app_mode localbase)" == runtime-mirror && "$(app_mode orgsuite)" == runtime-mirror ]] \
    || fail 'Runtime-Mirror-Allowlist ist unvollständig.'

incoming_root="$stage/incoming"
gate_owner="$(id -un)"
installer_user='nobody'
installer_uid="$(id -u "$installer_user")"
install_wrapper="$stage/br-staging-install"
sudo_bin="$stage/fake-sudo"
logger_bin="$stage/fake-logger"
sudo_log="$stage/sudo.log"
logger_log="$stage/logger.log"
export BR_STAGING_TEST_SUDO_LOG="$sudo_log"
export BR_STAGING_TEST_LOGGER_LOG="$logger_log"

install -d -m 700 "$incoming_root"
require_acl_tools
"$setfacl_bin" -b -- "$incoming_root"
"$setfacl_bin" -k -- "$incoming_root"
"$setfacl_bin" -m "u:$installer_uid:--x,g::---,m::--x,o::---" -- "$incoming_root"

cat > "$sudo_bin" <<'SH'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$BR_STAGING_TEST_SUDO_LOG"
SH
cat > "$logger_bin" <<'SH'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$BR_STAGING_TEST_LOGGER_LOG"
SH
cat > "$install_wrapper" <<'SH'
#!/usr/bin/env bash
exit 0
SH
chmod 700 "$sudo_bin" "$logger_bin" "$install_wrapper"

run_id='32576122333'
attempt='1'
commit='0123456789abcdef0123456789abcdef01234567'
hash='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
candidate_id='CAND-2026-br'
deployment_id='github-32576122333-1-brstunden'
build_id='github-build-brstunden'

printf 'synthetisches BR-Archiv\n' | gate_upload "$run_id" "$attempt" brstunden
gate_install "$run_id" "$attempt" brstunden "$commit" "$hash" \
    "$candidate_id" "$deployment_id" "$build_id"
grep -Fq -- "-n -u $installer_user $install_wrapper $incoming_root/$run_id-$attempt-brstunden/brstunden.tar.gz brstunden $commit $hash $candidate_id $deployment_id $build_id" "$sudo_log" \
    || fail 'BR-Candidate wird nicht unverändert an den Wrapper delegiert.'
gate_cleanup "$run_id" "$attempt" brstunden

mirror_run_id='32576122334'
printf 'synthetischer LocalBase-Mirror\n' | gate_upload "$mirror_run_id" "$attempt" localbase
gate_install "$mirror_run_id" "$attempt" localbase "$commit" "$hash"
grep -Fq -- "-n -u $installer_user $install_wrapper $incoming_root/$mirror_run_id-$attempt-localbase/localbase.tar.gz localbase $commit $hash" "$sudo_log" \
    || fail 'LocalBase-Runtime-Mirror wird nicht ohne Candidate-Bindung delegiert.'
sudo_lines_before="$(wc -l < "$sudo_log")"
if (gate_install "$mirror_run_id" "$attempt" localbase "$commit" "$hash" \
    "$candidate_id" mirror-deployment mirror-build) >/dev/null 2>&1; then
    fail 'LocalBase-Runtime-Mirror akzeptiert eine BR-Candidate-Bindung.'
fi
[[ "$(wc -l < "$sudo_log")" -eq "$sudo_lines_before" ]] \
    || fail 'Abgelehnter Runtime-Mirror hat den Installationswrapper aufgerufen.'
gate_cleanup "$mirror_run_id" "$attempt" localbase

if printf 'nicht abzulegen\n' | gate_upload 32576122335 1 flzcalendar >/dev/null 2>&1; then
    fail 'BR-Gate akzeptiert eine FLZ-Fachapp.'
fi
[[ ! -e "$incoming_root/32576122335-1-flzcalendar" ]] \
    || fail 'Abgelehnte FLZ-App hinterlässt ein Run-Verzeichnis.'
if (gate_verify localbase "$candidate_id" mirror-deployment "$commit" "$hash" mirror-build) >/dev/null 2>&1; then
    fail 'Runtime-Mirror kann als BR-Candidate verifiziert werden.'
fi
if (SSH_ORIGINAL_COMMAND="br-staging upload $run_id $attempt brstunden; id" main) >/dev/null 2>&1; then
    fail 'Manipuliertes SSH_ORIGINAL_COMMAND wurde akzeptiert.'
fi

grep -Fq 'UPLOAD' "$logger_log" || fail 'Upload wird nicht protokolliert.'
grep -Fq 'INSTALL' "$logger_log" || fail 'Install wird nicht protokolliert.'
grep -Fq 'CLEANUP' "$logger_log" || fail 'Cleanup wird nicht protokolliert.'

if "$wrapper" /var/tmp/br-staging-incoming/1-1-localbase/localbase.tar.gz \
    localbase "$commit" "$hash" "$candidate_id" mirror-deployment mirror-build >/dev/null 2>&1; then
    fail 'Server-Wrapper akzeptiert Candidate-/Evidence-Daten für einen Runtime-Mirror.'
fi
if "$wrapper" /var/tmp/br-staging-incoming/1-1-brtop/brtop.tar.gz \
    brtop "$commit" "$hash" >/dev/null 2>&1; then
    fail 'Server-Wrapper akzeptiert eine BR-App ohne Candidate-Bindung.'
fi
if "$wrapper" /var/tmp/br-staging-incoming/1-1-flzcalendar/flzcalendar.tar.gz \
    flzcalendar "$commit" "$hash" >/dev/null 2>&1; then
    fail 'Server-Wrapper akzeptiert eine FLZ-Fachapp.'
fi

echo 'BR-Staging-Gate-Contract: OK'
