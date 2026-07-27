#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
manifest="$workspace/config/workspace-repositories.tsv"
php_coverage_baseline="$workspace/scripts/ad-suite-php-coverage-baseline.tsv"
mapfile -t apps < <(awk -F '\t' '$2 == "app" { print $1 }' "$manifest")
consumers=(orgsuite adcalendar adplaner adurlaub adroom brtop brstunden br_permission_matrix adrecruitment)
branch_matched_consumers=(orgsuite adcalendar adplaner adurlaub adroom brtop brstunden br_permission_matrix adrecruitment)
declare -A consumer_repositories=(
    [orgsuite]='nextcloud-orgsuite'
    [adcalendar]='nextcloud-adcalendar'
    [adplaner]='nextcloud-adplaner'
    [adurlaub]='nextcloud-adurlaub'
    [adroom]='nextcloud-adroom'
    [brtop]='nextcloud-brtop'
    [brstunden]='nextcloud-brstunden'
    [br_permission_matrix]='nextcloud-br-permission-matrix'
    [adrecruitment]='nextcloud-recruitment'
)

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

    minimum_php_coverage="$(awk -F '\t' -v app="$app" '$1 == app { print $2 }' "$php_coverage_baseline")"
    require_text "$workflow" 'name: PHP-Coverage' 'PHP-Coverage-Job'
    require_text "$workflow" "if: matrix.php-version == '8.3'" 'PHP-Coverage-Laufzeit'
    require_text "$workflow" "coverage: \${{ matrix.php-version == '8.3' && 'xdebug' || 'none' }}" 'Selektive Xdebug-Coverage'
    require_text "$workflow" "if: matrix.php-version == '8.5'" 'PHP-Schnelltest-Laufzeit'
    require_text "$workflow" 'composer install --working-dir=localbase/tests/coverage' 'PHP-Coverage-Tooling'
    require_text "$workflow" 'PHP_COVERAGE_COMMAND="$GITHUB_WORKSPACE/localbase/tests/coverage/vendor/bin/phpcov"' 'PHP-Coverage-Kommando'
    require_text "$workflow" 'PHP_COVERAGE_OUTPUT_DIR="$RUNNER_TEMP/php-coverage"' 'PHP-Coverage-Ausgabe'
    require_text "$workflow" \
        "php \"\$GITHUB_WORKSPACE/localbase/tests/coverage/merge-clover.php\" $app \"\$RUNNER_TEMP/php-coverage\" $minimum_php_coverage" \
        'PHP-Coverage-Baseline'
done

for app in "${consumers[@]}"; do
    workflow="$workspace/$app/.github/workflows/tests.yml"
    require_text "$workflow" 'repository: Filzmann/nextcloud-localbase' 'LocalBase-Checkout'
    require_text "$workflow" 'path: localbase' 'LocalBase-Nachbarpfad'
done

for app in "${branch_matched_consumers[@]}"; do
    workflow="$workspace/$app/.github/workflows/tests.yml"
    require_text "$workflow" 'name: LocalBase-Referenz bestimmen' 'Dynamische LocalBase-Referenz'
    require_text "$workflow" 'CANDIDATE_REF: ${{ github.head_ref || github.ref_name }}' 'LocalBase-Branchkandidat'
    require_text "$workflow" 'git ls-remote --exit-code --heads https://github.com/Filzmann/nextcloud-localbase.git' 'Sichere LocalBase-Branchprüfung'
    require_text "$workflow" 'echo "ref=main" >> "$GITHUB_OUTPUT"' 'LocalBase-main-Fallback'
    require_text "$workflow" 'ref: ${{ steps.localbase-ref.outputs.ref }}' 'Aufgelöste LocalBase-Referenz'
done

localbase_workflow="$workspace/localbase/.github/workflows/tests.yml"
for app in "${consumers[@]}"; do
    require_text "$localbase_workflow" "repository: Filzmann/${consumer_repositories[$app]}" "Verbraucher-Checkout $app"
done

echo 'Workspace-CI-Vertrag: OK'
