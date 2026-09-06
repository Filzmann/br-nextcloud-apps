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
grep -Fq 'nextcloud-compatibility-smoke.php' "$real_driver" || fail 'Der Runtime-Driver nutzt den app-lokalen Smoke-Vertrag nicht.'
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
printf '%s\n' "$*" >> "$DRIVER_LOG"
mkdir -p "${@: -1}"
printf 'green\n' > "${@: -1}/result.txt"
SH
chmod +x "$stage/driver"

sha33="$(sha256sum "$stage/nextcloud-33.tar.gz" | cut -d' ' -f1)"
sha34="$(sha256sum "$stage/nextcloud-34.tar.gz" | cut -d' ' -f1)"
app_commit="$(git -C "$stage/demoapp" rev-parse HEAD)"

PATH="$stage/bin:$PATH" DDEV_LOG="$stage/ddev.log" DRIVER_LOG="$stage/driver.log" "$runner" \
    --server "33:$stage/nextcloud-33.tar.gz:$sha33:v33.0.7:1111111111111111111111111111111111111111" \
    --server "34:$stage/nextcloud-34.tar.gz:$sha34:v34.0.2:2222222222222222222222222222222222222222" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --driver "$stage/driver" \
    --reserved-ddev-root "$stage/original-ddev" \
    --evidence-dir "$stage/evidence"

[[ "$(grep -c '^fresh ' "$stage/driver.log")" -eq 2 ]] || fail 'Fresh stage did not run once per major.'
grep -Fq 'upgrade 33 34 ' "$stage/driver.log" || fail 'Consecutive upgrade stage did not run.'
grep -Fq $'server\t33\tv33.0.7\t1111111111111111111111111111111111111111' "$stage/evidence/manifest.tsv" \
    || fail 'Pinned server identity is missing from the manifest.'
grep -Fq $'app\tdemoapp\t'"$stage/demoapp"$'\t'"$app_commit" "$stage/evidence/manifest.tsv" \
    || fail 'Pinned app commit is missing from the manifest.'
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
    --server "32:$stage/nextcloud-33.tar.gz:$sha33:v32.0.0:1111111111111111111111111111111111111111" \
    --server "34:$stage/nextcloud-34.tar.gz:$sha34:v34.0.2:2222222222222222222222222222222222222222" \
    --app "demoapp:$stage/demoapp:$app_commit" \
    --driver "$stage/driver" \
    --evidence-dir "$stage/bad-major" >/dev/null 2>&1; then
    fail 'A mismatching or non-consecutive server major was accepted.'
fi

echo 'Nextcloud-Compatibility-Runner-Vertrag: OK'
