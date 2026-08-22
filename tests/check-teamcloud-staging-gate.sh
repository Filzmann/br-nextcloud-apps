#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
gate="$workspace/scripts/teamcloud-staging"
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
    local haystack="$1"
    local needle="$2"
    grep -Fqx -- "$needle" <<< "$haystack" || fail "ACL-Eintrag fehlt: $needle"
}

[[ -x "$gate" ]] || fail "SSH-Gate fehlt oder ist nicht ausführbar: $gate"
command -v setfacl >/dev/null || fail 'setfacl fehlt für den ACL-Contract-Test.'
command -v getfacl >/dev/null || fail 'getfacl fehlt für den ACL-Contract-Test.'

# Funktionen werden für einen isolierten ACL-Contract geladen. Die produktiven
# Konstanten werden beim direkten Aufruf des Gates weiterhin fest gesetzt.
source "$gate"

incoming_root="$stage/incoming"
gate_owner="$(id -un)"
installer_user='nobody'
installer_uid="$(id -u "$installer_user")"
install_wrapper="$stage/teamcloud-staging-install"
sudo_bin="$stage/fake-sudo"
logger_bin="$stage/fake-logger"
sudo_log="$stage/sudo.log"
logger_log="$stage/logger.log"
export TEAMCLOUD_STAGING_TEST_SUDO_LOG="$sudo_log"
export TEAMCLOUD_STAGING_TEST_LOGGER_LOG="$logger_log"

install -d -m 700 "$incoming_root"
cat > "$sudo_bin" <<'SH'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$TEAMCLOUD_STAGING_TEST_SUDO_LOG"
SH
cat > "$logger_bin" <<'SH'
#!/usr/bin/env bash
printf '%s\n' "$*" >> "$TEAMCLOUD_STAGING_TEST_LOGGER_LOG"
SH
cat > "$install_wrapper" <<'SH'
#!/usr/bin/env bash
exit 0
SH
chmod 700 "$sudo_bin" "$logger_bin" "$install_wrapper"

run_id='32576122333'
attempt='1'
app_id='br_permission_matrix'
commit='0123456789abcdef0123456789abcdef01234567'
hash='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
run_dir="$incoming_root/$run_id-$attempt-$app_id"
archive="$run_dir/$app_id.tar.gz"

printf 'synthetisches Archiv\n' | gate_upload "$run_id" "$attempt" "$app_id"

[[ -f "$archive" && ! -L "$archive" ]] || fail 'Upload erzeugt kein reguläres Archiv.'
[[ "$(stat -c '%U' -- "$archive")" == "$gate_owner" ]] || fail 'Upload verändert den Archiveigentümer.'
[[ "$(stat -c '%a' -- "$archive")" == '640' ]] || fail 'Archivmodus bildet die effektive ACL nicht erwartungsgemäß ab.'

root_acl="$(getfacl -ncp -- "$incoming_root")"
run_acl="$(getfacl -ncp -- "$run_dir")"
archive_acl="$(getfacl -ncp -- "$archive")"

for directory_acl in "$root_acl" "$run_acl"; do
    assert_contains "$directory_acl" 'user::rwx'
    assert_contains "$directory_acl" "user:$installer_uid:--x"
    assert_contains "$directory_acl" 'group::---'
    assert_contains "$directory_acl" 'mask::--x'
    assert_contains "$directory_acl" 'other::---'
done

assert_contains "$archive_acl" 'user::rw-'
assert_contains "$archive_acl" "user:$installer_uid:r--"
assert_contains "$archive_acl" 'group::---'
assert_contains "$archive_acl" 'mask::r--'
assert_contains "$archive_acl" 'other::---'
[[ "$(grep -Ec '^user:[0-9]+:' <<< "$archive_acl")" -eq 1 ]] || fail 'Archiv besitzt eine zusätzliche benannte Benutzer-ACL.'
if grep -Eq "^user:$installer_uid:.*w" <<< "$archive_acl"; then
    fail 'Installer-Benutzer erhält Schreibrecht auf das Archiv.'
fi

gate_install "$run_id" "$attempt" "$app_id" "$commit" "$hash"
grep -Fq -- "-n -u $installer_user $install_wrapper $archive $app_id $commit $hash" "$sudo_log" \
    || fail 'Install delegiert nicht mit dem unveränderten Wrapper-Vertrag.'

gate_cleanup "$run_id" "$attempt" "$app_id"
[[ ! -e "$run_dir" ]] || fail 'Cleanup entfernt das validierte Run-Verzeichnis nicht.'

working_setfacl="$setfacl_bin"
setfacl_bin="$stage/missing-setfacl"
if printf 'nicht abzulegen\n' | gate_upload 32576122334 1 "$app_id" >/dev/null 2>&1; then
    fail 'Upload akzeptiert ein System ohne setfacl.'
fi
[[ ! -e "$incoming_root/32576122334-1-$app_id" ]] || fail 'Fehlendes setfacl hinterlässt ein Run-Verzeichnis.'
setfacl_bin="$working_setfacl"

working_getfacl="$getfacl_bin"
getfacl_bin="$stage/missing-getfacl"
if printf 'nicht abzulegen\n' | gate_upload 32576122335 1 "$app_id" >/dev/null 2>&1; then
    fail 'Upload akzeptiert ein System ohne getfacl.'
fi
[[ ! -e "$incoming_root/32576122335-1-$app_id" ]] || fail 'Fehlendes getfacl hinterlässt ein Run-Verzeichnis.'
getfacl_bin="$working_getfacl"

if (main shell "$run_id" "$attempt" "$app_id") >/dev/null 2>&1; then
    fail 'Ein ungültiges direktes Gate-Kommando wurde akzeptiert.'
fi
if (SSH_ORIGINAL_COMMAND="teamcloud-staging upload $run_id $attempt $app_id; id" main) >/dev/null 2>&1; then
    fail 'Ein manipuliertes SSH_ORIGINAL_COMMAND wurde akzeptiert.'
fi

grep -Fq 'UPLOAD' "$logger_log" || fail 'Upload wird nicht protokolliert.'
grep -Fq 'INSTALL' "$logger_log" || fail 'Install wird nicht protokolliert.'
grep -Fq 'CLEANUP' "$logger_log" || fail 'Cleanup wird nicht protokolliert.'

echo 'Teamcloud-Staging-Gate-ACL-Contract: OK'
