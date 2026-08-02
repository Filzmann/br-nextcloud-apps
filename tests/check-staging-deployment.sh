#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
package_script="$workspace/scripts/package-staging-app.sh"
installer="$workspace/scripts/install-staging-app.sh"
server_wrapper="$workspace/scripts/teamcloud-staging-install"
workflow="$workspace/.github/workflows/deploy-staging.yml"
stage="$(mktemp -d)"

cleanup() {
    rm -rf "$stage"
}
trap cleanup EXIT

fail() {
    echo "FEHLER: $*" >&2
    exit 1
}

assert_file() {
    [[ -f "$1" ]] || fail "Datei fehlt: $1"
}

assert_contains() {
    local file="$1"
    local text="$2"
    grep -Fq -- "$text" "$file" || fail "$file enthält nicht: $text"
}

app="$stage/source/demoapp"
mkdir -p "$app/appinfo" "$app/css" "$app/js" "$app/tests" "$app/.github/workflows" "$app/.git"
cat > "$app/appinfo/info.xml" <<'XML'
<?xml version="1.0"?>
<info>
  <id>demoapp</id>
  <name>Demo App</name>
  <version>1.2.3</version>
  <dependencies><nextcloud min-version="34" max-version="34"/></dependencies>
</info>
XML
printf 'new-code\n' > "$app/new-code.txt"
printf 'body {}\n' > "$app/css/app.css"
printf 'console.log("demo");\n' > "$app/js/app.js"
printf 'excluded\n' > "$app/tests/excluded.txt"
ln -s excluded.txt "$app/tests/excluded-link"
printf 'excluded\n' > "$app/.github/workflows/excluded.yml"
printf 'excluded\n' > "$app/AGENTS.md"
printf 'excluded\n' > "$app/.git/config"

mkdir -p "$stage/output"
archive="$stage/output/demoapp.tar.gz"
metadata="$stage/output/metadata.env"
commit='0123456789abcdef0123456789abcdef01234567'

"$package_script" \
    --app-root "$app" \
    --app-id demoapp \
    --commit "$commit" \
    --archive "$archive" \
    --metadata "$metadata"

assert_file "$archive"
assert_file "$metadata"
assert_contains "$metadata" 'APP_ID=demoapp'
assert_contains "$metadata" 'APP_VERSION=1.2.3'
assert_contains "$metadata" "GIT_COMMIT=$commit"
assert_contains "$metadata" 'CSS_ASSET=css/app.css'
assert_contains "$metadata" 'JS_ASSET=js/app.js'

mapfile -t roots < <(tar -tzf "$archive" | cut -d/ -f1 | sort -u)
[[ "${#roots[@]}" -eq 1 && "${roots[0]}" == demoapp ]] || fail 'Archiv hat nicht genau den App-Wurzelordner.'
if tar -tzf "$archive" | grep -Eq '(^|/)(\.git|\.github|tests|AGENTS\.md)(/|$)'; then
    fail 'Archiv enthält Entwicklungsdateien.'
fi

nextcloud="$stage/nextcloud"
state="$stage/state"
mkdir -p "$nextcloud/custom_apps/demoapp/appinfo" "$state"
cp "$app/appinfo/info.xml" "$nextcloud/custom_apps/demoapp/appinfo/info.xml"
printf 'old-code\n' > "$nextcloud/custom_apps/demoapp/old-code.txt"

cat > "$nextcloud/occ" <<'PHP'
<?php
$command = $argv[1] ?? '';
$log = getenv('FAKE_OCC_LOG');
if ($log !== false) {
    file_put_contents($log, implode(' ', array_slice($argv, 1)) . "\n", FILE_APPEND);
}
if ($command === 'status') {
    $counterFile = getenv('FAKE_OCC_STATUS_COUNT');
    $count = 1;
    if ($counterFile !== false) {
        $count = is_file($counterFile) ? ((int)file_get_contents($counterFile) + 1) : 1;
        file_put_contents($counterFile, (string)$count);
    }
    if (getenv('FAKE_OCC_FAIL_SECOND_STATUS') === '1' && $count >= 2) {
        exit(22);
    }
    echo json_encode(['installed' => true, 'maintenance' => false, 'needsDbUpgrade' => false]);
    exit(0);
}
if ($command === 'app:list') {
    $state = getenv('FAKE_OCC_STATE');
    $enabled = $state !== false && is_file($state);
    echo json_encode($enabled
        ? ['enabled' => ['demoapp' => '1.2.3'], 'disabled' => []]
        : ['enabled' => [], 'disabled' => ['demoapp' => '1.2.3']]);
    exit(0);
}
if ($command === 'app:enable') {
    $state = getenv('FAKE_OCC_STATE');
    if ($state !== false) file_put_contents($state, 'enabled');
    exit(0);
}
if ($command === 'upgrade') {
    exit(getenv('FAKE_OCC_FAIL_UPGRADE') === '1' ? 24 : 0);
}
fwrite(STDERR, "unexpected occ command: $command\n");
exit(25);
PHP

hash="$(sha256sum "$archive" | cut -d' ' -f1)"
FAKE_OCC_LOG="$stage/occ-success.log" \
FAKE_OCC_STATE="$stage/occ-enabled" \
FAKE_OCC_STATUS_COUNT="$stage/occ-status-count" \
"$installer" \
    --nextcloud-root "$nextcloud" \
    --php-bin "$(command -v php)" \
    --php-memory-limit 512M \
    --archive "$archive" \
    --sha256 "$hash" \
    --app-id demoapp \
    --commit "$commit" \
    --state-dir "$state"

