#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo 'Aufruf: install-ad-product-bundle.sh --nextcloud-root <Pfad> --bundle-dir <Pfad> --product <App-ID|suite>' >&2
}

nextcloud_root=''
bundle_dir=''
product=''
php_bin="${PHP_BIN:-php}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --nextcloud-root) nextcloud_root="${2:-}"; shift 2 ;;
        --bundle-dir) bundle_dir="${2:-}"; shift 2 ;;
        --product) product="${2:-}"; shift 2 ;;
        *) usage; exit 2 ;;
    esac
done

catalog_file="$bundle_dir/ad-product-catalog.json"
if ! command -v "$php_bin" >/dev/null 2>&1; then
    echo "Erforderlicher Befehl fehlt: $php_bin" >&2
    exit 2
fi
if [[ ! -f "$catalog_file" ]]; then
    echo "AD-Produktkatalog fehlt im Paket: $catalog_file" >&2
    exit 2
fi
catalog_products="$($php_bin -r '
    $data = json_decode((string)file_get_contents($argv[1]), true);
    if (!is_array($data) || ($data["version"] ?? null) !== 1 || !is_array($data["entries"] ?? null)) exit(2);
    $required = ["id", "kind", "suite", "order", "route", "productLabel", "navigationLabel", "standalone", "menu", "fullSuiteBundle", "productBundle"];
    $seen = [];
    $menuOrders = [];
    $products = [];
    foreach ($data["entries"] as $entry) {
        if (!is_array($entry) || array_diff($required, array_keys($entry)) !== []) exit(3);
        $id = $entry["id"] ?? null;
        if (!is_string($id) || preg_match("/^[a-z][a-z0-9_]*$/", $id) !== 1 || isset($seen[$id])) exit(4);
        foreach (["standalone", "menu", "fullSuiteBundle", "productBundle"] as $flag) {
            if (!is_bool($entry[$flag])) exit(5);
        }
        $seen[$id] = $entry["kind"] ?? null;
        if (($entry["kind"] ?? null) === "infrastructure") {
            if ($entry["suite"] !== null || $entry["order"] !== null || $entry["route"] !== null
                || $entry["productLabel"] !== null || $entry["navigationLabel"] !== null
                || $entry["standalone"] || $entry["menu"]) exit(6);
            continue;
        }
        if (($entry["kind"] ?? null) === "product") {
            if (!is_string($entry["suite"]) || $entry["suite"] === ""
                || !is_int($entry["order"]) || $entry["order"] < 0
                || !is_string($entry["route"]) || !str_starts_with($entry["route"], $id . ".")
                || !is_string($entry["productLabel"]) || $entry["productLabel"] === ""
                || !is_string($entry["navigationLabel"]) || $entry["navigationLabel"] === "") exit(7);
            if ($entry["menu"]) {
                $orderKey = $entry["suite"] . ":" . $entry["order"];
                if (isset($menuOrders[$orderKey])) exit(8);
                $menuOrders[$orderKey] = true;
            }
            $products[] = $entry;
            continue;
        }
        exit(9);
    }
    if (($seen["localbase"] ?? null) !== "infrastructure" || ($seen["orgsuite"] ?? null) !== "infrastructure") exit(10);
    usort($products, static fn(array $left, array $right): int => [$left["order"], $left["id"]] <=> [$right["order"], $right["id"]]);
    foreach ($products as $entry) {
        echo $entry["id"], "\t", $entry["fullSuiteBundle"] ? "1" : "0", "\t", $entry["productBundle"] ? "1" : "0", "\n";
    }
' "$catalog_file")" || { echo 'AD-Produktkatalog im Paket ist ungültig.' >&2; exit 2; }

products=()
selected_products=()
while IFS=$'\t' read -r catalog_product in_full_suite has_product_bundle; do
    [[ -n "$catalog_product" ]] || continue
    products+=("$catalog_product")
    if [[ "$product" == 'suite' && "$in_full_suite" == '1' ]]; then
        selected_products+=("$catalog_product")
    elif [[ "$product" == "$catalog_product" && "$has_product_bundle" == '1' ]]; then
        selected_products=("$catalog_product")
    fi
