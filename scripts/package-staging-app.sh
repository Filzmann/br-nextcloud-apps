#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo 'Aufruf: package-staging-app.sh --app-root <Pfad> --app-id <ID> --commit <SHA> --archive <Pfad> --metadata <Pfad>' >&2
}

app_root=''
app_id=''
commit=''
archive=''
metadata=''

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-root) app_root="${2:-}"; shift 2 ;;
        --app-id) app_id="${2:-}"; shift 2 ;;
        --commit) commit="${2:-}"; shift 2 ;;
        --archive) archive="${2:-}"; shift 2 ;;
        --metadata) metadata="${2:-}"; shift 2 ;;
        *) usage; exit 2 ;;
    esac
done

if [[ -z "$app_root" || -z "$app_id" || -z "$commit" || -z "$archive" || -z "$metadata" ]]; then
    usage
    exit 2
fi
if [[ ! "$app_id" =~ ^[a-z][a-z0-9_]*$ ]]; then
    echo "Ungültige App-ID: $app_id" >&2
    exit 2
fi
if [[ ! "$commit" =~ ^[0-9a-f]{40}([0-9a-f]{24})?$ ]]; then
    echo 'Commit muss eine vollständige Git-SHA sein.' >&2
    exit 2
fi
if [[ ! -f "$app_root/appinfo/info.xml" ]]; then
    echo "appinfo/info.xml fehlt: $app_root" >&2
    exit 2
fi

for command in php tar sha256sum find sort; do
    command -v "$command" >/dev/null 2>&1 || { echo "Erforderlicher Befehl fehlt: $command" >&2; exit 2; }
done

mapfile -t info < <(php -r '
    $xml = simplexml_load_file($argv[1]);
    if ($xml === false) exit(2);
    echo (string)$xml->id, "\n", (string)$xml->version, "\n";
' "$app_root/appinfo/info.xml")
if [[ "${info[0]:-}" != "$app_id" || -z "${info[1]:-}" ]]; then
    echo "App-Metadaten passen nicht zu $app_id." >&2
    exit 2
fi
version="${info[1]}"
if [[ ! "$version" =~ ^[0-9A-Za-z][0-9A-Za-z._+-]*$ ]]; then
    echo "Nicht unterstützte App-Version: $version" >&2
    exit 2
fi
css_asset="$(find "$app_root/css" -type f -name '*.css' -printf '%P\n' 2>/dev/null | LC_ALL=C sort | head -n 1)"
js_asset="$(find "$app_root/js" -type f -name '*.js' -printf '%P\n' 2>/dev/null | LC_ALL=C sort | head -n 1)"
if [[ -z "$css_asset" || -z "$js_asset" ]]; then
    echo "$app_id benötigt mindestens ein CSS- und ein JavaScript-Asset." >&2
    exit 2
fi
css_asset="css/$css_asset"
js_asset="js/$js_asset"
for asset in "$css_asset" "$js_asset"; do
    if [[ ! "$asset" =~ ^[A-Za-z0-9._/-]+$ ]]; then
        echo "Nicht unterstützter Assetpfad: $asset" >&2
        exit 2
    fi
done

stage="$(mktemp -d)"
cleanup() {
    rm -rf "$stage"
}
trap cleanup EXIT

mkdir -p "$stage/$app_id" "$(dirname "$archive")" "$(dirname "$metadata")"
work_tar="$stage/source.tar"
tar -C "$app_root" \
    --exclude='./.git' \
    --exclude='./.gitignore' \
    --exclude='./.github' \
    --exclude='./.agents' \
    --exclude='./.codex' \
    --exclude='./AGENTS.md' \
    --exclude='./tests' \
    --exclude='./node_modules' \
    --exclude='./vendor' \
    -cf "$work_tar" .
tar -C "$stage/$app_id" -xf "$work_tar"
rm -f "$work_tar"
if find "$stage/$app_id" -type l -print -quit | grep -q .; then
    echo "Symlink im Releaseinhalt gefunden: $app_id" >&2
    exit 2
fi

tar -C "$stage" -czf "$archive" "$app_id"
mapfile -t roots < <(tar -tzf "$archive" | cut -d/ -f1 | sort -u)
if [[ "${#roots[@]}" -ne 1 || "${roots[0]}" != "$app_id" ]]; then
    echo 'Erzeugtes Archiv verletzt den Ein-Wurzel-Vertrag.' >&2
    exit 2
fi

hash="$(sha256sum "$archive" | cut -d' ' -f1)"
printf 'APP_ID=%s\nAPP_VERSION=%s\nGIT_COMMIT=%s\nSHA256=%s\nCSS_ASSET=%s\nJS_ASSET=%s\n' \
    "$app_id" "$version" "$commit" "$hash" "$css_asset" "$js_asset" > "$metadata"

echo "Staging-Archiv erstellt: $archive"
