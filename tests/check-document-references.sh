#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
checker="$workspace/scripts/check-document-references"
fixture_root="$(mktemp -d)"
trap 'rm -rf "$fixture_root"' EXIT

mkdir -p "$fixture_root/docs" "$fixture_root/scripts"
touch "$fixture_root/scripts/current-job.sh"

cat > "$fixture_root/docs/operations.md" <<'EOF'
Der aktuelle Ablauf verwendet `scripts/current-job.sh`.
EOF

valid_output="$($checker --workspace "$fixture_root" "$fixture_root/docs/operations.md" 2>&1)"
[[ "$valid_output" != *WARNUNG* ]] || {
    echo "Korrekte technische Dokumentreferenzen erzeugen eine Warnung." >&2
    exit 1
}

cat >> "$fixture_root/docs/operations.md" <<'EOF'
Der alte Ablauf verwendete `scripts/removed-job.sh`.
EOF

set +e
stale_output="$($checker --workspace "$fixture_root" "$fixture_root/docs/operations.md" 2>&1)"
stale_status=$?
set -e

(( stale_status != 0 )) || {
    echo "Eine veraltete exakte Skriptreferenz wurde nicht abgelehnt." >&2
    exit 1
}
grep -Fq 'scripts/removed-job.sh' <<< "$stale_output" || {
    echo "Der Fehler nennt die veraltete exakte Skriptreferenz nicht." >&2
    exit 1
}

cat > "$fixture_root/docs/heuristic.md" <<'EOF'
Der historische Text nennt `LegacyBackgroundJob.php` ohne Repositorypfad.
EOF
heuristic_output="$($checker --workspace "$fixture_root" "$fixture_root/docs/heuristic.md" 2>&1)"
grep -Fq 'WARNUNG' <<< "$heuristic_output" || {
    echo "Eine nur heuristisch erkennbare technische Referenz erzeugt keine Warnung." >&2
    exit 1
}

echo 'Dokumentreferenz-Check: OK'
