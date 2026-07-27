#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
manifest="$workspace/config/workspace-repositories.tsv"
tool="$workspace/localbase/tests/coverage/node_modules/.bin/c8"
host_output="$workspace/build/coverage/js"
summary="$workspace/build/coverage/js-summary.tsv"
baseline="${JS_COVERAGE_BASELINE_FILE:-$workspace/scripts/ad-suite-js-coverage-baseline.tsv}"
baseline_checker="$workspace/scripts/check-ad-suite-coverage-baseline.sh"
apps=()
while IFS=$'\t' read -r path kind app_id required_skills; do
    [[ "$kind" == 'app' ]] || continue
    apps+=("$path")
done < "$manifest"

if [[ ! -x "$tool" ]]; then
    echo 'JavaScript-Coverage-Abhängigkeiten fehlen. Bitte npm ci --prefix localbase/tests/coverage ausführen.' >&2
    exit 1
fi

mkdir -p "$host_output"
printf 'app\texecutable_lines\tcovered_lines\tline_coverage_percent\n' > "$summary"

for app in "${apps[@]}"; do
    echo "== $app: JavaScript-Coverage =="
    report_dir="$host_output/$app"
    mkdir -p "$report_dir"
    (
        cd "$workspace/$app"
        "$tool" \
            --all \
            '--include=js/**/*.js' \
            --reporter=json-summary \
            --report-dir="$report_dir" \
            node tests/run-js.mjs
    )
    node -e '
        const report = require(process.argv[1]);
        const app = process.argv[2];
        const lines = report.total.lines;
        process.stdout.write(`${app}\t${lines.total}\t${lines.covered}\t${Number(lines.pct).toFixed(2)}\n`);
    ' "$report_dir/coverage-summary.json" "$app" >> "$summary"
done

awk -F '\t' '
    NR > 1 { executable += $2; covered += $3 }
    END {
        percent = executable == 0 ? 0 : covered / executable * 100;
        printf "TOTAL\t%d\t%d\t%.2f\n", executable, covered, percent;
    }
' "$summary" >> "$summary"

bash "$baseline_checker" "$baseline" "$summary"

echo "JavaScript-Coverage-Bericht: $summary"
column -t -s $'\t' "$summary" 2>/dev/null || cat "$summary"
