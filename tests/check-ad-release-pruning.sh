#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
pruner="$workspace/scripts/prune-ad-suite-release-candidates.sh"
builder="$workspace/scripts/build-ad-suite-release.sh"
temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT

if grep -Fq 'prune-ad-suite-release-candidates.sh' "$builder"; then
    echo 'Ein normaler AD-Suite-Build ruft weiterhin automatisch die RC-Bereinigung auf.' >&2
    exit 1
fi

for label in nc35-rc4 nc35-rc7 nc35-rc8; do
    mkdir -p "$temporary/ad-suite-$label" "$temporary/ad-product-adcalendar-$label" "$temporary/ad-product-adrecruitment-$label"
    touch "$temporary/ad-suite-$label.tar.gz" "$temporary/ad-suite-$label.tar.gz.sha256"
    touch "$temporary/ad-product-adcalendar-$label.tar.gz" "$temporary/ad-product-adcalendar-$label.tar.gz.sha256"
    touch "$temporary/ad-product-adrecruitment-$label.tar.gz" "$temporary/ad-product-adrecruitment-$label.tar.gz.sha256"
done
touch "$temporary/ad-suite-1.0.0.tar.gz"
touch "$temporary/ad-suite-nc34-rc9.tar.gz"

preview="$($pruner --dist-root "$temporary" --keep-label nc35-rc8)"

for expected in \
    ad-suite-nc35-rc4 \
    ad-suite-nc35-rc4.tar.gz \
    ad-product-adcalendar-nc35-rc7.tar.gz.sha256; do
    grep -Fqx "$temporary/$expected" <<< "$preview" \
        || { echo "Vorschau nennt das erkannte RC-Artefakt nicht exakt: $expected" >&2; exit 1; }
    [[ -e "$temporary/$expected" ]] \
        || { echo "Die Vorschau hat ein RC-Artefakt gelöscht: $expected" >&2; exit 1; }
done

"$pruner" --dist-root "$temporary" --keep-label nc35-rc8 --execute

if find "$temporary" -maxdepth 1 -mindepth 1 \( -name '*-nc35-rc4*' -o -name '*-nc35-rc7*' \) -print -quit | grep -q .; then
    echo 'Veraltete Release Candidates wurden nicht vollständig entfernt.' >&2
    exit 1
fi
for required in \
    ad-suite-nc35-rc8 \
    ad-suite-nc35-rc8.tar.gz \
    ad-suite-nc35-rc8.tar.gz.sha256 \
    ad-product-adcalendar-nc35-rc8 \
    ad-product-adcalendar-nc35-rc8.tar.gz \
    ad-product-adcalendar-nc35-rc8.tar.gz.sha256 \
    ad-product-adrecruitment-nc35-rc8 \
    ad-product-adrecruitment-nc35-rc8.tar.gz \
    ad-product-adrecruitment-nc35-rc8.tar.gz.sha256 \
    ad-suite-nc34-rc9.tar.gz \
    ad-suite-1.0.0.tar.gz; do
    [[ -e "$temporary/$required" ]] || { echo "Aktuelles oder finales Artefakt wurde entfernt: $required" >&2; exit 1; }
done

if "$pruner" --dist-root "$temporary" --keep-label release-1 >/dev/null 2>&1; then
    echo 'Eine nicht nummerierte RC-Kennung wurde akzeptiert.' >&2
    exit 1
fi

unsafe_target="$temporary-unsafe-target"
mkdir -p "$unsafe_target"
ln -s "$unsafe_target" "$temporary/unsafe-dist"
if "$pruner" --dist-root "$temporary/unsafe-dist" --keep-label nc35-rc8 >/dev/null 2>&1; then
    echo 'Ein symbolischer Link wurde als Dist-Root akzeptiert.' >&2
    exit 1
fi
rm -rf "$unsafe_target"

echo 'AD-Release-Candidate-Bereinigung: OK'
