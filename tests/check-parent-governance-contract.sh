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

[[ "$contract" == *'## Parent-Governance-Vertrag: 2'* ]] \
    || fail 'Versionskennung fehlt'

# The standalone projection must not become an independently maintained rule.
python3 - "$workspace" <<'PY'
from pathlib import Path
import sys
root = Path(sys.argv[1])
architecture = (root / 'docs/architecture.md').read_text()
governance = (root / 'docs/parent-governance-contract.md').read_text()
phase = governance.split('### Entwicklungsphase und Kompatibilitätsbedarf\n', 1)[1].split('\n### Prüfaufwand', 1)[0].strip()
agents = (root / 'AGENTS.md').read_text()
efficiency = agents.split('## Test-, UI- und Datenqualität\n\n', 1)[1].split('\n### Testgetriebene', 1)[0].strip()
projection = governance
architecture_phase = architecture.split('## Entwicklungsphase und Kompatibilitätsbedarf\n', 1)[1].split('\n## Repository- und Produktgrenzen', 1)[0].strip()
assert phase in projection, 'Development-phase block missing from canonical governance contract'
assert '[`Parent-Governance-Vertrag`](parent-governance-contract.md)' in architecture_phase, 'Architecture does not reference the canonical governance contract'
assert 'kanonische Lifecycle-Quelle' in architecture_phase, 'Architecture does not identify the canonical lifecycle source'
assert phase not in architecture_phase, 'Architecture duplicates the canonical lifecycle block'
assert efficiency in projection, 'Check-efficiency projection differs from canonical AGENTS.md'
PY

while IFS=$'\t' read -r path kind app_id required_skills; do
    [[ "$kind" != 'parent' ]] || continue
    agents="$workspace/$path/AGENTS.md"
    [[ -f "$agents" ]] || fail "AGENTS.md fehlt: $path"
    agents_text="$(<"$agents")"
    [[ "$agents_text" == *"$contract"* ]] \
        || fail "lokale Governance-Projektion fehlt oder weicht ab: $path/AGENTS.md"
done < <(tail -n +2 "$manifest")

echo 'Parent-Governance-Vertrag: OK'
