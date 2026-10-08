#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
runner="$workspace/scripts/verify-nextcloud-compatibility"
real_driver="$workspace/scripts/run-nextcloud-ddev-compatibility-stage"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

fail() {
    echo "FEHLER: $*" >&2
    exit 1
}

[[ -x "$real_driver" ]] || fail 'Der getrackte DDEV-Runtime-Driver fehlt oder ist nicht ausführbar.'
grep -Fq 'omit_containers:' "$real_driver" || fail 'Der Runtime-Driver isoliert den Datenbankcontainer nicht.'
grep -Fq -- '--database=sqlite' "$real_driver" || fail 'Der Runtime-Driver verwendet keine isolierte SQLite-Datenbank.'
grep -Fq 'NC_COMPAT_DATABASE' "$real_driver" || fail 'Der Runtime-Driver besitzt keinen expliziten Datenbankmodus.'
grep -Fq 'type: postgres' "$real_driver" || fail 'Der Runtime-Driver kann keinen isolierten PostgreSQL-Container konfigurieren.'
grep -Fq -- '--database=pgsql' "$real_driver" || fail 'Der Runtime-Driver installiert Nextcloud nicht gegen PostgreSQL.'
grep -Fq 'NC_COMPAT_OBJECT_STORAGE' "$real_driver" || fail 'Der Runtime-Driver besitzt keinen expliziten Object-Storage-Modus.'
grep -Fq '0d7408fc9969caf07de6a8c3a84f9fbb10a6739e' "$real_driver" \
    || fail 'Der Runtime-Driver pinnt den freigegebenen offiziellen MinIO-Quellcommit nicht.'
grep -Fq 'docker.io/library/golang@sha256:7772cb5322baa875edd74705556d08f0eeca7b9c4b5367754ce3f2f00041ccee' "$real_driver" \
    || fail 'Der Runtime-Driver pinnt den MinIO-Builder nicht per unveränderlichem Digest.'
grep -Fq "'class' => '\\\\OC\\\\Files\\\\ObjectStore\\\\S3'" "$real_driver" \
    || fail 'Der Runtime-Driver konfiguriert Nextcloud nicht gegen den nativen S3-Object-Store.'
grep -Fq 'tmpfs:' "$real_driver" || fail 'Der Runtime-Driver hält die isolierten MinIO-Daten nicht flüchtig.'
grep -Fq 'compatibility-object-storage.php' "$real_driver" \
    || fail 'Der Runtime-Driver besitzt keinen getrennten HTTP-/FPM-Object-Storage-Schreiber.'
grep -Fq 'runtime_smokes object-storage-job' "$real_driver" \
    || fail 'Der Runtime-Driver liest den Object-Storage-Zustand nicht in einem neuen Jobprozess.'
grep -Fq 'compatibility-app-object-storage.php' "$real_driver" \
    || fail 'Der Runtime-Driver besitzt keinen app-spezifischen Object-Storage-Webprozess.'
grep -Fq 'object-storage-app-web-' "$real_driver" \
    || fail 'Der Runtime-Driver führt app-spezifische Object-Storage-Setups nicht über PHP-FPM aus.'
grep -Fq "'objectStorageJobVerify'" "$real_driver" \
    || fail 'Der Runtime-Driver führt keine app-spezifischen Object-Storage-Prüfungen im frischen Jobprozess aus.'
grep -Fq -- "--header 'OCS-APIRequest: true'" "$real_driver" \
    || fail 'Der Runtime-Driver kennzeichnet authentifizierte API-Smokes nicht als tokenfreien Nextcloud-API-Client.'
grep -Fq 'env OC_PASS=compat-target-development-only php occ user:add' "$real_driver" \
    || fail 'Der Runtime-Driver legt das synthetische Zielkonto nicht mit einer containerlokalen Passwortübergabe an.'
grep -Fq 'verify_postgresql_upgrade_runtime postgresql-upgrade-seed' "$real_driver" \
    || fail 'Der Runtime-Driver legt vor einem PostgreSQL-Upgrade keine app-spezifischen Bestandsdaten an.'
grep -Fq 'verify_postgresql_upgrade_runtime postgresql-upgrade-verify' "$real_driver" \
    || fail 'Der Runtime-Driver prüft app-spezifische Bestandsdaten nach dem PostgreSQL-Upgrade nicht.'
