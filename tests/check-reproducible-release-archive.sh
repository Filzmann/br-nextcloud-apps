#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
archiver="$workspace/scripts/create-reproducible-tar-gz.sh"
temporary="$(mktemp -d)"

cleanup() {
    rm -rf "$temporary"
}
trap cleanup EXIT

mkdir -p "$temporary/source/demo/sub"
printf '%s\n' 'alpha' > "$temporary/source/demo/a.txt"
printf '%s\n' 'beta' > "$temporary/source/demo/sub/b.txt"

touch -t 202601010101 "$temporary/source/demo/a.txt" "$temporary/source/demo/sub/b.txt"
"$archiver" "$temporary/first.tar.gz" "$temporary/source" demo

touch -t 202608090909 "$temporary/source/demo/a.txt" "$temporary/source/demo/sub/b.txt"
"$archiver" "$temporary/second.tar.gz" "$temporary/source" demo

if ! cmp -s "$temporary/first.tar.gz" "$temporary/second.tar.gz"; then
    echo 'Releasearchive sind bei identischem Inhalt nicht bytegleich reproduzierbar.' >&2
    exit 1
fi

if [[ "$(tar -tzf "$temporary/first.tar.gz" | head -n 1)" != 'demo/' ]]; then
    echo 'Das reproduzierbare Archiv besitzt nicht den erwarteten Wurzelordner.' >&2
    exit 1
fi

builder="$workspace/scripts/build-ad-suite-release.sh"
for expected_call in \
    '"$reproducible_archiver" "$archive" "$stage" "$app"' \
    '"$reproducible_archiver" "$product_bundle" "$dist_root" "$product_name"' \
    '"$reproducible_archiver" "$bundle" "$dist_root" "$(basename "$release_dir")"'; do
    if ! grep -Fq "$expected_call" "$builder"; then
        echo "Der AD-Suite-Builder verwendet den reproduzierbaren Archivierer nicht vollständig: $expected_call" >&2
        exit 1
    fi
done

if grep -Fq -- '-czf' "$builder"; then
    echo 'Der AD-Suite-Builder enthält weiterhin einen nicht normalisierten gzip-Tar-Aufruf.' >&2
    exit 1
fi

echo 'Reproduzierbarer Release-Archivvertrag: OK'
