#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
cd "$workspace"

decision='docs/architecture-decisions/0004-app-local-temporary-admin-full-access.md'

test -f "$decision"

for required in \
    'Kategorie C' \
    'pro Admin und App' \
    '24 Stunden' \
    'standardmäßig aus' \
    'fehlende oder abgelaufene Freigabe' \
    'PersonalDataProvider' \
    'PermissionProvider'; do
    grep -Fq "$required" "$decision" || {
        echo "Admin-Vollzugriffsvertrag fehlt in $decision: $required" >&2
        exit 1
    }
done

grep -Fq '0004-app-local-temporary-admin-full-access.md' AGENTS.md
grep -Fq 'check-app-local-admin-full-access-contract.sh' scripts/check-fast

echo 'App-lokaler Admin-Vollzugriffsvertrag: OK'
