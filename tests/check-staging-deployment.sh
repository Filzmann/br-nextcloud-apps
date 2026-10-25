#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
package_script="$workspace/scripts/package-staging-app.sh"
installer="$workspace/scripts/install-staging-app.sh"
server_wrapper="$workspace/scripts/teamcloud-staging-install"
ssh_gate="$workspace/scripts/teamcloud-staging"
deployment_verifier="$workspace/scripts/verify-staging-deployment-marker.php"
input_validator="$workspace/scripts/validate-staging-deployment-inputs"
workflow="$workspace/.github/workflows/deploy-staging.yml"
candidate_workflow="$workspace/.github/workflows/candidate-staging.yml"
candidate_contract="$workspace/scripts/candidate-staging-contract"
privacy_tests_workflow="$workspace/flz_data_protection/.github/workflows/tests.yml"
runbook="$workspace/docs/staging-deployment.md"
stage="$(mktemp -d)"
race_pid=''

cleanup() {
    if [[ -n "$race_pid" ]]; then
        kill "$race_pid" 2>/dev/null || true
        wait "$race_pid" 2>/dev/null || true
    fi
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
candidate_id='CAND-2026-mirror'
build_id='github-77-1-localbase'
build_hash='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
different_commit='fedcba9876543210fedcba9876543210fedcba98'

assert_file "$input_validator"
[[ -x "$candidate_contract" ]] \
    || fail "Ausführbarer Candidate-Staging-Vertrag fehlt: $candidate_contract"

candidate_env="$stage/candidate-github.env"
: > "$candidate_env"
"$candidate_contract" resolve \
    --repository Filzmann/nextcloud-brstunden \
    --app-id brstunden \
    --github-env "$candidate_env"
assert_contains "$candidate_env" 'CANDIDATE_INTAKE_URL=https://acceptance.staging.betriebsrat-ad.de/api/candidate-intake'
assert_contains "$candidate_env" 'CANDIDATE_STAGING_TARGET=br-staging'

: > "$candidate_env"
"$candidate_contract" resolve \
    --repository Filzmann/nextcloud-localbase \
    --app-id localbase \
    --github-env "$candidate_env"
assert_contains "$candidate_env" 'CANDIDATE_INTAKE_URL=https://teamcloud.simonbeyer.de/api/candidate-intake'
assert_contains "$candidate_env" 'CANDIDATE_STAGING_TARGET=teamcloud'

for rejected_pair in \
    'Filzmann/nextcloud-brstunden localbase' \
    'Filzmann/nextcloud-localbase brstunden' \
    'Filzmann/unknown brstunden'; do
    read -r rejected_repository rejected_app_id <<< "$rejected_pair"
    : > "$candidate_env"
    if "$candidate_contract" resolve \
        --repository "$rejected_repository" \
        --app-id "$rejected_app_id" \
        --github-env "$candidate_env" >/dev/null 2>&1; then
        fail "Unbekanntes oder gekreuztes Candidate-Paar wurde akzeptiert: $rejected_pair"
    fi
    [[ ! -s "$candidate_env" ]] \
        || fail "Abgelehntes Candidate-Paar hat Umgebungswerte veröffentlicht: $rejected_pair"
done

candidate_response="$stage/candidate-response.json"
candidate_outputs="$stage/candidate-github.output"
candidate_response_commit='0123456789abcdef0123456789abcdef01234567'
candidate_response_build_id='github-77-1-brstunden'
candidate_response_hash='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
cat > "$candidate_response" <<JSON
{"candidate_id":"CAND-brstunden-77-1","source_commit":"$candidate_response_commit","build_id":"$candidate_response_build_id","build_sha256":"$candidate_response_hash","staging_target":"br-staging"}
JSON
: > "$candidate_outputs"
"$candidate_contract" publish-response \
    --expected-staging-target br-staging \
    --expected-source-commit "$candidate_response_commit" \
    --expected-build-id "$candidate_response_build_id" \
    --expected-build-sha256 "$candidate_response_hash" \
    --response-file "$candidate_response" \
    --github-output "$candidate_outputs"
assert_contains "$candidate_outputs" 'candidate-id=CAND-brstunden-77-1'
assert_contains "$candidate_outputs" "source-commit=$candidate_response_commit"
assert_contains "$candidate_outputs" "build-id=$candidate_response_build_id"
assert_contains "$candidate_outputs" "build-sha256=$candidate_response_hash"
assert_contains "$candidate_outputs" 'staging-target=br-staging'

cat > "$candidate_response" <<JSON
{"candidate_id":"CAND-brstunden-77-1","source_commit":"$candidate_response_commit","build_id":"$candidate_response_build_id","build_sha256":"$candidate_response_hash","staging_target":"teamcloud"}
JSON
: > "$candidate_outputs"
if "$candidate_contract" publish-response \
    --expected-staging-target br-staging \
    --expected-source-commit "$candidate_response_commit" \
    --expected-build-id "$candidate_response_build_id" \
    --expected-build-sha256 "$candidate_response_hash" \
    --response-file "$candidate_response" \
    --github-output "$candidate_outputs" >/dev/null 2>&1; then
    fail 'Eine Candidate-Antwort vom falschen autoritativen Ziel wurde akzeptiert.'
fi
[[ ! -s "$candidate_outputs" ]] \
    || fail 'Eine Candidate-Antwort vom falschen Ziel hat Deployment-Ausgaben veröffentlicht.'

if "$input_validator" \
    --app-id brstunden \
    --deployment-target br-staging \
    --candidate-staging-target '' \
    --source-ref "$commit" \
    --candidate-id '' \
    --candidate-source-commit '' \
    --candidate-build-id '' \
    --candidate-build-sha256 '' >/dev/null 2>&1; then
    fail 'Eine BR-App wurde ohne zielgebundene v2-Candidate-Bindung akzeptiert.'
fi
mirror_mode="$("$input_validator" \
    --app-id localbase \
    --deployment-target br-staging \
    --candidate-staging-target teamcloud \
    --source-ref "$commit" \
    --candidate-id "$candidate_id" \
    --candidate-source-commit "$commit" \
    --candidate-build-id "$build_id" \
    --candidate-build-sha256 "$build_hash" \
    --actual-source-commit "$commit" \
    --actual-build-sha256 "$build_hash")"
[[ "$mirror_mode" == 'runtime-mirror' ]] \
    || fail 'Ein vollständig an den Teamcloud-Candidate gebundener Runtime-Mirror wurde nicht erkannt.'

if "$input_validator" \
    --app-id localbase \
    --deployment-target br-staging \
    --candidate-staging-target br-staging \
    --source-ref "$commit" \
    --candidate-id "$candidate_id" \
    --candidate-source-commit "$commit" \
    --candidate-build-id "$build_id" \
    --candidate-build-sha256 "$build_hash" \
    --actual-source-commit "$commit" \
    --actual-build-sha256 "$build_hash" >/dev/null 2>&1; then
    fail 'Ein Runtime-Mirror mit falschem autoritativem Ziel wurde akzeptiert.'
fi

if "$input_validator" \
    --app-id orgsuite \
    --deployment-target br-staging \
    --candidate-staging-target teamcloud \
    --source-ref "$commit" \
    --candidate-id "$candidate_id" \
    --candidate-source-commit "$commit" \
    --candidate-build-id "$build_id" \
    --candidate-build-sha256 "$build_hash" \
    --actual-source-commit "$commit" \
    --actual-build-sha256 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' >/dev/null 2>&1; then
    fail 'Ein Runtime-Mirror mit abweichendem Archivdigest wurde akzeptiert.'
fi

if "$input_validator" \
    --app-id localbase \
    --deployment-target br-staging \
    --candidate-staging-target teamcloud \
    --source-ref refs/heads/main \
    --candidate-id "$candidate_id" \
    --candidate-source-commit "$commit" \
    --candidate-build-id "$build_id" \
    --candidate-build-sha256 "$build_hash" \
    --actual-source-commit "$commit" \
    --actual-build-sha256 "$build_hash" >/dev/null 2>&1; then
    fail 'Ein Runtime-Mirror aus einem veränderlichen Git-Ref wurde akzeptiert.'
fi

legacy_mode="$("$input_validator" \
    --app-id flz_data_protection \
    --deployment-target teamcloud \
    --candidate-staging-target '' \
    --source-ref "$commit" \
    --candidate-id '' \
    --candidate-source-commit '' \
    --candidate-build-id '' \
    --candidate-build-sha256 '' \
    --actual-source-commit "$commit" \
    --actual-build-sha256 "$build_hash")"
[[ "$legacy_mode" == 'legacy-deployment' ]] \
    || fail 'Ein vollständig an Commit und erzeugten Digest gebundenes Legacy-Deployment wurde nicht erkannt.'

if "$input_validator" \
    --app-id flz_data_protection \
    --deployment-target teamcloud \
    --candidate-staging-target '' \
    --source-ref "$commit" \
    --candidate-id '' \
    --candidate-source-commit '' \
    --candidate-build-id '' \
    --candidate-build-sha256 '' \
    --actual-source-commit "$different_commit" \
    --actual-build-sha256 "$build_hash" >/dev/null 2>&1; then
    fail 'Ein Legacy-Deployment mit vom gepinnten Ref abweichendem Checkout wurde akzeptiert.'
fi

if "$input_validator" \
    --app-id flz_data_protection \
    --deployment-target teamcloud \
    --candidate-staging-target '' \
    --source-ref refs/heads/main \
    --candidate-id '' \
    --candidate-source-commit '' \
    --candidate-build-id '' \
    --candidate-build-sha256 '' \
    --actual-source-commit "$commit" \
    --actual-build-sha256 "$build_hash" >/dev/null 2>&1; then
    fail 'Ein Legacy-Deployment aus einem veränderlichen Git-Ref wurde akzeptiert.'
fi

if "$input_validator" \
    --app-id flz_data_protection \
    --deployment-target teamcloud \
    --candidate-staging-target '' \
    --source-ref "$commit" \
    --candidate-id '' \
    --candidate-source-commit '' \
    --candidate-build-id '' \
    --candidate-build-sha256 '' \
    --actual-source-commit "$commit" \
    --actual-build-sha256 invalid >/dev/null 2>&1; then
    fail 'Ein Legacy-Deployment mit ungültigem erzeugtem Archivdigest wurde akzeptiert.'
fi

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

touch -d '2031-01-01 00:00:00 UTC' "$app/new-code.txt" "$app/css/app.css" "$app/js/app.js"
second_archive="$stage/output/demoapp-second.tar.gz"
second_metadata="$stage/output/metadata-second.env"
"$package_script" --app-root "$app" --app-id demoapp --commit "$commit" --archive "$second_archive" --metadata "$second_metadata"
cmp "$archive" "$second_archive" || fail 'Identischer Candidate-Quellstand erzeugt kein reproduzierbares Staging-Archiv.'

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
command -v setfacl >/dev/null || fail 'setfacl fehlt für den Acceptance-Lesevertrag.'
command -v getfacl >/dev/null || fail 'getfacl fehlt für den Acceptance-Lesevertrag.'
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
    --staging-target teamcloud \
    --state-dir "$state" \
    --candidate-id CAND-2026-0001 \
    --deployment-id github-42-1-demoapp \
    --build-id build-17 \
    --active-reader-user nobody

assert_file "$nextcloud/custom_apps/demoapp/new-code.txt"
[[ ! -e "$nextcloud/custom_apps/demoapp/old-code.txt" ]] || fail 'Alter Code blieb nach erfolgreicher Installation aktiv.'
assert_contains "$state/deployed/demoapp.tsv" "$commit"
assert_contains "$stage/occ-success.log" 'app:enable demoapp'
assert_contains "$stage/occ-success.log" 'upgrade'
assert_contains "$state/active/demoapp.json" '"candidate_id": "CAND-2026-0001"'
assert_contains "$state/active/demoapp.json" '"deployment_id": "github-42-1-demoapp"'
assert_contains "$state/active/demoapp.json" '"schema": "filzmann.active-staging-deployment/v2"'
assert_contains "$state/active/demoapp.json" '"staging_target": "teamcloud"'
assert_contains "$state/active/demoapp.json" '"deployed_at":'
assert_contains "$state/active/demoapp.json" '"status": "pending-https-verification"'
getfacl -cp "$state/active" | grep -Fqx 'user:nobody:r-x' \
    || fail 'Acceptance-Dienst besitzt kein minimales Traverse-/Leserecht auf dem Marker-Verzeichnis.'
getfacl -cp "$state/active/demoapp.json" | grep -Fqx 'user:nobody:r--' \
    || fail 'Acceptance-Dienst besitzt kein minimales Leserecht auf dem aktiven Marker.'

"$(command -v php)" "$deployment_verifier" \
    --active-file "$state/active/demoapp.json" \
    --lock-file "$state/deploy.lock" \
    --app-id demoapp \
    --candidate-id CAND-2026-0001 \
    --deployment-id github-42-1-demoapp \
    --source-commit "$commit" \
    --build-id build-17 \
    --build-sha256 "$hash" \
    --staging-target teamcloud \
    --verified-at 2026-10-11T08:25:00Z \
    --active-reader-user nobody
assert_contains "$state/active/demoapp.json" '"status": "verified"'
assert_contains "$state/active/demoapp.json" '"verified_at": "2026-10-11T08:25:00Z"'
getfacl -cp "$state/active/demoapp.json" | grep -Fqx 'user:nobody:r--' \
    || fail 'Acceptance-Leserecht ging bei der atomaren HTTPS-Verifikation verloren.'
if "$(command -v php)" "$deployment_verifier" \
    --active-file "$state/active/demoapp.json" \
    --lock-file "$state/deploy.lock" \
    --app-id demoapp \
    --candidate-id CAND-2026-0001 \
    --deployment-id github-foreign \
    --source-commit "$commit" \
    --build-id build-17 \
    --build-sha256 "$hash" \
    --staging-target teamcloud \
    --verified-at 2026-10-11T08:26:00Z \
    --active-reader-user nobody >/dev/null 2>&1; then
    fail 'Ein widersprüchlicher HTTPS-Verifikationsbeleg wurde akzeptiert.'
fi

if "$installer" \
    --nextcloud-root "$nextcloud" \
    --php-bin "$(command -v php)" \
    --archive "$archive" \
    --sha256 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff' \
    --app-id demoapp \
    --commit "$commit" \
    --staging-target teamcloud \
    --state-dir "$state" >/dev/null 2>&1; then
    fail 'Falsche Prüfsumme wurde akzeptiert.'
fi
if find "$state/tmp" -mindepth 1 -maxdepth 1 -name 'archive.*' -print -quit | grep -q .; then
    fail 'Privater Archiv-Snapshot blieb nach abgelehnter Prüfsumme zurück.'
fi

rollback_nc="$stage/rollback-nextcloud"
rollback_state="$stage/rollback-state"
mkdir -p "$rollback_nc/custom_apps/demoapp/appinfo" "$rollback_state"
cp "$app/appinfo/info.xml" "$rollback_nc/custom_apps/demoapp/appinfo/info.xml"
cp "$nextcloud/occ" "$rollback_nc/occ"
printf 'old-code\n' > "$rollback_nc/custom_apps/demoapp/old-code.txt"
mkdir -p "$rollback_state/active"
printf '{"candidate_id":"old"}\n' > "$rollback_state/active/demoapp.json"
if FAKE_OCC_FAIL_SECOND_STATUS=1 \
    FAKE_OCC_STATUS_COUNT="$stage/rollback-status-count" \
    "$installer" \
    --nextcloud-root "$rollback_nc" \
    --php-bin "$(command -v php)" \
    --archive "$archive" \
    --sha256 "$hash" \
    --app-id demoapp \
    --commit "$commit" \
    --staging-target teamcloud \
    --state-dir "$rollback_state" >/dev/null 2>&1; then
    fail 'Fehlschlagende App-Aktivierung wurde als Erfolg gemeldet.'
fi
assert_file "$rollback_nc/custom_apps/demoapp/old-code.txt"
[[ ! -e "$rollback_nc/custom_apps/demoapp/new-code.txt" ]] || fail 'Neuer Code blieb nach Rollback aktiv.'
[[ ! -e "$rollback_state/active/demoapp.json" ]] || fail 'Alter aktiver Candidate blieb während/nach fehlgeschlagenem Deployment gültig.'

race_archive="$stage/output/demoapp-race.tar.gz"
cp "$archive" "$race_archive"
race_hash="$(sha256sum "$race_archive" | cut -d' ' -f1)"
race_replacement_root="$stage/race-replacement"
mkdir -p "$race_replacement_root/demoapp/appinfo"
cp "$app/appinfo/info.xml" "$race_replacement_root/demoapp/appinfo/info.xml"
printf 'replacement-code\n' > "$race_replacement_root/demoapp/replacement-code.txt"
tar -C "$race_replacement_root" -czf "$stage/output/demoapp-race-replacement.tar.gz" demoapp
replacement_hash="$(sha256sum "$stage/output/demoapp-race-replacement.tar.gz" | cut -d' ' -f1)"
[[ "$replacement_hash" != "$race_hash" ]] || fail 'Race-Fixture besitzt unerwartet denselben Digest wie das freigegebene Archiv.'

race_nc="$stage/race-nextcloud"
race_state="$stage/race-state"
mkdir -p "$race_nc/custom_apps/demoapp/appinfo" "$race_state" "$stage/race-bin"
cp "$app/appinfo/info.xml" "$race_nc/custom_apps/demoapp/appinfo/info.xml"
cp "$nextcloud/occ" "$race_nc/occ"
printf 'old-code\n' > "$race_nc/custom_apps/demoapp/old-code.txt"
mkfifo "$stage/race-lock-entered" "$stage/race-lock-release"
printf '%s\n' \
    '#!/usr/bin/env bash' \
    'set -euo pipefail' \
    'printf "entered\\n" > "$FAKE_FLOCK_ENTERED"' \
    'IFS= read -r _ < "$FAKE_FLOCK_RELEASE"' \
    > "$stage/race-bin/flock"
chmod 755 "$stage/race-bin/flock"

PATH="$stage/race-bin:$PATH" \
FAKE_FLOCK_ENTERED="$stage/race-lock-entered" \
FAKE_FLOCK_RELEASE="$stage/race-lock-release" \
FAKE_OCC_STATE="$stage/race-occ-enabled" \
FAKE_OCC_STATUS_COUNT="$stage/race-occ-status-count" \
"$installer" \
    --nextcloud-root "$race_nc" \
    --php-bin "$(command -v php)" \
    --archive "$race_archive" \
    --sha256 "$race_hash" \
    --app-id demoapp \
    --commit "$commit" \
    --staging-target teamcloud \
    --state-dir "$race_state" \
    --candidate-id CAND-2026-race \
    --deployment-id github-42-2-demoapp \
    --build-id build-race \
    --active-reader-user nobody \
    > "$stage/race-install.log" 2>&1 &
race_pid=$!

race_signal="$(timeout 10 head -n 1 "$stage/race-lock-entered")" \
    || fail 'Installer erreichte die deterministische Deployment-Lock-Grenze nicht.'
[[ "$race_signal" == entered ]] || fail 'Installer meldete eine ungültige Lock-Synchronisation.'
mapfile -t race_snapshot_dirs < <(find "$race_state/tmp" -mindepth 1 -maxdepth 1 -type d -name 'archive.*' -print)
[[ "${#race_snapshot_dirs[@]}" -eq 1 ]] || fail 'Installer hält am Lock nicht genau einen privaten Archiv-Snapshot.'
race_snapshot_dir="${race_snapshot_dirs[0]}"
race_snapshot="$race_snapshot_dir/demoapp.tar.gz"
[[ "$(stat -c '%a' -- "$race_snapshot_dir")" == 700 ]] || fail 'Privates Snapshot-Verzeichnis hat nicht Modus 700.'
[[ "$(stat -c '%a' -- "$race_snapshot")" == 600 ]] || fail 'Privater Archiv-Snapshot hat nicht Modus 600.'
[[ "$(sha256sum "$race_snapshot" | cut -d' ' -f1)" == "$race_hash" ]] \
    || fail 'Der private Snapshot ist vor Freigabe des Locks nicht an den erwarteten Digest gebunden.'
mv "$stage/output/demoapp-race-replacement.tar.gz" "$race_archive"
printf 'release\n' > "$stage/race-lock-release"
if ! wait "$race_pid"; then
    race_pid=''
    sed -n '1,120p' "$stage/race-install.log" >&2
    fail 'Installer scheiterte im synchronisierten Archiv-Austauschtest.'
fi
race_pid=''

[[ "$(sha256sum "$race_archive" | cut -d' ' -f1)" == "$replacement_hash" ]] \
    || fail 'Der Regressionstest hat den öffentlichen Archivpfad nicht wirksam ersetzt.'
assert_file "$race_nc/custom_apps/demoapp/new-code.txt"
[[ ! -e "$race_nc/custom_apps/demoapp/replacement-code.txt" ]] \
    || fail 'Nach Archiv-Austausch wurde nicht der freigegebene Snapshot installiert.'
assert_contains "$race_state/deployed/demoapp.tsv" "$race_hash"
assert_contains "$race_state/active/demoapp.json" "\"sha256\": \"$race_hash\""
if grep -Fq "$replacement_hash" "$race_state/deployed/demoapp.tsv" "$race_state/active/demoapp.json"; then
    fail 'Deploymentnachweis oder Marker wurde an das ausgetauschte Archiv gebunden.'
fi
if find "$race_state/tmp" -mindepth 1 -maxdepth 1 -name 'archive.*' -print -quit | grep -q .; then
    fail 'Privater Archiv-Snapshot blieb nach erfolgreicher Installation zurück.'
fi

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
    --staging-target teamcloud \
    --state-dir "$state" >/dev/null 2>&1; then
    fail 'Symlink im Archiv wurde akzeptiert.'
fi

assert_file "$workflow"
assert_file "$candidate_workflow"
assert_file "$runbook"
assert_contains "$runbook" 'Einmaliger Rebranding-Reinstall beim nächsten Staging-Lauf'
assert_contains "$runbook" 'Candidate-Deployments während des Clean Installs anhalten'
assert_contains "$runbook" 'Installationsreihenfolge des Clean Installs'
assert_contains "$runbook" 'Ausschließlich dieser private Snapshot wird per'
assert_contains "$runbook" 'weder den installierten Code noch den Deploymentmarker nachträglich ändern'
assert_contains "$runbook" '1. `localbase`'
assert_contains "$runbook" '2. `flz_data_protection`'
assert_contains "$runbook" '3. `flz_permission_matrix`'
assert_contains "$runbook" '4. `flzcalendar`, `flzplaner`, `flzurlaub`, `flzroom` und `flzrecruitment`'
assert_contains "$runbook" '5. `flzbqplanung`'
assert_contains "$runbook" '6. `orgsuite`'
for legacy_app_id in adcalendar adplaner adurlaub adroom adrecruitment adbqplanung filzmann_data_protection filzmann_permission_matrix; do
    assert_contains "$runbook" "$legacy_app_id"
done
for target_app_id in flzcalendar flzplaner flzurlaub flzroom flzrecruitment flzbqplanung flz_data_protection flz_permission_matrix; do
    assert_contains "$runbook" "$target_app_id"
done
assert_contains "$workflow" 'workflow_call:'
assert_contains "$workflow" 'candidate-id:'
assert_contains "$workflow" 'candidate-source-commit:'
assert_contains "$workflow" 'candidate-build-sha256:'
assert_contains "$workflow" 'candidate-staging-target:'
assert_contains "$workflow" 'staging-target:'
assert_contains "$workflow" 'CANDIDATE_STAGING_TARGET: ${{ inputs.candidate-staging-target }}'
assert_contains "$workflow" "environment: \${{ inputs.staging-target == 'teamcloud' && 'staging' || 'staging-br' }}"
assert_contains "$workflow" 'concurrency:'
assert_contains "$workflow" 'group: staging-${{ inputs.staging-target }}'
assert_contains "$workflow" 'CANDIDATE_ID: ${{ inputs.candidate-id }}'
assert_contains "$privacy_tests_workflow" 'staging-target: teamcloud'
assert_contains "$privacy_tests_workflow" 'source-ref: ${{ github.sha }}'
assert_contains "$input_validator" 'Candidate-Build stimmt nicht mit dem erzeugten Archiv überein.'
assert_contains "$workflow" 'if [[ "$DEPLOYMENT_MODE" == authoritative-candidate ]]'
assert_contains "$workflow" 'STAGING_SSH_PRIVATE_KEY'
assert_contains "$workflow" 'STAGING_SSH_KNOWN_HOSTS'
assert_contains "$workflow" '- name: Archiv über SSH-Gate installieren'
assert_contains "$workflow" '${STAGING_GATE_COMMAND} upload ${GITHUB_RUN_ID} ${GITHUB_RUN_ATTEMPT} ${APP_ID}'
assert_contains "$workflow" '< "$RUNNER_TEMP/staging-package/$APP_ID.tar.gz"'
assert_contains "$workflow" '${STAGING_GATE_COMMAND} install ${GITHUB_RUN_ID} ${GITHUB_RUN_ATTEMPT} ${APP_ID} ${SOURCE_COMMIT} ${SHA256}'
assert_contains "$workflow" '${CANDIDATE_ID} ${DEPLOYMENT_ID}'
assert_contains "$workflow" '- name: Temporäre Serverdateien entfernen'
assert_contains "$workflow" 'if: always()'
assert_contains "$workflow" '${STAGING_GATE_COMMAND} cleanup ${GITHUB_RUN_ID} ${GITHUB_RUN_ATTEMPT} ${APP_ID}'
assert_contains "$candidate_workflow" 'workflow_call:'
assert_contains "$candidate_workflow" 'id-token: write'
assert_contains "$candidate_workflow" 'source_ref="refs/heads/candidate/$work_order_id"'
assert_contains "$candidate_workflow" '"repository_id": os.environ["SOURCE_REPOSITORY_ID"]'
assert_contains "$candidate_contract" '/api/candidate-intake'
assert_contains "$candidate_contract" 'https://acceptance.staging.betriebsrat-ad.de/api/candidate-intake'
assert_contains "$candidate_contract" 'https://teamcloud.simonbeyer.de/api/candidate-intake'
assert_contains "$candidate_workflow" 'control/scripts/candidate-staging-contract resolve'
assert_contains "$candidate_workflow" 'control/scripts/candidate-staging-contract publish-response'
assert_contains "$candidate_workflow" '--github-env "$GITHUB_ENV"'
assert_contains "$candidate_workflow" '--github-output "$GITHUB_OUTPUT"'
assert_contains "$candidate_workflow" 'ACTIONS_ID_TOKEN_REQUEST_URL'
assert_contains "$candidate_workflow" 'candidate-id: ${{ needs.issue-candidate.outputs.candidate-id }}'
assert_contains "$candidate_workflow" 'candidate-build-sha256: ${{ needs.issue-candidate.outputs.build-sha256 }}'
assert_contains "$candidate_workflow" 'staging-target: ${{ needs.issue-candidate.outputs.staging-target }}'
assert_contains "$candidate_workflow" 'deploy-br-runtime-mirror:'
assert_contains "$candidate_workflow" "inputs.app-id == 'localbase' || inputs.app-id == 'orgsuite'"
assert_contains "$candidate_workflow" '      - deploy-candidate'
assert_contains "$candidate_workflow" 'staging-target: br-staging'
assert_contains "$candidate_workflow" 'candidate-staging-target: ${{ needs.issue-candidate.outputs.staging-target }}'
assert_contains "$candidate_workflow" 'candidate-source-commit: ${{ needs.issue-candidate.outputs.source-commit }}'
if grep -Fq 'STAGING_EVIDENCE_TOKEN' "$candidate_workflow"; then
    fail 'Candidate-Ausstellung verwendet einen statischen Evidence-Schreibtoken statt GitHub OIDC.'
fi
if grep -Fq 'CANDIDATE_INTAKE_URL: https://teamcloud.simonbeyer.de/api/candidate-intake' "$candidate_workflow"; then
    fail 'Candidate-Intake ist weiterhin global und unabhängig vom autoritativen Ziel auf Teamcloud festgelegt.'
fi
if grep -Fq '[[ "$response_staging_target" == '\''teamcloud'\'' || "$response_staging_target" == '\''br-staging'\'' ]]' "$candidate_workflow"; then
    fail 'Candidate-Workflow akzeptiert weiterhin ein anderes registriertes Ziel als das vorab aufgelöste autoritative Ziel.'
