#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT

git -C "$fixture" init -q
printf '# bleibt\n' > "$fixture/existing.md"
printf '# wird umbenannt\n' > "$fixture/old-name.md"
printf 'kein Markdown\n' > "$fixture/ignored.txt"
git -C "$fixture" add existing.md old-name.md ignored.txt

rm "$fixture/old-name.md"
printf '# neuer Name\n' > "$fixture/new-name.md"

actual="$($workspace/scripts/list-worktree-markdown-files "$fixture")"
expected=$'existing.md\nnew-name.md'

[[ "$actual" == "$expected" ]] || {
    printf 'Markdown-Worktree-Inventar falsch.\nErwartet:\n%s\nTatsächlich:\n%s\n' \
        "$expected" "$actual" >&2
    exit 1
}

echo 'Markdown-Worktree-Inventar: OK'
