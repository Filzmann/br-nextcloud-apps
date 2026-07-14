#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
ddev_project="$workspace/nextcloud-dev"
apps=(localbase orgsuite adcalendar adplaner adurlaub adroom)
container_root='/var/www/html/html/custom_apps'
tool="$container_root/localbase/tests/coverage/vendor/bin/phpcov"
merger="$container_root/localbase/tests/coverage/merge-clover.php"
container_output='/tmp/ad-suite-coverage'
host_output="$workspace/build/coverage"
summary="$host_output/php-summary.tsv"

if ! command -v ddev >/dev/null 2>&1; then
    echo 'DDEV fehlt.' >&2
    exit 1
fi
if ! (cd "$ddev_project" && ddev exec php -m | grep -qi xdebug); then
    echo 'Xdebug ist in DDEV nicht aktiv. Vorher ausführen: cd nextcloud-dev && ddev xdebug on' >&2
    exit 1
fi

(cd "$ddev_project" && ddev exec test -x "$tool") || {
    echo 'Coverage-Abhängigkeiten fehlen. Bitte tests/coverage/composer.lock installieren.' >&2
    exit 1
}

mkdir -p "$host_output"
printf 'app\texecutable_lines\tcovered_lines\tline_coverage_percent\n' > "$summary"
if [[ "${REUSE_COVERAGE:-0}" != "1" ]]; then
    (cd "$ddev_project" && ddev exec rm -rf "$container_output" && ddev exec mkdir -p "$container_output")
fi

for app in "${apps[@]}"; do
    echo "== $app: PHP-Coverage =="
    app_output="$container_output/$app"
    if [[ "${REUSE_COVERAGE:-0}" != "1" ]]; then
        (cd "$ddev_project" && ddev exec mkdir -p "$app_output")
        (cd "$ddev_project" && ddev exec -d "$container_root/$app" env \
            XDEBUG_MODE=coverage \
            PHP_COVERAGE_COMMAND="$tool" \
            PHP_COVERAGE_OUTPUT_DIR="$app_output" \
            php tests/run.php)
    fi
    (cd "$ddev_project" && ddev exec php "$merger" "$app" "$app_output") >> "$summary"
done

awk -F '\t' '
    NR > 1 { executable += $2; covered += $3 }
    END {
        percent = executable == 0 ? 0 : covered / executable * 100;
        printf "TOTAL\t%d\t%d\t%.2f\n", executable, covered, percent;
    }
' "$summary" >> "$summary"

minimum="${MIN_TOTAL_COVERAGE:-40}"
total_percent="$(awk -F '\t' '$1 == "TOTAL" { print $4 }' "$summary")"
if ! awk -v actual="$total_percent" -v minimum="$minimum" 'BEGIN { exit(actual + 0 >= minimum + 0 ? 0 : 1) }'; then
    echo "PHP-Line-Coverage ${total_percent} % liegt unter dem Mindestwert ${minimum} %." >&2
    exit 1
fi

echo "Coverage-Bericht: $summary"
column -t -s $'\t' "$summary" 2>/dev/null || cat "$summary"