grep -Fq 'nextcloud-compatibility-smoke.php' "$real_driver" || fail 'Der Runtime-Driver nutzt den app-lokalen Smoke-Vertrag nicht.'
grep -Fq 'runtime_smokes providers "$effective_major" > "$result_dir/providers-$suffix.tsv"' "$real_driver" \
    || fail 'Der Runtime-Driver prüft Provider-Discovery nicht in jedem Lifecycle-Zustand.'
grep -Fq 'NC_COMPAT_ROLLBACK_APPS_ROOT' "$real_driver" \
    || fail 'Der Runtime-Driver besitzt keinen expliziten Rollback-Snapshotvertrag.'
snapshot_line="$(grep -n '^copy_app_snapshots$' "$real_driver" | cut -d: -f1)"
preflight_line="$(grep -n '^verify_app_tests "\$major"$' "$real_driver" | cut -d: -f1)"
install_line="$(grep -n '^install_core "\$major"$' "$real_driver" | cut -d: -f1)"
overlay_line="$(grep -n '^install_app_snapshots "\$major"$' "$real_driver" | cut -d: -f1)"
[[ -n "$snapshot_line" && -n "$preflight_line" && -n "$install_line" && -n "$overlay_line" \
    && "$snapshot_line" -lt "$preflight_line" && "$preflight_line" -lt "$install_line" \
    && "$install_line" -lt "$overlay_line" ]] \
    || fail 'Commit-Preflight, leere Core-Installation und App-Overlay sind falsch geordnet.'
[[ "$(grep -Ec '^[[:space:]]*write_runtime_smoke_runner$' "$real_driver")" -eq 2 ]] \
    || fail 'Der Runtime-Smoke-Bootstrap wird nach einem Core-Upgrade nicht erneuert.'
seed_line="$(grep -n 'verify_postgresql_upgrade_runtime postgresql-upgrade-seed' "$real_driver" | tail -n 1 | cut -d: -f1)"
stop_line="$(grep -n 'ddev stop.*ddev-stop-before-upgrade' "$real_driver" | cut -d: -f1)"
upgrade_line="$(grep -n 'occ upgrade.*upgrade-' "$real_driver" | cut -d: -f1)"
verify_line="$(grep -n 'verify_postgresql_upgrade_runtime postgresql-upgrade-verify' "$real_driver" | tail -n 1 | cut -d: -f1)"
[[ -n "$seed_line" && -n "$stop_line" && -n "$upgrade_line" && -n "$verify_line" \
    && "$seed_line" -lt "$stop_line" && "$upgrade_line" -lt "$verify_line" ]] \
    || fail 'PostgreSQL-Seed und -Verifikation umschließen das reale Core-Upgrade nicht.'

# Exercise the generated runtime program, not just a keyword in its source.
mkdir -p "$stage/runtime/lib"
python3 - "$real_driver" "$stage/runtime/compatibility-grants.php" <<'PY'
from pathlib import Path
import sys
source = Path(sys.argv[1]).read_text()
program = source.split('cat > "$project/html/compatibility-grants.php" <<\'PHP\'\n', 1)[1].split('\nPHP\n', 1)[0]
Path(sys.argv[2]).write_text(program)
PY
cat > "$stage/runtime/lib/base.php" <<'PHP'
<?php
namespace OCP {
    class Server {
        public static function get(string $id): object {
            return match ($id) {
                'OCP\IUserManager' => new class { public function get(string $uid): object { return new \stdClass(); } },
                'OCP\IUserSession' => new class { public function setUser(object $user): void {} },
                'OCP\IGroupManager' => new class {
                    public function get(string $group): ?object { return null; }
                    public function createGroup(string $group): object {
                        return new class($group) {
                            public function __construct(private string $group) {}
                            public function addUser(object $user): void { file_put_contents(getenv('GRANT_GROUP_LOG'), $this->group . "\n", FILE_APPEND); }
                        };
                    }
                },
                'OCP\App\IAppManager' => new class {
                    public function isEnabledForUser(string $app): bool { return getenv('DISABLED_OWNER') !== $app; }
                },
                'OCP\EventDispatcher\IEventDispatcher' => new class {
                    public function dispatchTyped(object $event): void {
                        file_put_contents(getenv('PROVIDER_DISPATCH_LOG'), "dispatch\n", FILE_APPEND);
                    }
                },
                default => throw new \RuntimeException('Unexpected service: ' . $id),
            };
        }
    }
}
namespace {
    class SyntheticRegistryEvent {
        public function providers(): array {
            return getenv('PROVIDER_FAULT') === 'missing' ? [] : ['demoapp' => new \stdClass(), 'otherapp' => new \stdClass()];
        }
        public function registrationFailures(): array {
            return getenv('PROVIDER_FAULT') === 'incompatible' ? ['demoapp' => 'Provider incompatible.'] : [];
        }
    }
    class SyntheticLegacyRegistryEvent {
        public function providers(): array {
            return ['demoapp' => new \stdClass(), 'otherapp' => new \stdClass()];
        }
    }
}
PHP
for app in demoapp otherapp; do
    mkdir -p "$stage/runtime/custom_apps/$app/tests"
    cat > "$stage/runtime/custom_apps/$app/tests/nextcloud-compatibility-smoke.php" <<'PHP'