done <<< "$catalog_products"
if [[ "${#selected_products[@]}" -eq 0 ]]; then
    echo "Unbekanntes oder nicht paketiertes AD-Produkt: $product" >&2
    exit 2
fi

if [[ ! -f "$nextcloud_root/occ" || ! -d "$nextcloud_root/custom_apps" ]]; then
    echo "Ungültige Nextcloud-Installation: $nextcloud_root" >&2
    exit 2
fi
if [[ ! -d "$bundle_dir" || ! -f "$bundle_dir/SHA256SUMS" ]]; then
    echo "Unvollständiges Produktpaket: $bundle_dir" >&2
    exit 2
fi
for command in "$php_bin" tar sha256sum find; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Erforderlicher Befehl fehlt: $command" >&2
        exit 2
    fi
done

status_json="$($php_bin "$nextcloud_root/occ" status --output=json)"
printf '%s' "$status_json" | "$php_bin" -r '
    $status = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($status) || ($status["installed"] ?? false) !== true) {
        fwrite(STDERR, "Nextcloud ist nicht vollständig installiert.\n");
        exit(2);
    }
    if (($status["maintenance"] ?? false) === true || ($status["needsDbUpgrade"] ?? false) === true) {
        fwrite(STDERR, "Nextcloud befindet sich nicht in einem sauberen Upgradezustand.\n");
        exit(3);
    }
'

(cd "$bundle_dir" && sha256sum --check SHA256SUMS)

enabled_before="$($php_bin "$nextcloud_root/occ" app:list --output=json)"
printf '%s' "$enabled_before" | "$php_bin" -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($data) || !is_array($data["enabled"] ?? null)) {
        fwrite(STDERR, "Aktivierte Nextcloud-Apps konnten nicht ermittelt werden.\n");
        exit(2);
    }
'
declare -A originally_enabled=()
while IFS= read -r app_id; do
    [[ -n "$app_id" ]] && originally_enabled["$app_id"]=1
done < <(printf '%s' "$enabled_before" | "$php_bin" -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($data) || !is_array($data["enabled"] ?? null)) exit(2);
    foreach (array_keys($data["enabled"]) as $appId) echo $appId, "\n";
')

stage="$(mktemp -d)"
backup_root="$nextcloud_root/.ad-product-backup-$(date +%Y%m%d%H%M%S)-$$"
declare -a replaced=()
declare -a created=()
declare -a enabled_by_installer=()
database_upgrade_started=0
finished=0

