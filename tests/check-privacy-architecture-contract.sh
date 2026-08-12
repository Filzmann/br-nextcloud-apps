#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
privacy_contract='docs/privacy-architecture.md'

fail() {
    echo "Datenschutz-Architekturvertrag ungültig: $*" >&2
    exit 1
}

[[ -f "$workspace/$privacy_contract" ]] || fail "Normative Quelle fehlt: $privacy_contract"

for source in \
    AGENTS.md \
    docs/architecture.md \
    docs/implementation-tasks.md \
    .agents/skills/create-nextcloud-app/SKILL.md \
    .agents/skills/verify-workspace/SKILL.md; do
    rg -Fq "$privacy_contract" "$workspace/$source" \
        || fail "$source verweist nicht auf $privacy_contract"
done

privacy_text="$(<"$workspace/$privacy_contract")"
for contract in \
    'PersonalDataProvider' \
    'RetentionProvider' \
    'SubjectLifecycleProvider' \
    'REMOVE_PERSON_REFERENCE' \
    'Keine automatische Aktion' \
    'Self-Service' \
    'Admin-Auskunft' \
    'Migrationsmatrix' \
    'Kategorie B' \
    'docs/architecture-decisions/0001-shared-code-runtime-and-app-store.md' \
    'keinen direkten SQL-Zugriff'; do
    [[ "$privacy_text" == *"$contract"* ]] \
        || fail "Normative Quelle enthält den Vertrag nicht: $contract"
done

echo 'Datenschutz-Architekturvertrag: OK'