<?php
return [
    'uiPath' => '/', 'preGrantUiStatuses' => [200], 'postGrantUiStatuses' => [200],
    'grantService' => null, 'grantManagerGroups' => ['demo-privacy-group'], 'permissionProbe' => null, 'apiSmokes' => [],
    'providerSetup' => static function(): void { file_put_contents(getenv('PROVIDER_SETUP_LOG'), "setup\n", FILE_APPEND); },
    'providerRegistrations' => ['demo_owner' => [SyntheticRegistryEvent::class, SyntheticLegacyRegistryEvent::class]],
    'postgresqlUpgradeSeed' => static function(string $uid): void { file_put_contents(getenv('POSTGRESQL_SEED_LOG'), $uid . "\n", FILE_APPEND); },
    'postgresqlUpgradeVerify' => static function(string $uid): void { file_put_contents(getenv('POSTGRESQL_VERIFY_LOG'), $uid . "\n", FILE_APPEND); },
];
PHP
done
PROVIDER_SETUP_LOG="$stage/provider-setup.log" php "$stage/runtime/compatibility-grants.php" setup-providers > "$stage/provider-setup.tsv"
[[ "$(wc -l < "$stage/provider-setup.log")" -eq 2 ]] || fail 'App-lokales Provider-Setup wurde nicht vollständig ausgeführt.'
PROVIDER_DISPATCH_LOG="$stage/dispatch.log" php "$stage/runtime/compatibility-grants.php" providers > "$stage/providers.tsv"
[[ "$(wc -l < "$stage/dispatch.log")" -eq 2 ]] || fail 'Registries were not dispatched exactly once each.'
grep -Fqx $'demoapp\tSyntheticRegistryEvent\tregistered' "$stage/providers.tsv" || fail 'Provider registration was not proved.'
grep -Fqx $'otherapp\tSyntheticRegistryEvent\tregistered' "$stage/providers.tsv" || fail 'Second provider registration was not proved.'
grep -Fqx $'demoapp\tSyntheticLegacyRegistryEvent\tregistered' "$stage/providers.tsv" || fail 'Legacy provider registration was not proved.'
grep -Fqx $'otherapp\tSyntheticLegacyRegistryEvent\tregistered' "$stage/providers.tsv" || fail 'Second legacy provider registration was not proved.'
for fault in missing incompatible; do
    if PROVIDER_FAULT="$fault" PROVIDER_DISPATCH_LOG="$stage/dispatch.log" php "$stage/runtime/compatibility-grants.php" providers > "$stage/provider-fault.log" 2>&1; then
        fail "A $fault provider was accepted after installation."
    fi
    grep -Eq 'Provider (missing|registration failed)' "$stage/provider-fault.log" || fail 'Provider failure was not diagnosed.'