fi
control_checkout_line="$(grep -nF -- '- name: Zentrale Deploymentwerkzeuge auschecken' "$candidate_workflow" | cut -d: -f1)"
binding_validation_line="$(grep -nF -- '- name: GitHub- und Work-Order-Bindung validieren' "$candidate_workflow" | cut -d: -f1)"
[[ -n "$control_checkout_line" && -n "$binding_validation_line" && "$control_checkout_line" -lt "$binding_validation_line" ]] \
    || fail 'Der ausführbare Candidate-Vertrag wird nicht vor der Work-Order-Bindungsprüfung ausgecheckt.'
assert_contains "$workflow" '$STAGING_BASE_URL/custom_apps/$APP_ID/$CSS_ASSET'
assert_contains "$workflow" '$STAGING_BASE_URL/custom_apps/$APP_ID/$JS_ASSET'
asset_line="$(grep -nF -- '- name: Öffentliche Assets prüfen' "$workflow" | cut -d: -f1)"
verify_line="$(grep -nF -- '${STAGING_GATE_COMMAND} verify ${APP_ID} ${CANDIDATE_ID} ${DEPLOYMENT_ID}' "$workflow" | cut -d: -f1)"
[[ -n "$asset_line" && -n "$verify_line" && "$verify_line" -gt "$asset_line" ]] \
    || fail 'Candidate wird nicht erst nach dem öffentlichen HTTPS-Smoke als verifiziert markiert.'
