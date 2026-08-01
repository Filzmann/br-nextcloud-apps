#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
installer="$workspace/scripts/install-ad-product-bundle.sh"
catalog="$workspace/localbase/resources/ad-product-catalog.json"
catalog_reader="$workspace/scripts/read-ad-product-catalog.php"
php "$catalog_reader" validate >/dev/null
mapfile -t products < <(php "$catalog_reader" products)
mapfile -t full_suite_apps < <(php "$catalog_reader" full-suite)
temporary="$(mktemp -d)"

cleanup() {
    rm -rf "$temporary"
}
trap cleanup EXIT

cloud="$temporary/nextcloud"
mkdir -p "$cloud/custom_apps"

cat > "$cloud/occ" <<'PHP'
<?php
$stateFile = __DIR__ . '/enabled.json';
$enabled = is_file($stateFile) ? json_decode((string)file_get_contents($stateFile), true) : [];
$command = $argv[1] ?? '';
file_put_contents(__DIR__ . '/occ.log', implode(' ', array_slice($argv, 1)) . "\n", FILE_APPEND);
if ($command === 'status') {
    echo json_encode(['installed' => true, 'maintenance' => false, 'needsDbUpgrade' => false]);
    exit(0);
}
if ($command === 'app:enable') {
    $appId = $argv[2] ?? '';
    if (getenv('FAIL_ENABLE_APP') === $appId) exit(9);
    $info = simplexml_load_file(__DIR__ . '/custom_apps/' . $appId . '/appinfo/info.xml');
    if ($appId === '' || $info === false) exit(2);
    $enabled[$appId] = (string)$info->version;
    file_put_contents($stateFile, json_encode($enabled));
    echo $appId . " enabled\n";
    exit(0);
}
if ($command === 'app:disable') {
    unset($enabled[$argv[2] ?? '']);
    file_put_contents($stateFile, json_encode($enabled));
    exit(0);
}
if ($command === 'app:list') {
    echo json_encode(['enabled' => $enabled, 'disabled' => []]);
    exit(0);
}
if ($command === 'upgrade') exit(0);
exit(3);
PHP

