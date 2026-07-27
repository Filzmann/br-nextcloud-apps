#!/usr/bin/env bash
set -euo pipefail

if [[ "$#" -ne 2 ]]; then
    echo 'Aufruf: check-ad-suite-coverage-baseline.sh <Baseline.tsv> <Coverage-Zusammenfassung.tsv>' >&2
    exit 2
fi

baseline="$1"
summary="$2"

for file in "$baseline" "$summary"; do
    if [[ ! -f "$file" ]]; then
        echo "Coverage-Datei fehlt: $file" >&2
        exit 2
    fi
done

awk -F '\t' '
    function report(message) {
        print message > "/dev/stderr";
        errors++;
    }

    NR == FNR {
        if (FNR == 1) {
            if ($1 != "app" || $2 != "minimum_line_coverage_percent") {
                report("Ungültiger Baseline-Header.");
            }
            next;
        }
        if ($1 == "" || $2 !~ /^[0-9]+([.][0-9]+)?$/) {
            report("Ungültige Baseline-Zeile: " $0);
            next;
        }
        if ($1 in minimum) {
            report("Doppelte Baseline-Zeile: " $1);
            next;
        }
        order[++baselineCount] = $1;
        minimum[$1] = $2 + 0;
        minimumText[$1] = $2;
        next;
    }

    FNR == 1 {
        if ($1 != "app" || $2 != "executable_lines" || $3 != "covered_lines" || $4 != "line_coverage_percent") {
            report("Ungültiger Coverage-Header.");
        }
        next;
    }

    {
        if ($1 == "" || $4 !~ /^[0-9]+([.][0-9]+)?$/) {
            report("Ungültige Coverage-Zeile: " $0);
            next;
        }
        actual[$1] = $4 + 0;
        actualText[$1] = $4;
    }

    END {
        for (i = 1; i <= baselineCount; i++) {
            app = order[i];
            if (!(app in actual)) {
                report("Coverage-Zeile fehlt: " app);
                continue;
            }
            if (actual[app] < minimum[app]) {
                report(app ": " actualText[app] " % liegt unter " minimumText[app] " %.");
            }
        }
        if (errors > 0) exit 1;
        print "Coverage-Baseline: OK";
    }
' "$baseline" "$summary"
