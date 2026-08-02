#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo 'Aufruf: install-staging-app.sh --nextcloud-root <Pfad> --php-bin <Pfad> [--php-memory-limit <Wert>] --archive <Pfad> --sha256 <Hash> --app-id <ID> --commit <SHA> [--state-dir <Pfad>]' >&2
}

nextcloud_root=''
php_bin=''
php_memory_limit=''
archive=''
expected_hash=''
app_id=''
commit=''
state_dir="${XDG_STATE_HOME:-$HOME/.local/state}/br-nextcloud-staging"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --nextcloud-root) nextcloud_root="${2:-}"; shift 2 ;;
        --php-bin) php_bin="${2:-}"; shift 2 ;;
        --php-memory-limit) php_memory_limit="${2:-}"; shift 2 ;;
        --archive) archive="${2:-}"; shift 2 ;;
        --sha256) expected_hash="${2:-}"; shift 2 ;;
        --app-id) app_id="${2:-}"; shift 2 ;;
        --commit) commit="${2:-}"; shift 2 ;;
        --state-dir) state_dir="${2:-}"; shift 2 ;;
        *) usage; exit 2 ;;
    esac
done

if [[ -z "$nextcloud_root" || -z "$php_bin" || -z "$archive" || -z "$expected_hash" || -z "$app_id" || -z "$commit" || -z "$state_dir" ]]; then
    usage
    exit 2
fi
if [[ ! "$app_id" =~ ^[a-z][a-z0-9_]*$ ]]; then
    echo "Ungültige App-ID: $app_id" >&2
    exit 2
fi
if [[ ! "$expected_hash" =~ ^[0-9a-f]{64}$ || ! "$commit" =~ ^[0-9a-f]{40}([0-9a-f]{24})?$ ]]; then
    echo 'Ungültige Prüfsumme oder Commit-ID.' >&2
    exit 2
fi
if [[ ! -f "$archive" || ! -f "$nextcloud_root/occ" || ! -d "$nextcloud_root/custom_apps" ]]; then
    echo 'Archiv oder Nextcloud-Installation ist unvollständig.' >&2
    exit 2
fi
if [[ ! -x "$php_bin" ]]; then
    echo "PHP-Binary ist nicht ausführbar: $php_bin" >&2
    exit 2
fi
if [[ -n "$php_memory_limit" && ! "$php_memory_limit" =~ ^[1-9][0-9]*[MG]$ ]]; then
    echo "Ungültiges PHP-Memory-Limit: $php_memory_limit" >&2
    exit 2
fi
php_command=("$php_bin")
if [[ -n "$php_memory_limit" ]]; then
    php_command+=(-d "memory_limit=$php_memory_limit")
fi
for command in tar sha256sum find flock cp mv rm mkdir date; do
    command -v "$command" >/dev/null 2>&1 || { echo "Erforderlicher Befehl fehlt: $command" >&2; exit 2; }
done

actual_hash="$(sha256sum "$archive" | cut -d' ' -f1)"
if [[ "$actual_hash" != "$expected_hash" ]]; then
    echo 'SHA-256-Prüfsumme des App-Archivs stimmt nicht.' >&2
    exit 3
fi