make_bundle() {
    local directory="$1"
    local product="$2"
    mkdir -p "$directory/source" "$directory/bundle"
    mapfile -t bundle_apps < <(php "$catalog_reader" product-bundle "$product")
    for app in "${bundle_apps[@]}"; do
        mkdir -p "$directory/source/$app/appinfo"
        cat > "$directory/source/$app/appinfo/info.xml" <<XML
<?xml version="1.0"?>
<info><id>$app</id><name>$app</name><summary>$app</summary><description>$app</description><version>1.0.0</version><licence>agpl</licence><author>Test</author><category>organization</category><bugs>https://example.test</bugs><dependencies><nextcloud min-version="34" max-version="34"/></dependencies></info>
XML
        tar -C "$directory/source" -czf "$directory/bundle/$app-1.0.0.tar.gz" "$app"
    done
    cp "$catalog" "$directory/bundle/ad-product-catalog.json"
    (cd "$directory/bundle" && sha256sum ./*.tar.gz ad-product-catalog.json > SHA256SUMS)
}

make_suite_bundle() {
    local directory="$1"
    mkdir -p "$directory/source" "$directory/bundle"
    for app in "${full_suite_apps[@]}"; do
        mkdir -p "$directory/source/$app/appinfo"
        cat > "$directory/source/$app/appinfo/info.xml" <<XML
<?xml version="1.0"?>
<info><id>$app</id><name>$app</name><summary>$app</summary><description>$app</description><version>1.0.0</version><licence>agpl</licence><author>Test</author><category>organization</category><bugs>https://example.test</bugs><dependencies><nextcloud min-version="34" max-version="34"/></dependencies></info>
XML
        tar -C "$directory/source" -czf "$directory/bundle/$app-1.0.0.tar.gz" "$app"
    done
    cp "$catalog" "$directory/bundle/ad-product-catalog.json"
    (cd "$directory/bundle" && sha256sum ./*.tar.gz ad-product-catalog.json > SHA256SUMS)
}

make_cloud() {
    local target="$1"
    mkdir -p "$target/custom_apps"
    cp "$cloud/occ" "$target/occ"
}

make_bundle "$temporary/calendar" adcalendar
"$installer" --nextcloud-root "$cloud" --bundle-dir "$temporary/calendar/bundle" --product adcalendar

for app in localbase orgsuite adcalendar; do
    [[ -f "$cloud/custom_apps/$app/appinfo/info.xml" ]] || { echo "Installierte App fehlt: $app" >&2; exit 1; }
done
php -r '$s=json_decode(file_get_contents($argv[1]),true); if (!isset($s["localbase"],$s["adcalendar"]) || isset($s["orgsuite"])) exit(1);' "$cloud/enabled.json"

make_bundle "$temporary/planer" adplaner
"$installer" --nextcloud-root "$cloud" --bundle-dir "$temporary/planer/bundle" --product adplaner
php -r '$s=json_decode(file_get_contents($argv[1]),true); foreach (["localbase","adcalendar","adplaner","orgsuite"] as $app) if (!isset($s[$app])) exit(1);' "$cloud/enabled.json"
if [[ "$(grep -c '^upgrade$' "$cloud/occ.log")" -ne 2 ]]; then
    echo 'Nextcloud-App-Upgrades wurden nicht nach jedem Produktlauf ausgeführt.' >&2
    exit 1
fi

for ((left=0; left<${#products[@]}; left++)); do
    for ((right=left+1; right<${#products[@]}; right++)); do
        first="${products[$left]}"
        second="${products[$right]}"
        pair_cloud="$temporary/pair-$first-$second-cloud"
        make_cloud "$pair_cloud"
        make_bundle "$temporary/pair-$first-$second-first" "$first"
        make_bundle "$temporary/pair-$first-$second-second" "$second"
        "$installer" --nextcloud-root "$pair_cloud" --bundle-dir "$temporary/pair-$first-$second-first/bundle" --product "$first" >/dev/null
        "$installer" --nextcloud-root "$pair_cloud" --bundle-dir "$temporary/pair-$first-$second-second/bundle" --product "$second" >/dev/null
        php -r '$s=json_decode(file_get_contents($argv[1]),true); foreach (["localbase","orgsuite",$argv[2],$argv[3]] as $app) if (!isset($s[$app])) exit(1);' "$pair_cloud/enabled.json" "$first" "$second"
    done
done

suite_cloud="$temporary/suite-cloud"
make_cloud "$suite_cloud"
make_suite_bundle "$temporary/suite"
"$installer" --nextcloud-root "$suite_cloud" --bundle-dir "$temporary/suite/bundle" --product suite >/dev/null
for app in "${full_suite_apps[@]}"; do
    php -r '$s=json_decode(file_get_contents($argv[1]),true); if (!isset($s[$argv[2]])) exit(1);' "$suite_cloud/enabled.json" "$app"
done

malicious_cloud="$temporary/malicious-cloud"
make_cloud "$malicious_cloud"
cp -R "$temporary/suite/bundle" "$temporary/malicious-bundle"
php -r '
    $path = $argv[1];
    $catalog = json_decode((string)file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $catalog["entries"][] = [
        "id" => "../escape", "kind" => "product", "suite" => "ad", "order" => 999,
        "route" => "../escape.page.index", "productLabel" => "Escape", "navigationLabel" => "Escape",
        "standalone" => true, "menu" => true, "fullSuiteBundle" => true, "productBundle" => true,
    ];
    file_put_contents($path, json_encode($catalog, JSON_THROW_ON_ERROR));
' "$temporary/malicious-bundle/ad-product-catalog.json"
(cd "$temporary/malicious-bundle" && sha256sum ./*.tar.gz ad-product-catalog.json > SHA256SUMS)
if "$installer" --nextcloud-root "$malicious_cloud" --bundle-dir "$temporary/malicious-bundle" --product suite >"$temporary/malicious.out" 2>"$temporary/malicious.err"; then
    echo 'Unsichere Katalog-ID wurde vom Installer akzeptiert.' >&2
    exit 1
fi
if ! grep -Fq 'AD-Produktkatalog im Paket ist ungültig.' "$temporary/malicious.err"; then
    echo 'Unsichere Katalog-ID wurde nicht als Katalogfehler abgelehnt.' >&2
    exit 1
fi

recruitment_cloud="$temporary/recruitment-cloud"
make_cloud "$recruitment_cloud"
make_bundle "$temporary/recruitment" adrecruitment
"$installer" --nextcloud-root "$recruitment_cloud" --bundle-dir "$temporary/recruitment/bundle" --product adrecruitment >/dev/null
php -r '$s=json_decode(file_get_contents($argv[1]),true); foreach (["localbase","adrecruitment"] as $app) if (!isset($s[$app])) exit(1); if (isset($s["orgsuite"])) exit(2);' "$recruitment_cloud/enabled.json"

cp -R "$temporary/calendar/bundle" "$temporary/broken"
printf '0%.0s' {1..64} > "$temporary/broken/SHA256SUMS"
if "$installer" --nextcloud-root "$cloud" --bundle-dir "$temporary/broken" --product adcalendar >/dev/null 2>&1; then
    echo 'Ungültige Prüfsumme wurde akzeptiert.' >&2
    exit 1
fi

failure_cloud="$temporary/failure-cloud"
mkdir -p "$failure_cloud/custom_apps"
cp "$cloud/occ" "$failure_cloud/occ"
make_bundle "$temporary/room" adroom
if FAIL_ENABLE_APP=adroom "$installer" --nextcloud-root "$failure_cloud" --bundle-dir "$temporary/room/bundle" --product adroom >/dev/null 2>&1; then
    echo 'Absichtlich fehlgeschlagene Produktaktivierung wurde akzeptiert.' >&2
    exit 1
fi
if [[ -f "$failure_cloud/enabled.json" ]] && php -r '$s=json_decode(file_get_contents($argv[1]),true); exit($s === [] ? 1 : 0);' "$failure_cloud/enabled.json"; then
    echo 'Neu aktivierte Infrastruktur blieb nach einem fehlgeschlagenen Lauf aktiv.' >&2
    exit 1
fi

if ! grep -Fq '(^|/)\.\.(/|$)' "$installer"; then
    echo 'Der Installer prüft Archivpfade nicht explizit auf Traversal.' >&2
    exit 1
fi

echo 'AD-Produktinstaller-Test: OK'