done
DISABLED_OWNER=demo_owner PROVIDER_DISPATCH_LOG="$stage/disabled-dispatch.log" php "$stage/runtime/compatibility-grants.php" providers > "$stage/disabled-providers.tsv"
[[ ! -e "$stage/disabled-dispatch.log" ]] || fail 'A disabled optional owner was dispatched.'
grep -Fq 'owner-unavailable' "$stage/disabled-providers.tsv" || fail 'The missing optional owner was hidden.'

python3 - "$stage/runtime/custom_apps/demoapp/tests/nextcloud-compatibility-smoke.php" <<'PY'
from pathlib import Path
import sys
p = Path(sys.argv[1])
p.write_text(p.read_text().replace(
    "'grantService' => null, 'grantManagerGroups' => ['demo-privacy-group'], 'permissionProbe' => null",
    "'grantService' => static function(string $uid): void { file_put_contents(getenv('GRANT_LOG'), $uid); }, 'grantManagerGroups' => ['demo-privacy-group'], 'permissionProbe' => static fn(string $uid): bool => is_file(getenv('GRANT_LOG'))"
))
PY
GRANT_LOG="$stage/grant.log" php "$stage/runtime/compatibility-grants.php" pre > /dev/null
[[ ! -e "$stage/grant.log" ]] || fail 'The pre-grant probe changed access.'
GRANT_LOG="$stage/grant.log" GRANT_GROUP_LOG="$stage/grant-groups.log" php "$stage/runtime/compatibility-grants.php" grant > /dev/null
[[ "$(wc -l < "$stage/grant-groups.log")" -eq 2 ]] || fail 'The app-local grant-manager group setup did not run.'
[[ "$(cat "$stage/grant.log")" == compat-admin ]] || fail 'The app-local native grant setup did not run.'
POSTGRESQL_SEED_LOG="$stage/postgresql-seed.log" php "$stage/runtime/compatibility-grants.php" postgresql-upgrade-seed > "$stage/postgresql-seed.tsv"
[[ "$(wc -l < "$stage/postgresql-seed.log")" -eq 2 ]] || fail 'App-lokale PostgreSQL-Bestandsdaten wurden nicht vollständig angelegt.'
POSTGRESQL_VERIFY_LOG="$stage/postgresql-verify.log" php "$stage/runtime/compatibility-grants.php" postgresql-upgrade-verify > "$stage/postgresql-verify.tsv"
[[ "$(wc -l < "$stage/postgresql-verify.log")" -eq 2 ]] || fail 'App-lokale PostgreSQL-Bestandsdaten wurden nicht vollständig nachgeprüft.'

# Every included app supplies its own expectations; the Parent has no app list.
php -r '
    $root=$argv[1];
    $rows=array_map(static fn($line)=>str_getcsv($line,"\t","\"",""),file($root."/config/workspace-repositories.tsv",FILE_IGNORE_NEW_LINES));
    foreach($rows as [$path,$kind,$id]) {
        if($kind!=="app" || $id==="localbase")continue;
        $file=$root."/".$path."/tests/nextcloud-compatibility-smoke.php";
        if(!is_file($file))throw new RuntimeException("App runtime smoke missing: ".$id);
        $contract=require $file;
        foreach(["uiPath","preGrantUiStatuses","postGrantUiStatuses","grantService","permissionProbe","apiSmokes","providerRegistrations"] as $key) {
            if(!array_key_exists($key,$contract))throw new RuntimeException("App runtime contract incomplete: ".$id." / ".$key);
        }
    }
' "$workspace"

for app in brtop flzrecruitment flzcalendar; do
    contract="$workspace/$app/tests/nextcloud-compatibility-smoke.php"
    grep -Fq "'objectStorageWebSetup'" "$contract" \
        || fail "$app besitzt kein app-spezifisches Object-Storage-Websetup."
    grep -Fq "'objectStorageJobVerify'" "$contract" \
        || fail "$app besitzt keinen app-spezifischen Object-Storage-Jobnachweis."
    grep -Fq "'postgresqlUpgradeSeed'" "$contract" \
        || fail "$app besitzt kein app-spezifisches PostgreSQL-Upgrade-Setup."
    grep -Fq "'postgresqlUpgradeVerify'" "$contract" \
        || fail "$app besitzt keinen app-spezifischen PostgreSQL-Upgrade-Nachweis."
done