rollback() {
    local exit_code=$?
    if [[ "$finished" -ne 1 ]]; then
        if [[ "$database_upgrade_started" -eq 1 ]]; then
            echo 'Das Nextcloud-Upgrade ist fehlgeschlagen. Appcode wird wegen möglicher Datenbankänderungen nicht automatisch zurückgesetzt; vollständiges Backup wiederherstellen.' >&2
        else
            for ((index=${#enabled_by_installer[@]}-1; index>=0; index--)); do
                "$php_bin" "$nextcloud_root/occ" app:disable "${enabled_by_installer[$index]}" >/dev/null 2>&1 || true
            done
            for app in "${created[@]:-}"; do
                [[ -n "$app" ]] && rm -rf "$nextcloud_root/custom_apps/$app"
            done
            for app in "${replaced[@]:-}"; do
                [[ -n "$app" && -d "$backup_root/$app" ]] || continue
                rm -rf "$nextcloud_root/custom_apps/$app"
                mv "$backup_root/$app" "$nextcloud_root/custom_apps/$app"
            done
        fi
    fi
    rm -rf "$stage" "$backup_root"
    exit "$exit_code"
}
trap rollback EXIT

archive_for() {
    local app="$1"
    local matches=()
    shopt -s nullglob
    matches=("$bundle_dir/$app"-*.tar.gz)
    shopt -u nullglob
    if [[ "${#matches[@]}" -ne 1 ]]; then
        echo "Erwartet wird genau ein Archiv für $app, gefunden: ${#matches[@]}" >&2
        return 1
    fi
    printf '%s\n' "${matches[0]}"
}

install_archive() {
    local app="$1"
    local archive
    archive="$(archive_for "$app")"
    local members roots member
    members="$(tar -tzf "$archive")"
    while IFS= read -r member; do
        if [[ "$member" == /* || "$member" =~ (^|/)\.\.(/|$) ]]; then
            echo "Unsicherer Archivpfad in $app: $member" >&2
            return 1
        fi
    done <<< "$members"
    if tar -tvzf "$archive" | grep -Eq '^[^d-]'; then
        echo "Nicht unterstützter Archiveintrag in $app" >&2
        return 1
    fi
    roots="$(printf '%s\n' "$members" | cut -d/ -f1 | sort -u)"
    if [[ "$roots" != "$app" ]]; then
        echo "Archiv besitzt keinen eindeutigen Wurzelordner $app: $archive" >&2
        return 1
    fi

    tar -C "$stage" -xzf "$archive"
    if find "$stage/$app" -type l -print -quit | grep -q .; then
        echo "Symlink im App-Archiv gefunden: $app" >&2
        return 1
    fi
    local package_version
    package_version="$($php_bin -r '
        $xml = simplexml_load_file($argv[1]);
        if ($xml === false || (string)$xml->id !== $argv[2]) exit(2);
        echo (string)$xml->version;
    ' "$stage/$app/appinfo/info.xml" "$app")"

    local target="$nextcloud_root/custom_apps/$app"
    if [[ -f "$target/appinfo/info.xml" ]]; then
        local installed_version comparison
        installed_version="$($php_bin -r '$xml=simplexml_load_file($argv[1]); if ($xml===false) exit(2); echo (string)$xml->version;' "$target/appinfo/info.xml")"
        comparison="$($php_bin -r 'echo version_compare($argv[1], $argv[2]);' "$package_version" "$installed_version")"
        if [[ "$comparison" -le 0 ]]; then
            echo "$app $installed_version bleibt installiert; Paketversion $package_version ist nicht neuer."
            rm -rf "$stage/$app"
            return 0
        fi
        mkdir -p "$backup_root"
        mv "$target" "$backup_root/$app"
        replaced+=("$app")
    else
        created+=("$app")
    fi
    mv "$stage/$app" "$target"
    echo "$app $package_version wurde bereitgestellt."
}

install_archive localbase
install_archive orgsuite
for selected_product in "${selected_products[@]}"; do
    install_archive "$selected_product"
done

"$php_bin" "$nextcloud_root/occ" app:enable localbase
[[ -n "${originally_enabled[localbase]:-}" ]] || enabled_by_installer+=(localbase)
for selected_product in "${selected_products[@]}"; do
    "$php_bin" "$nextcloud_root/occ" app:enable "$selected_product"
    [[ -n "${originally_enabled[$selected_product]:-}" ]] || enabled_by_installer+=("$selected_product")
done

enabled_json="$($php_bin "$nextcloud_root/occ" app:list --output=json)"
product_count="$(printf '%s' "$enabled_json" | "$php_bin" -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($data) || !is_array($data["enabled"] ?? null)) exit(2);
    $catalog = json_decode((string)file_get_contents($argv[1]), true);
    if (!is_array($catalog) || !is_array($catalog["entries"] ?? null)) exit(2);
    $products = [];
    foreach ($catalog["entries"] as $entry) {
        if (($entry["kind"] ?? null) === "product") $products[] = $entry["id"];
    }
    echo count(array_intersect($products, array_keys($data["enabled"]))) ;
' "$catalog_file")"

if [[ "$product_count" -ge 2 ]]; then
    "$php_bin" "$nextcloud_root/occ" app:enable orgsuite
    [[ -n "${originally_enabled[orgsuite]:-}" ]] || enabled_by_installer+=(orgsuite)
    echo "OrgSuite wurde für $product_count aktive AD-Produkte aktiviert."
else
    echo 'OrgSuite bleibt bei einer einzelnen AD-Fachapp deaktiviert.'
fi

database_upgrade_started=1
"$php_bin" "$nextcloud_root/occ" upgrade
database_upgrade_started=0

finished=1
echo "AD-Produktinstallation abgeschlossen: $product"