if grep -Fq '$STAGING_BASE_URL/apps/$APP_ID/' "$workflow"; then
    fail 'Workflow prüft Custom-App-Assets über den nicht konfigurierten Core-App-URL-Pfad.'
fi
if grep -Fq 'control/scripts/install-staging-app.sh' "$workflow"; then
    fail 'Workflow lädt den root-eigenen Server-Installer unzulässig pro Deployment hoch.'
fi
if grep -Fq 'CONTROL_REPOSITORY_TOKEN' "$workflow"; then
    fail 'Öffentliches Parent-Repository verlangt unnötig ein zusätzliches Zugriffstoken.'
fi
if grep -Eq '(^|[[:space:]])scp([[:space:]]|$)' "$workflow"; then
    fail 'Workflow umgeht das SSH-Gate mit einem direkten SCP-Aufruf.'
fi
if grep -Fq 'sudo -n -u simonbeyer_sys' "$workflow"; then
    fail 'Workflow umgeht das SSH-Gate mit einem direkten Sudo-Aufruf.'
fi
if grep -Fq '/var/tmp/teamcloud-staging-incoming' "$workflow"; then
    fail 'Workflow greift direkt auf das serverseitige Incoming-Verzeichnis zu.'
fi

assert_file "$server_wrapper"
assert_contains "$server_wrapper" '/var/www/vhosts/simonbeyer.de/teamcloud.simonbeyer.de'
assert_contains "$server_wrapper" '/opt/plesk/php/8.4/bin/php'
assert_contains "$server_wrapper" '/usr/local/libexec/teamcloud-staging/install-staging-app.sh'
assert_contains "$server_wrapper" '/var/lib/teamcloud-staging'
assert_contains "$server_wrapper" '--php-memory-limit 512M'
assert_contains "$server_wrapper" '--candidate-id "$candidate_id"'
assert_contains "$server_wrapper" '--deployment-id "$deployment_id"'
assert_contains "$server_wrapper" "active_reader='filzmann_acceptance'"
assert_contains "$server_wrapper" '--active-reader-user "$active_reader"'
assert_contains "$server_wrapper" 'verify-staging-deployment-marker.php'
assert_contains "$server_wrapper" '--lock-file "$state_dir/deploy.lock"'
assert_contains "$deployment_verifier" 'flock($deployLock, LOCK_EX)'
assert_contains "$deployment_verifier" 'hash_equals($originalDigest, $currentDigest)'
assert_contains "$ssh_gate" 'verify)'
if tail -n +265 "$ssh_gate" | grep -Fq 'candidate_id'; then
    fail 'Candidate-Validierung liegt außerhalb der install-Funktion und läuft nach möglichen Seiteneffekten.'