make_server() {
    local major="$1"
    local patch="$2"
    local root="$stage/server-$major/nextcloud"
    mkdir -p "$root"
    cat > "$root/version.php" <<PHP
<?php
\$OC_Version = [$major, 0, $patch, 0];
\$OC_VersionString = '$major.0.$patch';
PHP
    printf '#!/usr/bin/env php\n' > "$root/occ"
    chmod +x "$root/occ"
    tar -C "$stage/server-$major" -czf "$stage/nextcloud-$major.tar.gz" nextcloud
}

make_app() {
    local app="$1"
    local root="$stage/$app"
    mkdir -p "$root/appinfo"
    git -C "$root" init -q
    git -C "$root" config user.name Test
    git -C "$root" config user.email test@example.invalid
    cat > "$root/appinfo/info.xml" <<XML
<?xml version="1.0"?>
<info><id>$app</id><version>1.0.0</version><dependencies><nextcloud min-version="33" max-version="34"/></dependencies></info>
XML
    git -C "$root" add appinfo/info.xml
    git -C "$root" commit -qm initial
}

make_server 33 7
make_server 34 2
make_server 35 0
make_app demoapp

mkdir -p "$stage/original-ddev/.ddev" "$stage/bin"
cat > "$stage/original-ddev/.ddev/config.yaml" <<'YAML'
name: nextcloud-dev
YAML
cat > "$stage/bin/ddev" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\n' "$*" >> "$DDEV_LOG"
if [[ "${1:-}" == describe && "${2:-}" == -j ]]; then
    printf '{"raw":{"name":"nextcloud-dev","status":"%s"}}\n' "${DDEV_FAKE_STATUS:-stopped}"
fi
SH
chmod +x "$stage/bin/ddev"

cat > "$stage/driver" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
printf '%s\tupdate-root=%s\n' "$*" "${NC_COMPAT_UPDATE_APPS_ROOT:-none}" >> "$DRIVER_LOG"
mkdir -p "${@: -1}"
printf 'green\n' > "${@: -1}/result.txt"
SH
chmod +x "$stage/driver"

sha33="$(sha256sum "$stage/nextcloud-33.tar.gz" | cut -d' ' -f1)"
sha34="$(sha256sum "$stage/nextcloud-34.tar.gz" | cut -d' ' -f1)"
sha35="$(sha256sum "$stage/nextcloud-35.tar.gz" | cut -d' ' -f1)"
app_commit="$(git -C "$stage/demoapp" rev-parse HEAD)"
sed -i 's/<version>1.0.0<\//<version>1.1.0<\//' "$stage/demoapp/appinfo/info.xml"
git -C "$stage/demoapp" add appinfo/info.xml
git -C "$stage/demoapp" commit -qm update
update_commit="$(git -C "$stage/demoapp" rev-parse HEAD)"

PATH="$stage/bin:$PATH" DDEV_LOG="$stage/ddev.log" DRIVER_LOG="$stage/driver.log" "$runner" \
    --server "33:$stage/nextcloud-33.tar.gz:$sha33:v33.0.7:1111111111111111111111111111111111111111" \
    --server "34:$stage/nextcloud-34.tar.gz:$sha34:v34.0.2:2222222222222222222222222222222222222222" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --update-app "demoapp:$stage/demoapp:$update_commit" \
    --driver "$stage/driver" \
    --reserved-ddev-root "$stage/original-ddev" \
    --evidence-dir "$stage/evidence"

[[ "$(grep -c '^fresh ' "$stage/driver.log")" -eq 2 ]] || fail 'Fresh stage did not run once per major.'
grep -Fq 'upgrade 33 34 ' "$stage/driver.log" || fail 'Consecutive upgrade stage did not run.'
grep -Fq $'server\t33\tv33.0.7\t1111111111111111111111111111111111111111' "$stage/evidence/manifest.tsv" \
    || fail 'Pinned server identity is missing from the manifest.'
grep -Fq $'app\tdemoapp\t'"$stage/demoapp"$'\t'"$app_commit" "$stage/evidence/manifest.tsv" \
    || fail 'Pinned app commit is missing from the manifest.'
grep -Fq $'update-app\tdemoapp\t'"$stage/demoapp"$'\t'"$update_commit" "$stage/evidence/manifest.tsv" \
    || fail 'Pinned update app commit is missing from the manifest.'
