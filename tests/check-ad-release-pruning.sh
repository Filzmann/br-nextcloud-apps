#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
pruner="$workspace/scripts/prune-ad-suite-release-candidates.sh"
temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT

for label in nc34-rc4 nc34-rc7 nc34-rc8; do
    mkdir -p "$temporary/ad-suite-$label" "$temporary/ad-product-adcalendar-$label" "$temporary/ad-product-adrecruitment-$label"
    touch "$temporary/ad-suite-$label.tar.gz" "$temporary/ad-suite-$label.tar.gz.sha256"
    touch "$temporary/ad-product-adcalendar-$label.tar.gz" "$temporary/ad-product-adcalendar-$label.tar.gz.sha256"
    touch "$temporary/ad-product-adrecruitment-$label.tar.gz" "$temporary/ad-product-adrecruitment-$label.tar.gz.sha256"
done
touch "$temporary/ad-suite-1.0.0.tar.gz"

"$pruner" --dist-root "$temporary" --keep-label nc34-rc8

if find "$temporary" -maxdepth 1 -mindepth 1 \( -name '*-nc34-rc4*' -o -name '*-nc34-rc7*' \) -print -quit | grep -q .; then
    echo 'Veraltete Release Candidates wurden nicht vollständig entfernt.' >&2
    exit 1
fi
for required in \
    ad-suite-nc34-rc8 \
    ad-suite-nc34-rc8.tar.gz \
    ad-suite-nc34-rc8.tar.gz.sha256 \
    ad-product-adcalendar-nc34-rc8 \
    ad-product-adcalendar-nc34-rc8.tar.gz \
    ad-product-adcalendar-nc34-rc8.tar.gz.sha256 \
    ad-product-adrecruitment-nc34-rc8 \
    ad-product-adrecruitment-nc34-rc8.tar.gz \
    ad-product-adrecruitment-nc34-rc8.tar.gz.sha256 \
    ad-suite-1.0.0.tar.gz; do
    [[ -e "$temporary/$required" ]] || { echo "Aktuelles oder finales Artefakt wurde entfernt: $required" >&2; exit 1; }
done

if "$pruner" --dist-root "$temporary" --keep-label release-1 >/dev/null 2>&1; then
    echo 'Eine nicht nummerierte RC-Kennung wurde akzeptiert.' >&2
    exit 1
fi

echo 'AD-Release-Candidate-Bereinigung: OK'
