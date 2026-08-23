#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
privacy_contract='docs/privacy-architecture.md'
provider_guide='docs/privacy-provider-guide.md'
standalone_adr='docs/architecture-decisions/0002-standalone-privacy-platform.md'

fail() {
    echo "Datenschutz-Architekturvertrag ungültig: $*" >&2
    exit 1
}

[[ -f "$workspace/$privacy_contract" ]] || fail "Normative Quelle fehlt: $privacy_contract"
[[ -f "$workspace/$provider_guide" ]] || fail "Providerleitfaden fehlt: $provider_guide"
[[ -f "$workspace/$standalone_adr" ]] || fail "Standalone-ADR fehlt: $standalone_adr"

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
    'docs/architecture-decisions/0002-standalone-privacy-platform.md' \
    'docs/privacy-provider-guide.md' \
    'neutrale Standalone-App' \
    'kein Daten-Fallback' \
    'Cursor-Paging' \
    'Versionshandshake' \
    'keinen direkten SQL-Zugriff'; do
    [[ "$privacy_text" == *"$contract"* ]] \
        || fail "Normative Quelle enthält den Vertrag nicht: $contract"
done

[[ "$privacy_text" != *'späteren Ausbaustufe in LocalBase umgesetzt'* ]] \
    || fail 'LocalBase ist noch als dauerhafte Zielruntime beschrieben'

provider_text="$(<"$workspace/$provider_guide")"
for contract in \
    'PersonalDataProvider' \
    'complete' \
    'partial' \
    'not_applicable' \
    'failed' \
    'missing' \
    'SQL-Fallback' \
    'IUserMigrator' \
    'Contract-Test-Kit'; do
    [[ "$provider_text" == *"$contract"* ]] \
        || fail "Providerleitfaden enthält den Vertrag nicht: $contract"
done

adr_text="$(<"$workspace/$standalone_adr")"
for contract in \
    'Status: angenommen' \
    'Kategorie B' \
    'LocalBase' \
    'neutrale Standalone-App' \
    'keine automatische App-zu-App-Installation' \
    'kein Daten-Fallback'; do
    [[ "$adr_text" == *"$contract"* ]] \
        || fail "Standalone-ADR enthält den Vertrag nicht: $contract"
done

echo 'Datenschutz-Architekturvertrag: OK'