grep -Fq $'update-root='"$stage/evidence/update-apps" "$stage/driver.log" \
    || fail 'The pinned update app root was not handed to the runtime driver.'
[[ "$(grep -Fxc 'stop --unlist' "$stage/ddev.log")" -eq 1 ]] \
    || fail 'Reserved DDEV registration was not handed off exactly once.'
tail -n 2 "$stage/ddev.log" | grep -Fqx 'start -y' \
    || fail 'Stopped reserved DDEV project was not registered again.'
tail -n 1 "$stage/ddev.log" | grep -Fqx 'stop' \
    || fail 'Originally stopped DDEV project was not returned to stopped state.'

cat > "$stage/failing-driver" <<'SH'
#!/usr/bin/env bash
exit 23
SH
chmod +x "$stage/failing-driver"
: > "$stage/ddev-failure.log"
if PATH="$stage/bin:$PATH" DDEV_LOG="$stage/ddev-failure.log" "$runner" \
    --server "33:$stage/nextcloud-33.tar.gz:$sha33:v33.0.7:1111111111111111111111111111111111111111" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --driver "$stage/failing-driver" \
    --reserved-ddev-root "$stage/original-ddev" \
    --evidence-dir "$stage/failed-evidence" >/dev/null 2>&1; then
    fail 'A failing runtime driver was accepted.'
fi
tail -n 2 "$stage/ddev-failure.log" | grep -Fqx 'start -y' \
    || fail 'Reserved DDEV registration was not restored after driver failure.'
tail -n 1 "$stage/ddev-failure.log" | grep -Fqx 'stop' \
    || fail 'Reserved DDEV stopped state was not restored after driver failure.'

if "$runner" \
    --server "33:$stage/nextcloud-33.tar.gz:ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff:v33.0.7:1111111111111111111111111111111111111111" \
    --server "34:$stage/nextcloud-34.tar.gz:$sha34:v34.0.2:2222222222222222222222222222222222222222" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --driver "$stage/driver" \
    --evidence-dir "$stage/bad-sha" >/dev/null 2>&1; then
    fail 'A wrong server checksum was accepted.'
fi

if "$runner" \
    --server "33:$stage/nextcloud-33.tar.gz:$sha33:v33.0.7:1111111111111111111111111111111111111111" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --update-app "demoapp:$stage/demoapp:$app_commit" \
    --driver "$stage/driver" \
    --evidence-dir "$stage/bad-update-version" >/dev/null 2>&1; then
    fail 'A non-increasing app update version was accepted.'
fi

if DRIVER_LOG="$stage/rejected-driver.log" "$runner" \
    --server "32:$stage/nextcloud-33.tar.gz:$sha33:v32.0.0:1111111111111111111111111111111111111111" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --driver "$stage/driver" \
    --evidence-dir "$stage/bad-major" >"$stage/bad-major.log" 2>&1; then
    fail 'A mismatching server major was accepted.'
fi
grep -Fq 'version.php meldet Major 33 statt 32.' "$stage/bad-major.log" \
    || fail 'The mismatching archive was not rejected for its actual major.'

# Both archives are valid: this must reach the continuity guard, independently
# of the archive/version mismatch above. No runtime stage may start on a gap.
if DRIVER_LOG="$stage/rejected-driver.log" "$runner" \
    --server "33:$stage/nextcloud-33.tar.gz:$sha33:v33.0.7:1111111111111111111111111111111111111111" \
    --server "35:$stage/nextcloud-35.tar.gz:$sha35:v35.0.0:3333333333333333333333333333333333333333" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --driver "$stage/driver" \
    --evidence-dir "$stage/major-gap" >"$stage/major-gap.log" 2>&1; then
    fail 'A non-consecutive server major was accepted.'
fi
grep -Fq 'Servermajors sind nicht lückenlos aufsteigend: 33, 35' "$stage/major-gap.log" \
    || fail 'The valid archives were not rejected for the gap in their majors.'
[[ ! -e "$stage/rejected-driver.log" ]] \
    || fail 'An invalid server matrix started a runtime stage.'

echo 'Nextcloud-Compatibility-Runner-Vertrag: OK'
