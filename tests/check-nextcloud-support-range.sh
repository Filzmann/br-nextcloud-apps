#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
checker="$workspace/scripts/validate-nextcloud-support-range.php"
temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT

write_info() {
    local path="$1"
    local app_id="$2"
    local minimum="$3"
    local maximum="$4"
    mkdir -p "$(dirname "$path")"
    cat > "$path" <<XML
<?xml version="1.0"?>
<info>
  <id>$app_id</id>
  <dependencies>
    <nextcloud min-version="$minimum" max-version="$maximum"/>
  </dependencies>
</info>
XML
}

write_info "$temporary/exact-floor.xml" demoapp 33 34
php "$checker" "$temporary/exact-floor.xml" demoapp 33 34

write_info "$temporary/wide-range.xml" demoapp 29 35
php "$checker" "$temporary/wide-range.xml" demoapp 33 34
php "$checker" "$temporary/wide-range.xml" demoapp 33 35

assert_rejected() {
    local expected="$1"
    shift
    local output
    set +e
    output="$(php "$checker" "$@" 2>&1)"
    local status=$?
    set -e
    (( status != 0 )) || {
        echo "Ungültiger Nextcloud-Supportbereich wurde akzeptiert: $*" >&2
        exit 1
    }
    grep -Fq "$expected" <<< "$output" || {
        echo "Fehlermeldung nennt den verletzten Bereichsvertrag nicht: $output" >&2
        exit 1
    }
}

write_info "$temporary/missing-floor.xml" demoapp 34 35
assert_rejected 'OpenDesk-Boden 33' "$temporary/missing-floor.xml" demoapp 33 34

write_info "$temporary/missing-target.xml" demoapp 29 33
assert_rejected 'Release-Ziel 34' "$temporary/missing-target.xml" demoapp 33 34

write_info "$temporary/reversed.xml" demoapp 35 33
assert_rejected 'ungültig' "$temporary/reversed.xml" demoapp 33 34

write_info "$temporary/wrong-id.xml" otherapp 29 35
assert_rejected 'App-ID' "$temporary/wrong-id.xml" demoapp 33 34

assert_rejected 'Release-Ziel' "$temporary/wide-range.xml" demoapp 33 32

for app in localbase adrecruitment adroom adurlaub orgsuite; do
    php "$checker" "$workspace/$app/appinfo/info.xml" "$app" 33 34
done

for delivery_script in \
    "$workspace/scripts/build-ad-suite-release.sh" \
    "$workspace/scripts/verify-ad-suite-delivery.sh"; do
    grep -Fq 'NEXTCLOUD_TARGET_MAJOR' "$delivery_script" || {
        echo "Delivery-Skript besitzt keine explizite Nextcloud-Zielmajor: $delivery_script" >&2
        exit 1
    }
    grep -Fq 'scripts/validate-nextcloud-support-range.php' "$delivery_script" || {
        echo "Delivery-Skript verwendet den zentralen Bereichsvalidator nicht: $delivery_script" >&2
        exit 1
    }
    grep -Fq '"$info" "$app" 33 "$nextcloud_target_major"' "$delivery_script" || {
        echo "Delivery-Skript erzwingt Boden 33 und Zielmajor nicht gemeinsam: $delivery_script" >&2
        exit 1
    }
done

echo 'Nextcloud-Supportbereichsvertrag: OK'
