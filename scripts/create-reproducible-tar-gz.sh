#!/usr/bin/env bash
set -euo pipefail

if (( $# != 3 )); then
    echo 'Verwendung: create-reproducible-tar-gz.sh ARCHIV QUELLVERZEICHNIS WURZELEINTRAG' >&2
    exit 2
fi

archive="$1"
source_directory="$2"
root_entry="$3"
source_date_epoch="${SOURCE_DATE_EPOCH:-0}"

if [[ ! "$source_date_epoch" =~ ^[0-9]+$ ]]; then
    echo 'SOURCE_DATE_EPOCH muss eine nichtnegative Ganzzahl sein.' >&2
    exit 2
fi
if [[ ! -d "$source_directory/$root_entry" ]]; then
    echo "Archivwurzel fehlt: $source_directory/$root_entry" >&2
    exit 1
fi

LC_ALL=C tar \
    --sort=name \
    --format=posix \
    --pax-option=delete=atime,delete=ctime \
    --mtime="@$source_date_epoch" \
    --owner=0 \
    --group=0 \
    --numeric-owner \
    -C "$source_directory" \
    -cf - "$root_entry" | gzip -n > "$archive"
