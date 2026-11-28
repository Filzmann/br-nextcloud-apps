#!/usr/bin/env bash
# Baut ein offizielles App-Store-Kandidatenarchiv (tar.gz) für genau eine App.
#
# Maschinenprüfbare Eigenschaften gemäß ADR 0001 (Store- und Releasevertrag):
#   - genau eine Archivwurzel mit appinfo/info.xml (App-ID = Wurzelname)
#   - keine Entwicklungs-/Test-/Secret-Dateien, keine Symlinks
#   - Lizenz (AGPL) und l10n-Kataloge vorhanden
#   - keine LaufzeitReferenz auf private Workspace-Apps (LocalBase) im Produktivcode
#   - reproduzierbares tar.gz aus definiertem Commit + sha256 + Evidence
#
# Signierung und Veröffentlichung bleiben getrennte, menschlich freizugebende
# Delivery-Ereignisse (privater Schlüssel ist nicht Teil dieses Workflows).
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"

app=""
label=""
commit=""
while (( $# > 0 )); do
    case "$1" in
        --app) app="${2:?--app braucht einen Wert}"; shift 2 ;;
        --label) label="${2:?--label braucht einen Wert}"; shift 2 ;;
        --commit) commit="${2:?--commit braucht einen Wert}"; shift 2 ;;
        *) echo "Unbekannte Option: $1" >&2; exit 2 ;;
    esac
done

if [[ -z "$app" || -z "$label" ]]; then
    echo 'Verwendung: build-app-store-candidate.sh --app <app-id> --label <label> [--commit <sha>]' >&2
    exit 2
fi
if [[ ! "$label" =~ ^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$ ]]; then
    echo 'Ungültiges Label.' >&2
    exit 2
fi

app_root="$workspace/$app"
if [[ ! -d "$app_root/.git" ]]; then
    echo "App-Verzeichnis ist kein Git-Repository: $app_root" >&2
    exit 1
fi
if [[ -z "$commit" ]]; then
    commit="$(git -C "$app_root" rev-parse HEAD)"
fi
if ! git -C "$app_root" rev-parse --verify --quiet "$commit^{commit}" >/dev/null; then
    echo "Commit nicht auflösbar: $commit" >&2
    exit 1
fi
if [[ -n "$(git -C "$app_root" status --porcelain)" ]]; then
    echo "Arbeitsbaum von $app ist nicht sauber; bitte committen oder --commit eines sauberen Stands wählen." >&2
    exit 1
fi

app_id="$(php -r '$x = simplexml_load_file($argv[1]); if ($x === false || !isset($x->id)) { fwrite(STDERR, "info.xml ohne id\n"); exit(1); } echo (string)$x->id;' "$app_root/appinfo/info.xml")"
if [[ "$app_id" != "$app" ]]; then
    echo "App-ID ($app_id) weicht vom Verzeichnisnamen ($app) ab." >&2
    exit 1
fi

dist_root="${DIST_ROOT:-$workspace/dist}"
mkdir -p "$dist_root"
stage="$(mktemp -d "$dist_root/.store-stage-$app-XXXXXX")"
trap 'rm -rf "$stage"' EXIT

# Sauberer Export aus dem definierten Commit; Entwicklungsdateien bleiben außen.
mkdir -p "$stage/$app"
git -C "$app_root" archive --format=tar "$commit" | tar -x -C "$stage/$app"
for forbidden in tests .github node_modules dist build .git .DS_Store .agents .vibe .codex; do
    rm -rf "$stage/$app/$forbidden"
done
find "$stage/$app" -name '*.log' -delete

# --- Maschinenprüfungen ---
fail=0
check() { # check <beschreibung> <kommando...>
    local description="$1"; shift
    if "$@" >/dev/null 2>&1; then
        echo "OK   $description"
    else
        echo "FAIL $description" >&2
        fail=1
    fi
}