fi
if "$server_wrapper" /var/tmp/teamcloud-staging-incoming/1-1-foreign/foreign.tar.gz foreign "$commit" "$hash" >/dev/null 2>&1; then
    fail 'Server-Wrapper akzeptiert eine nicht freigegebene App-ID.'
fi
if "$server_wrapper" /tmp/demoapp.tar.gz demoapp "$commit" "$hash" >/dev/null 2>&1; then
    fail 'Server-Wrapper akzeptiert ein Archiv außerhalb des Incoming-Verzeichnisses.'
fi

deployment_apps=(
    flzplaner
    localbase
    flz_permission_matrix
    flzcalendar
    flzurlaub
    orgsuite
    flzroom
    flzrecruitment
    flzbqplanung
)

for app_id in "${deployment_apps[@]}"; do
    assert_contains "$input_validator" "$app_id"
    assert_contains "$ssh_gate" "$app_id"
    assert_contains "$server_wrapper" "$app_id"
    tests_workflow="$workspace/$app_id/.github/workflows/tests.yml"
    caller="$workspace/$app_id/.github/workflows/candidate.yml"
    assert_contains "$tests_workflow" "'candidate/WO-*'"
    if grep -Fq 'deploy-staging:' "$tests_workflow"; then
        fail "$app_id deployt weiterhin unmittelbar aus dem Testworkflow."
    fi
    assert_contains "$caller" 'workflow_run:'
    assert_contains "$caller" "github.event.workflow_run.conclusion == 'success'"
    assert_contains "$caller" "startsWith(github.event.workflow_run.head_branch, 'candidate/WO-')"
    assert_contains "$caller" 'id-token: write'
    assert_contains "$caller" 'Filzmann/br-nextcloud-apps/.github/workflows/candidate-staging.yml@main'
    assert_contains "$caller" "app-id: $app_id"
    assert_contains "$caller" 'source-commit: ${{ github.event.workflow_run.head_sha }}'
done

assert_contains "$input_validator" 'flz_data_protection'
assert_contains "$privacy_tests_workflow" 'deploy-staging:'
assert_contains "$privacy_tests_workflow" 'Filzmann/br-nextcloud-apps/.github/workflows/deploy-staging.yml@main'

for br_app_id in brtop brstunden; do
    assert_contains "$input_validator" "$br_app_id"
    if grep -Eq "(^|[|[:space:]])${br_app_id}([|)])" "$ssh_gate"; then
        fail "$br_app_id ist weiterhin im autoritativen Teamcloud-SSH-Gate freigegeben."
    fi
    if grep -Eq "(^|[|[:space:]])${br_app_id}([|)])" "$server_wrapper"; then
        fail "$br_app_id ist weiterhin im autoritativen Teamcloud-Installer freigegeben."
    fi
    tests_workflow="$workspace/$br_app_id/.github/workflows/tests.yml"
    caller="$workspace/$br_app_id/.github/workflows/candidate.yml"
    assert_contains "$tests_workflow" "'candidate/WO-*'"
    assert_contains "$caller" "app-id: $br_app_id"
done

echo 'Staging-Deployment-Contract: OK'
