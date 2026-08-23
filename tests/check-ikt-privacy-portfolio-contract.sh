#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
decision='docs/architecture-decisions/0003-permission-matrix-ikt-privacy-portfolio.md'

fail() {
    echo "IKT-/Datenschutz-Portfoliovertrag ungültig: $*" >&2
    exit 1
}

[[ -f "$workspace/$decision" ]] || fail "Entscheidung fehlt: $decision"

decision_text="$(<"$workspace/$decision")"
for contract in \
    'Status: angenommen' \
    'IKT/Datenschutz' \
    'keine Gremien-Arbeits-App' \
    'Kategorie B' \
    'eigenständige App' \
    'nicht in die Privacy-App verschmolzen' \
    'ohne aktive Privacy-App' \
    'br_permission_matrix' \
    'OrgSuite' \
    'data-suite="br"'; do
    [[ "$decision_text" == *"$contract"* ]] \
        || fail "Entscheidung enthält den Vertrag nicht: $contract"
done

for source in \
    AGENTS.md \
    docs/privacy-architecture.md \
    docs/implementation-tasks.md; do
    rg -Fq "$decision" "$workspace/$source" \
        || fail "$source verweist nicht auf $decision"
done

echo 'IKT-/Datenschutz-Portfoliovertrag: OK'