assert_file "$nextcloud/custom_apps/demoapp/new-code.txt"
[[ ! -e "$nextcloud/custom_apps/demoapp/old-code.txt" ]] || fail 'Alter Code blieb nach erfolgreicher Installation aktiv.'
assert_contains "$state/deployed/demoapp.tsv" "$commit"
assert_contains "$stage/occ-success.log" 'app:enable demoapp'
assert_contains "$stage/occ-success.log" 'upgrade'

if "$installer" \
    --nextcloud-root "$nextcloud" \
    --php-bin "$(command -v php)" \
    --archive "$archive" \
    --sha256 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff' \
    --app-id demoapp \
    --commit "$commit" \
    --state-dir "$state" >/dev/null 2>&1; then
    fail 'Falsche Prüfsumme wurde akzeptiert.'
fi

rollback_nc="$stage/rollback-nextcloud"
rollback_state="$stage/rollback-state"
mkdir -p "$rollback_nc/custom_apps/demoapp/appinfo" "$rollback_state"
cp "$app/appinfo/info.xml" "$rollback_nc/custom_apps/demoapp/appinfo/info.xml"
cp "$nextcloud/occ" "$rollback_nc/occ"
printf 'old-code\n' > "$rollback_nc/custom_apps/demoapp/old-code.txt"
if FAKE_OCC_FAIL_SECOND_STATUS=1 \
    FAKE_OCC_STATUS_COUNT="$stage/rollback-status-count" \
    "$installer" \
    --nextcloud-root "$rollback_nc" \
    --php-bin "$(command -v php)" \
    --archive "$archive" \
    --sha256 "$hash" \
    --app-id demoapp \
    --commit "$commit" \
    --state-dir "$rollback_state" >/dev/null 2>&1; then
    fail 'Fehlschlagende App-Aktivierung wurde als Erfolg gemeldet.'
fi
assert_file "$rollback_nc/custom_apps/demoapp/old-code.txt"
[[ ! -e "$rollback_nc/custom_apps/demoapp/new-code.txt" ]] || fail 'Neuer Code blieb nach Rollback aktiv.'

bad="$stage/bad"
mkdir -p "$bad/demoapp/appinfo"
cp "$app/appinfo/info.xml" "$bad/demoapp/appinfo/info.xml"
ln -s /etc/passwd "$bad/demoapp/unsafe-link"
tar -C "$bad" -czf "$stage/bad.tar.gz" demoapp
bad_hash="$(sha256sum "$stage/bad.tar.gz" | cut -d' ' -f1)"
if "$installer" \
    --nextcloud-root "$nextcloud" \
    --php-bin "$(command -v php)" \
    --archive "$stage/bad.tar.gz" \
    --sha256 "$bad_hash" \
    --app-id demoapp \
    --commit "$commit" \
    --state-dir "$state" >/dev/null 2>&1; then
    fail 'Symlink im Archiv wurde akzeptiert.'
fi

assert_file "$workflow"
assert_contains "$workflow" 'workflow_call:'
assert_contains "$workflow" 'environment: staging'
assert_contains "$workflow" 'STAGING_SSH_PRIVATE_KEY'
assert_contains "$workflow" 'STAGING_SSH_KNOWN_HOSTS'
assert_contains "$workflow" 'sudo -n -u simonbeyer_sys /usr/local/sbin/teamcloud-staging-install'
assert_contains "$workflow" '/var/tmp/teamcloud-staging-incoming'
if grep -Fq 'control/scripts/install-staging-app.sh' "$workflow"; then
    fail 'Workflow lädt den root-eigenen Server-Installer unzulässig pro Deployment hoch.'
fi
if grep -Fq 'CONTROL_REPOSITORY_TOKEN' "$workflow"; then
    fail 'Öffentliches Parent-Repository verlangt unnötig ein zusätzliches Zugriffstoken.'
fi

assert_file "$server_wrapper"
assert_contains "$server_wrapper" '/var/www/vhosts/simonbeyer.de/teamcloud.simonbeyer.de'
assert_contains "$server_wrapper" '/opt/plesk/php/8.4/bin/php'
assert_contains "$server_wrapper" '/usr/local/libexec/teamcloud-staging/install-staging-app.sh'
assert_contains "$server_wrapper" '/var/lib/teamcloud-staging'
assert_contains "$server_wrapper" '--php-memory-limit 512M'
if "$server_wrapper" /var/tmp/teamcloud-staging-incoming/1-1-foreign/foreign.tar.gz foreign "$commit" "$hash" >/dev/null 2>&1; then
    fail 'Server-Wrapper akzeptiert eine nicht freigegebene App-ID.'
fi
if "$server_wrapper" /tmp/demoapp.tar.gz demoapp "$commit" "$hash" >/dev/null 2>&1; then
    fail 'Server-Wrapper akzeptiert ein Archiv außerhalb des Incoming-Verzeichnisses.'
fi

for app_id in brtop adplaner brstunden localbase br_permission_matrix adcalendar adurlaub orgsuite adroom adrecruitment; do
    caller="$workspace/$app_id/.github/workflows/tests.yml"
    assert_contains "$caller" 'deploy-staging:'
    assert_contains "$caller" 'Filzmann/br-nextcloud-apps/.github/workflows/deploy-staging.yml@main'
    assert_contains "$caller" "app-id: $app_id"
done

echo 'Staging-Deployment-Contract: OK'
