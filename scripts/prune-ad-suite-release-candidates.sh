#!/usr/bin/env bash
set -euo pipefail

usage() {
    echo 'Aufruf: prune-ad-suite-release-candidates.sh --dist-root <Pfad> --keep-label nc34-rcN' >&2
}

dist_root=''
keep_label=''
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
    if [[ "$name" =~ ^(ad-suite|ad-product-(adcalendar|adplaner|adurlaub|adroom))-nc34-rc[0-9]+(\.tar\.gz(\.sha256)?)?$ ]]; then
        if [[ "$name" == *"-$keep_label" || "$name" == *"-$keep_label.tar.gz" || "$name" == *"-$keep_label.tar.gz.sha256" ]]; then
            continue
        fi
        rm -rf -- "$candidate"
        removed=$((removed + 1))
    fi
done

echo "$removed veraltete RC-Artefakte entfernt; $keep_label bleibt erhalten."