members="$(tar -tzf "$archive")"
while IFS= read -r member; do
    if [[ "$member" == /* || "$member" =~ (^|/)\.\.(/|$) ]]; then
        echo "Unsicherer Archivpfad: $member" >&2
        exit 3
    fi
done <<< "$members"
roots="$(printf '%s\n' "$members" | cut -d/ -f1 | sort -u)"
if [[ "$roots" != "$app_id" ]]; then
    echo "Archiv besitzt nicht genau den Wurzelordner $app_id." >&2
    exit 3
fi
if tar -tvzf "$archive" | awk 'substr($1,1,1) != "d" && substr($1,1,1) != "-" { found=1 } END { exit found ? 0 : 1 }'; then
    echo 'Archiv enthält einen nicht unterstützten Eintrag wie einen Symlink.' >&2
    exit 3
fi

mkdir -p "$state_dir/tmp" "$state_dir/backups/$app_id" "$state_dir/deployed"
exec 9>"$state_dir/deploy.lock"
if ! flock -w 600 9; then
    echo 'Ein anderes Staging-Deployment hat die Wartezeit von zehn Minuten überschritten.' >&2
    exit 4
fi

status_json="$("${php_command[@]}" "$nextcloud_root/occ" status --output=json)"
printf '%s' "$status_json" | "${php_command[@]}" -r '
    $status = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($status) || ($status["installed"] ?? false) !== true
        || ($status["maintenance"] ?? true) === true
        || ($status["needsDbUpgrade"] ?? true) === true) exit(1);
' || { echo 'Nextcloud ist vor dem Deployment nicht in einem sauberen Zustand.' >&2; exit 4; }

enabled_json="$("${php_command[@]}" "$nextcloud_root/occ" app:list --output=json)"
was_enabled="$(printf '%s' "$enabled_json" | "${php_command[@]}" -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($data) || !is_array($data["enabled"] ?? null)) exit(2);
    echo array_key_exists($argv[1], $data["enabled"]) ? "1" : "0";
' "$app_id")"

extract_dir="$(mktemp -d "$state_dir/tmp/deploy.XXXXXX")"
candidate="$nextcloud_root/custom_apps/.deploy-$app_id-$$"
displaced="$nextcloud_root/custom_apps/.previous-$app_id-$$"
target="$nextcloud_root/custom_apps/$app_id"
backup=''
code_replaced=0
database_upgrade_started=0
finished=0

cleanup() {
    local exit_code=$?
    if [[ "$finished" -ne 1 && "$code_replaced" -eq 1 && "$database_upgrade_started" -eq 0 ]]; then
        rm -rf "$target"
        if [[ -d "$displaced" ]]; then
            mv "$displaced" "$target"
        fi
        echo "$app_id wurde nach einem Fehler vor dem Datenbank-Upgrade auf den vorherigen Code zurückgesetzt." >&2
    elif [[ "$finished" -ne 1 && "$database_upgrade_started" -eq 1 ]]; then
        echo 'Das Nextcloud-App-Upgrade wurde begonnen. Der Code wird wegen möglicher Datenbankänderungen nicht automatisch zurückgesetzt.' >&2
    fi
    rm -rf "$candidate" "$extract_dir"
    if [[ -d "$displaced" ]]; then
        rm -rf "$displaced"
    fi
    exit "$exit_code"
}
trap cleanup EXIT

tar -C "$extract_dir" -xzf "$archive"
if find "$extract_dir/$app_id" -type l -print -quit | grep -q .; then
    echo 'Entpackte App enthält einen Symlink.' >&2
    exit 3
fi
mapfile -t package_info < <("${php_command[@]}" -r '
    $xml = simplexml_load_file($argv[1]);
    if ($xml === false) exit(2);
    echo (string)$xml->id, "\n", (string)$xml->version, "\n";
' "$extract_dir/$app_id/appinfo/info.xml")
if [[ "${package_info[0]:-}" != "$app_id" || -z "${package_info[1]:-}" ]]; then
    echo 'App-ID oder Version im Paket ist ungültig.' >&2
    exit 3
fi
version="${package_info[1]}"

cp -a "$extract_dir/$app_id" "$candidate"
if [[ -d "$target" ]]; then
    backup="$state_dir/backups/$app_id/$(date -u +%Y%m%dT%H%M%SZ)-$$-$commit"
    cp -a "$target" "$backup"
    mv "$target" "$displaced"
fi
mv "$candidate" "$target"
code_replaced=1

status_json="$("${php_command[@]}" "$nextcloud_root/occ" status --output=json)"
printf '%s' "$status_json" | "${php_command[@]}" -r '
    $status = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($status) || ($status["installed"] ?? false) !== true
        || ($status["maintenance"] ?? true) === true) exit(1);
' || { echo 'Nextcloud-Status ist nach dem Codetausch ungültig.' >&2; exit 4; }

database_upgrade_started=1
if [[ "$was_enabled" == '0' ]]; then
    "${php_command[@]}" "$nextcloud_root/occ" app:enable "$app_id"
fi
"${php_command[@]}" "$nextcloud_root/occ" upgrade

status_json="$("${php_command[@]}" "$nextcloud_root/occ" status --output=json)"
printf '%s' "$status_json" | "${php_command[@]}" -r '
    $status = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($status) || ($status["installed"] ?? false) !== true
        || ($status["maintenance"] ?? true) === true
        || ($status["needsDbUpgrade"] ?? true) === true) exit(1);
' || { echo 'Nextcloud ist nach dem Upgrade nicht in einem sauberen Zustand.' >&2; exit 5; }
enabled_json="$("${php_command[@]}" "$nextcloud_root/occ" app:list --output=json)"
printf '%s' "$enabled_json" | "${php_command[@]}" -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    if (!is_array($data) || !array_key_exists($argv[1], $data["enabled"] ?? [])) exit(1);
' "$app_id" || { echo "$app_id ist nach dem Deployment nicht aktiviert." >&2; exit 5; }

record_tmp="$state_dir/deployed/$app_id.tsv.tmp.$$"
printf '%s\t%s\t%s\t%s\n' "$app_id" "$version" "$commit" "$actual_hash" > "$record_tmp"
mv "$record_tmp" "$state_dir/deployed/$app_id.tsv"
rm -rf "$displaced"
finished=1

echo "$app_id $version ($commit) wurde erfolgreich auf Staging installiert."
if [[ -n "$backup" ]]; then
    echo "Code-Backup: $backup"
fi
