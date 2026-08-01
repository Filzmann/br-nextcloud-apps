#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo 'Aufruf: prune-ad-suite-release-candidates.sh --dist-root <Pfad> --keep-label nc34-rcN' >&2
}

dist_root=''
keep_label=''
workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog_reader="$workspace/scripts/read-ad-product-catalog.php"
php "$catalog_reader" validate >/dev/null
mapfile -t catalog_products < <(php "$catalog_reader" products)
declare -A allowed_products=()
for catalog_product in "${catalog_products[@]}"; do
    allowed_products["$catalog_product"]=1
done
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dist-root) dist_root="${2:-}"; shift 2 ;;
        --keep-label) keep_label="${2:-}"; shift 2 ;;
        *) usage; exit 2 ;;
    esac
done

if [[ ! "$keep_label" =~ ^nc34-rc[0-9]+$ ]]; then
    echo "Ungültige Release-Candidate-Kennung: $keep_label" >&2
    exit 2
fi
if [[ ! -d "$dist_root" ]]; then
    echo "Release-Verzeichnis fehlt: $dist_root" >&2
    exit 2
fi

removed=0
shopt -s nullglob
candidates=("$dist_root"/ad-suite-nc34-rc* "$dist_root"/ad-product-*-nc34-rc*)
shopt -u nullglob
for candidate in "${candidates[@]}"; do
    name="$(basename "$candidate")"
    valid_candidate=0
    if [[ "$name" =~ ^ad-suite-nc34-rc[0-9]+(\.tar\.gz(\.sha256)?)?$ ]]; then
        valid_candidate=1
    elif [[ "$name" =~ ^ad-product-([a-z0-9_]+)-nc34-rc[0-9]+(\.tar\.gz(\.sha256)?)?$ ]] \
        && [[ -n "${allowed_products[${BASH_REMATCH[1]}]:-}" ]]; then
        valid_candidate=1
    fi
    if [[ "$valid_candidate" -eq 1 ]]; then
        if [[ "$name" == *"-$keep_label" || "$name" == *"-$keep_label.tar.gz" || "$name" == *"-$keep_label.tar.gz.sha256" ]]; then
            continue
        fi
        rm -rf -- "$candidate"
        removed=$((removed + 1))
    fi
done

echo "$removed veraltete RC-Artefakte entfernt; $keep_label bleibt erhalten."
