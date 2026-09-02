#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo 'Aufruf: prune-ad-suite-release-candidates.sh --dist-root <Pfad> --keep-label nc<major>-rcN [--execute]' >&2
}

dist_root=''
keep_label=''
execute=0
workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog_reader="$workspace/scripts/read-ad-product-catalog.php"
php "$catalog_reader" validate >/dev/null
mapfile -t catalog_products < <(php "$catalog_reader" bundle-products)
declare -A allowed_products=()
for catalog_product in "${catalog_products[@]}"; do
    allowed_products["$catalog_product"]=1
done
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dist-root) dist_root="${2:-}"; shift 2 ;;
        --keep-label) keep_label="${2:-}"; shift 2 ;;
        --execute) execute=1; shift ;;
        *) usage; exit 2 ;;
    esac
done

if [[ ! "$keep_label" =~ ^(nc[0-9]+)-rc[0-9]+$ ]]; then
    echo "Ungültige Release-Candidate-Kennung: $keep_label" >&2
    exit 2
fi
release_series="${BASH_REMATCH[1]}"
if [[ ! -d "$dist_root" || -L "$dist_root" || "$dist_root" == '/' ]]; then
    echo "Release-Verzeichnis fehlt oder ist als Bereinigungsziel unsicher: $dist_root" >&2
    exit 2
fi

targets=()
shopt -s nullglob
candidates=("$dist_root"/ad-suite-"$release_series"-rc* "$dist_root"/ad-product-*-"$release_series"-rc*)
shopt -u nullglob
for candidate in "${candidates[@]}"; do
    name="$(basename "$candidate")"
    valid_candidate=0
    if [[ "$name" =~ ^ad-suite-${release_series}-rc[0-9]+(\.tar\.gz(\.sha256)?)?$ ]]; then
        valid_candidate=1
    elif [[ "$name" =~ ^ad-product-([a-z0-9_]+)-${release_series}-rc[0-9]+(\.tar\.gz(\.sha256)?)?$ ]] \
        && [[ -n "${allowed_products[${BASH_REMATCH[1]}]:-}" ]]; then
        valid_candidate=1
    fi
    if [[ "$valid_candidate" -eq 1 ]]; then
        if [[ "$name" == *"-$keep_label" || "$name" == *"-$keep_label.tar.gz" || "$name" == *"-$keep_label.tar.gz.sha256" ]]; then
            continue
        fi
        targets+=("$candidate")
    fi
done

printf '%s\n' "${targets[@]}"

if (( ! execute )); then
    echo "${#targets[@]} veraltete RC-Artefakte zur Bereinigung vorgemerkt; keine Datei wurde gelöscht."
    echo "Zum Löschen denselben Aufruf mit --execute wiederholen; $keep_label bleibt erhalten."
    exit 0
fi

for target in "${targets[@]}"; do
    rm -rf -- "$target"
done

echo "${#targets[@]} veraltete RC-Artefakte entfernt; $keep_label bleibt erhalten."
