#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
harness="$workspace/filzmann-ai-harness"

cmp "$harness/viewer/lib/manual_acceptance_viewer.py" "$workspace/scripts/lib/manual_acceptance_viewer.py"
cmp "$harness/viewer/assets/manual-acceptance-viewer.js" "$workspace/scripts/assets/manual-acceptance-viewer.js"
cmp "$harness/viewer/assets/manual-acceptance-viewer.css" "$workspace/scripts/assets/manual-acceptance-viewer.css"
python3 "$harness/tests/test_contract.py"

grep -Fq $'filzmann-ai-harness\ttool\t-\t-' "$workspace/config/workspace-repositories.tsv"
grep -Fq $'filzmann-ai-evidence\tevidence\t-\t-' "$workspace/config/workspace-repositories.tsv"
grep -Fq 'keinen Freigabestatus' "$workspace/docs/workspace.md"
grep -Fq 'Staging-PASS-Evidence' "$workspace/docs/app-repository-structure.md"

echo 'Acceptance-Harness-Contract: OK'