check "genau eine Archivwurzel mit appinfo/info.xml" test -f "$stage/$app/appinfo/info.xml"
check "Lizenzdatei vorhanden" test -f "$stage/$app/LICENSE"
check "Lizenz ist AGPL" grep -q "GNU AFFERO GENERAL PUBLIC LICENSE" "$stage/$app/LICENSE"
check "l10n-Kataloge en.json/en.js vorhanden" test -f "$stage/$app/l10n/en.json" -a -f "$stage/$app/l10n/en.js"

licence="$(php -r '$x = simplexml_load_file($argv[1]); echo (string)($x->licence ?? "");' "$stage/$app/appinfo/info.xml")"
if [[ "$licence" != "agpl" ]]; then
    echo "FAIL info.xml-Lizenz ist nicht agpl: $licence" >&2
    fail=1
else
    echo "OK   info.xml-Lizenz agpl"
fi
for field in name summary description version; do
    value="$(php -r '$x = simplexml_load_file($argv[1]); echo trim((string)($x->{$argv[2]} ?? ""));' "$stage/$app/appinfo/info.xml" "$field")"
    if [[ -z "$value" ]]; then
        echo "FAIL info.xml-Feld fehlt: $field" >&2
        fail=1
    fi
done
if [[ $fail -eq 0 ]]; then echo "OK   info.xml-Pflichtfelder vollständig"; fi

if find "$stage/$app" -type l | grep -q .; then
    echo "FAIL Archiv enthält Symlinks" >&2
    fail=1
else
    echo "OK   keine Symlinks im Archiv"
fi

if grep -rIl --exclude-dir=vendor -e 'OCA\\LocalBase' -e "addScript('localbase'" -e "addStyle('localbase'" -e 'window\.LocalBase' "$stage/$app/lib" "$stage/$app/appinfo" "$stage/$app/templates" "$stage/$app/js" >/dev/null 2>&1; then
    echo "FAIL Produktivcode referenziert weiterhin LocalBase:" >&2
    grep -rIl --exclude-dir=vendor -e 'OCA\\LocalBase' -e "addScript('localbase'" -e "addStyle('localbase'" -e 'window\.LocalBase' "$stage/$app/lib" "$stage/$app/appinfo" "$stage/$app/templates" "$stage/$app/js" >&2
    fail=1
else
    echo "OK   kein LocalBase-Laufzeitbezug im Produktivcode"
fi

while IFS= read -r php_file; do
    if ! php -l "$php_file" >/dev/null 2>&1; then
        echo "FAIL PHP-Lint: $php_file" >&2
        fail=1
    fi
done < <(find "$stage/$app" -name '*.php' -type f)
if [[ $fail -eq 0 ]]; then echo "OK   PHP-Lint aller Produktivdateien"; fi

if (( fail != 0 )); then
    echo "Store-Kandidaten-Gate rot; kein Archiv erzeugt." >&2
    exit 1
fi

# --- Reproduzierbares Archiv + Evidence ---
source_epoch="$(git -C "$app_root" log -1 --format=%ct "$commit")"
bundle="$dist_root/$app-store-$label.tar.gz"
SOURCE_DATE_EPOCH="$source_epoch" "$workspace/scripts/create-reproducible-tar-gz.sh" "$bundle" "$stage" "$app"
sha="$(sha256sum "$bundle" | cut -d' ' -f1)"
file_count="$(tar -tzf "$bundle" | wc -l)"
evidence="$dist_root/$app-store-$label.evidence.json"
php -r '
$evidence = [
    "schema" => 1,
    "kind" => "app-store-candidate",
    "app" => $argv[1],
    "label" => $argv[2],
    "commit" => $argv[3],
    "sourceDateEpoch" => (int)$argv[4],
    "artifact" => basename($argv[5]),
    "sha256" => $argv[6],
    "fileCount" => (int)$argv[7],
    "gate" => "passed",
    "signing" => "separates menschlich freizugebendes Delivery-Ereignis (kein Schlüssel im Workflow)",
];
file_put_contents($argv[8], json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
' "$app" "$label" "$commit" "$source_epoch" "$bundle" "$sha" "$file_count" "$evidence"
sha256sum "$evidence" | cut -d' ' -f1 > "$evidence.sha256"

echo "Store-Kandidat: $bundle"
echo "SHA-256:         $sha"
echo "Evidence:        $evidence"
