#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
manifest="$workspace/config/workspace-repositories.tsv"
source_file="$workspace/docs/parent-governance-contract.md"
start_marker='<!-- APP-AGENTS-GOVERNANCE:START -->'
end_marker='<!-- APP-AGENTS-GOVERNANCE:END -->'

fail() {
    echo "Parent-Governance-Vertrag ungültig: $*" >&2
    exit 1
}

[[ -f "$source_file" ]] || fail 'kanonische Quelle fehlt: docs/parent-governance-contract.md'

contract="$(awk -v start="$start_marker" -v end="$end_marker" '
    $0 == start { capture = 1; next }
    $0 == end { capture = 0; found = 1; next }
    capture { print }
    END { if (!found) exit 1 }
' "$source_file")" || fail 'kanonischer Block ist nicht vollständig markiert'

[[ "$contract" == *'## Parent-Governance-Vertrag: 1'* ]] \
    || fail 'Versionskennung fehlt'

while IFS=$'\t' read -r path kind app_id required_skills; do
    [[ "$kind" != 'parent' ]] || continue
    agents="$workspace/$path/AGENTS.md"
    [[ -f "$agents" ]] || fail "AGENTS.md fehlt: $path"
    agents_text="$(<"$agents")"
    [[ "$agents_text" == *"$contract"* ]] \
        || fail "lokale Governance-Projektion fehlt oder weicht ab: $path/AGENTS.md"
done < <(tail -n +2 "$manifest")

echo 'Parent-Governance-Vertrag: OK'
