#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
apps=(localbase orgsuite adcalendar adplaner adurlaub adroom)
consumers=(orgsuite adcalendar adplaner adurlaub adroom)
products=(adcalendar adplaner adurlaub adroom)

require_text() {
    local file="$1"
    local text="$2"
    local description="$3"

    if ! grep -Fq -- "$text" "$file"; then
        echo "$description fehlt in ${file#"$workspace/"}." >&2
        exit 1
    fi
}

for app in "${apps[@]}"; do
    workflow="$workspace/$app/.github/workflows/tests.yml"
    if [[ ! -f "$workflow" ]]; then
        echo "CI-Workflow fehlt: $app/.github/workflows/tests.yml" >&2
        exit 1
    fi

    require_text "$workflow" 'push:' 'Push-Trigger'
    require_text "$workflow" 'branches: [main]' 'Push-Begrenzung auf main'
    require_text "$workflow" 'pull_request:' 'Pull-Request-Trigger'
    require_text "$workflow" 'contents: read' 'Read-only-Berechtigung'
    require_text "$workflow" 'actions/checkout@v7' 'Checkout v7'
    require_text "$workflow" 'shivammathur/setup-php@v2' 'PHP-Setup'
    require_text "$workflow" "php-version: ['8.3', '8.5']" 'PHP-Matrix'
    require_text "$workflow" 'actions/setup-node@v6' 'Node-Setup v6'
    require_text "$workflow" 'node-version: 24' 'Node-24-Vertrag'
    require_text "$workflow" 'php tests/run.php' 'PHP-Testlauf'
    require_text "$workflow" 'node tests/run-js.mjs' 'JavaScript-Testlauf'
done

for app in "${consumers[@]}"; do
    workflow="$workspace/$app/.github/workflows/tests.yml"
    require_text "$workflow" 'repository: Filzmann/nextcloud-localbase' 'LocalBase-Checkout'
    require_text "$workflow" 'path: localbase' 'LocalBase-Nachbarpfad'
done

for app in "${products[@]}"; do
    workflow="$workspace/$app/.github/workflows/tests.yml"
    require_text "$workflow" 'name: LocalBase-Referenz bestimmen' 'Dynamische LocalBase-Referenz'
    require_text "$workflow" 'CANDIDATE_REF: ${{ github.head_ref || github.ref_name }}' 'LocalBase-Branchkandidat'
    require_text "$workflow" 'git ls-remote --exit-code --heads https://github.com/Filzmann/nextcloud-localbase.git' 'Sichere LocalBase-Branchprüfung'
    require_text "$workflow" 'echo "ref=main" >> "$GITHUB_OUTPUT"' 'LocalBase-main-Fallback'
    require_text "$workflow" 'ref: ${{ steps.localbase-ref.outputs.ref }}' 'Aufgelöste LocalBase-Referenz'
done

localbase_workflow="$workspace/localbase/.github/workflows/tests.yml"
for app in "${consumers[@]}"; do
    require_text "$localbase_workflow" "repository: Filzmann/nextcloud-$app" "Verbraucher-Checkout $app"
done

echo 'AD-Suite-CI-Vertrag: OK'
