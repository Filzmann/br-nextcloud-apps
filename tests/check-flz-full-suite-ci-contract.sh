#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
manifest="$workspace/config/workspace-repositories.tsv"
php_coverage_baseline="$workspace/scripts/flz-full-suite-php-coverage-baseline.tsv"
mapfile -t apps < <(awk -F '\t' '$2 == "app" { print $1 }' "$manifest")
consumers=(orgsuite flzcalendar flzplaner flzurlaub flzroom brtop brstunden flz_permission_matrix flzrecruitment)

require_text() {
    local file="$1"
    local text="$2"
    local description="$3"

    if ! grep -Fq -- "$text" "$file"; then
        echo "$description fehlt in ${file#"$workspace/"}." >&2
        exit 1
    fi
}

require_text "$workspace/scripts/check-apps" \
    'php tests/permission-provider-v1-contract.php' \
    'App-übergreifender Permission-Provider-V1-Vertrag'
require_text "$workspace/scripts/check-apps" \
    'php tests/privacy-provider-v1-contract.php' \
    'App-übergreifender Privacy-Provider-V1-Vertrag'
require_text "$workspace/scripts/check-apps" \
    'php tests/localbase-organization-v1-contract.php' \
    'App-übergreifender LocalBase-Organisationsprovider-V1-Vertrag'

for app in "${apps[@]}"; do
    workflow="$workspace/$app/.github/workflows/tests.yml"
    if [[ ! -f "$workflow" ]]; then
        echo "CI-Workflow fehlt: $app/.github/workflows/tests.yml" >&2
        exit 1
    fi

    require_text "$workflow" 'push:' 'Push-Trigger'
    if [[ "$app" == flz_data_protection ]]; then
        require_text "$workflow" "branches: [flz-full-rebrand, 'candidate/WO-*']" 'Push-Begrenzung auf Default- und Candidate-Branches'
    else
        require_text "$workflow" "branches: [main, 'candidate/WO-*']" 'Push-Begrenzung auf main und Candidate-Branches'
    fi
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
    require_text "$workflow" 'composer install --working-dir=test-harness/coverage' 'PHP-Coverage-Tooling'
    require_text "$workflow" 'PHP_COVERAGE_COMMAND="$GITHUB_WORKSPACE/test-harness/coverage/vendor/bin/phpcov"' 'PHP-Coverage-Kommando'
    require_text "$workflow" 'PHP_COVERAGE_OUTPUT_DIR="$RUNNER_TEMP/php-coverage"' 'PHP-Coverage-Ausgabe'
    require_text "$workflow" \
        "php \"\$GITHUB_WORKSPACE/test-harness/coverage/merge-clover.php\" $app \"\$RUNNER_TEMP/php-coverage\" $minimum_php_coverage" \
        'PHP-Coverage-Baseline'
done

for app in "${consumers[@]}"; do
    workflow="$workspace/$app/.github/workflows/tests.yml"
    # Das Test-Harness liegt im Parent-Repository (test-harness/). Beide Jobs
    # (PHP und JavaScript) müssen den Parent unverändert in den Workspace-Root
    # auschecken, damit test-harness/ lokal und in der CI identisch auflöst.
    if grep -Fq 'nextcloud-localbase' "$workflow"; then
        echo "Veralteter LocalBase-Checkout in ${workflow#"$workspace/"}." >&2
        exit 1
    fi
    require_text "$workflow" 'repository: Filzmann/br-nextcloud-apps' 'Parent-Checkout (Test-Harness)'
    require_text "$workflow" "path: '.'" 'Parent-Workspace-Wurzel'
    require_text "$workflow" 'clean: false' 'Parent-Checkout ohne Clean'
    require_text "$workflow" 'ref: main' 'Parent-main-Referenz'
    if ! awk '
        function check_step()
        {
            if (parent)
            {
                checkouts++
                if (!checkout || !root || !noclean || !mainref) invalid = 1
                if (parent_ln == 0) parent_ln = NR
            }
            parent = checkout = root = noclean = mainref = 0
        }
        /^      - / { check_step() }
        /^        uses: actions\/checkout@v7$/ { checkout = 1 }
        /^          repository: Filzmann\/br-nextcloud-apps$/ { parent = 1 }
        /^          ref: main$/ { mainref = 1 }
        /^          path: .{3}$/ { root = 1 }
        /^          clean: false$/ { noclean = 1 }
        /^      - name: App auschecken$/ { if (app_ln == 0) app_ln = NR }
        END {
            check_step()
            # checkout mit path "." wischelt den Workspace, daher muss der
            # Parent-Checkout vor dem App-Checkout (path: app) laufen.
            exit (checkouts != 2 || invalid || parent_ln == 0 || app_ln == 0 || parent_ln > app_ln)
        }
    ' "$workflow"; then
        echo "PHP- und JavaScript-Job müssen je einen Parent-Checkout (ref: main, path: '.', clean: false) vor dem App-Checkout enthalten: ${workflow#"$workspace/"}." >&2
        exit 1
    fi
done

echo 'Workspace-CI-Vertrag: OK'
