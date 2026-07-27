#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
manifest="$workspace/config/workspace-repositories.tsv"
checker="$workspace/scripts/check-ad-suite-coverage-baseline.sh"
baseline="$workspace/scripts/ad-suite-js-coverage-baseline.tsv"
measurement="$workspace/scripts/measure-ad-suite-js-coverage.sh"
package="$workspace/localbase/tests/coverage/package.json"
lock="$workspace/localbase/tests/coverage/package-lock.json"

for file in "$baseline" "$measurement" "$package" "$lock"; do
    if [[ ! -f "$file" ]]; then
        echo "JavaScript-Coverage-Vertragsdatei fehlt: ${file#"$workspace/"}" >&2
        exit 1
    fi
done

mapfile -t registered_apps < <(awk -F '\t' '$2 == "app" { print $1 }' "$manifest")
for app in "${registered_apps[@]}"; do
    if [[ "$(awk -F '\t' -v app="$app" '$1 == app { count++ } END { print count + 0 }' "$baseline")" -ne 1 ]]; then
        echo "JavaScript-Coverage-Baseline muss genau eine Zeile für $app enthalten." >&2
        exit 1
    fi

    workflow="$workspace/$app/.github/workflows/tests.yml"
    minimum="$(awk -F '\t' -v app="$app" '$1 == app { print $2 }' "$baseline")"
    for contract in \
        'npm ci --prefix' \
        '--all' \
        "--include=js/**/*.js" \
        '--check-coverage' \
        '--lines='; do
        if ! grep -Fq -- "$contract" "$workflow"; then
            echo "JavaScript-Coverage-CI-Vertrag fehlt für $app: $contract" >&2
            exit 1
        fi
    done
    if ! grep -Fq -- "--lines=$minimum" "$workflow"; then
        echo "JavaScript-Coverage-CI-Schwelle weicht für $app von der Baseline $minimum ab." >&2
        exit 1
    fi
done

while IFS=$'\t' read -r app minimum; do
    [[ "$app" == 'app' || "$app" == 'TOTAL' ]] && continue
    if ! printf '%s\n' "${registered_apps[@]}" | grep -Fqx "$app"; then
        echo "JavaScript-Coverage-Baseline enthält nicht registrierte App: $app" >&2
        exit 1
    fi
done < "$baseline"

for contract in \
    'manifest="$workspace/config/workspace-repositories.tsv"' \
    "while IFS=\$'\\t' read -r path kind app_id required_skills" \
    "[[ \"\$kind\" == 'app' ]] || continue" \
    'apps+=("$path")' \
    '--all' \
    '--include=js/**/*.js' \
    'coverage-summary.json'; do
    if ! grep -Fq -- "$contract" "$measurement"; then
        echo "Dynamischer JavaScript-Coverage-Vertrag fehlt: $contract" >&2
        exit 1
    fi
done

node -e '
const packageJson = require(process.argv[1]);
if (!packageJson.devDependencies?.c8) process.exit(1);
' "$package"

temporary="$(mktemp -d)"
trap 'rm -rf "$temporary"' EXIT
summary="$temporary/summary.tsv"
{
    printf 'app\texecutable_lines\tcovered_lines\tline_coverage_percent\n'
    while IFS=$'\t' read -r app minimum; do
        [[ "$app" == 'app' ]] && continue
        printf '%s\t100\t100\t100.00\n' "$app"
    done < "$baseline"
} > "$summary"
bash "$checker" "$baseline" "$summary"

echo 'JavaScript-Coverage-Baseline-Test: OK'
