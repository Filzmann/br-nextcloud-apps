#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
checker="$workspace/scripts/check-ad-suite-coverage-baseline.sh"
temporary="$(mktemp -d)"

cleanup() {
    rm -rf "$temporary"
}
trap cleanup EXIT

baseline="$temporary/baseline.tsv"
summary="$temporary/summary.tsv"

cat > "$baseline" <<'EOF'
app	minimum_line_coverage_percent
alpha	40.00
beta	80.00
TOTAL	60.00
EOF

cat > "$summary" <<'EOF'
app	executable_lines	covered_lines	line_coverage_percent
alpha	100	45	45.00
beta	100	80	80.00
TOTAL	200	125	62.50
EOF

bash "$checker" "$baseline" "$summary"

sed -i 's/beta\t100\t80\t80.00/beta\t100\t79\t79.00/' "$summary"
if bash "$checker" "$baseline" "$summary" >"$temporary/regression.out" 2>&1; then
    echo 'Coverage-Rückgang wurde nicht abgelehnt.' >&2
    exit 1
fi
grep -q 'beta: 79.00 % liegt unter 80.00 %' "$temporary/regression.out"

sed -i '/^beta\t/d' "$summary"
if bash "$checker" "$baseline" "$summary" >"$temporary/missing.out" 2>&1; then
    echo 'Fehlende App-Coverage wurde nicht abgelehnt.' >&2
    exit 1
fi
grep -q 'Coverage-Zeile fehlt: beta' "$temporary/missing.out"

echo 'Coverage-Baseline-Test: OK'
