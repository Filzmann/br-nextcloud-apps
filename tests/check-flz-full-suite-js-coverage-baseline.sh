#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
manifest="$workspace/config/workspace-repositories.tsv"
checker="$workspace/scripts/check-flz-full-suite-coverage-baseline.sh"
baseline="$workspace/scripts/flz-full-suite-js-coverage-baseline.tsv"
measurement="$workspace/scripts/measure-flz-full-suite-js-coverage.sh"
package="$workspace/test-harness/coverage/package.json"
lock="$workspace/test-harness/coverage/package-lock.json"

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

runtime_checker="$workspace/scripts/check-js-runtime-coverage"
fixture="$temporary/runtime-app"
mkdir -p "$fixture/js" "$fixture/tests"
git -C "$fixture" init -q
printf "import { readFileSync } from 'node:fs';\nreadFileSync('js/behavior.js', 'utf8');\n" > "$fixture/tests/run-js.mjs"
git -C "$fixture" add tests/run-js.mjs
git -C "$fixture" -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm initial
printf 'globalThis.syntheticBehavior = 42;\n' > "$fixture/js/behavior.js"

if bash "$runtime_checker" --repo "$fixture" --base HEAD > "$temporary/source-only.out" 2>&1; then
    echo 'Quelltextprüfung wurde als JS-Laufzeitnachweis akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Kein JS-Laufzeitnachweis: js/behavior.js' "$temporary/source-only.out"

cat > "$fixture/tests/run-js.mjs" <<'JS'
import assert from 'node:assert/strict';
await import('../js/behavior.js');
assert.equal(globalThis.syntheticBehavior, 42);
JS
bash "$runtime_checker" --repo "$fixture" --base HEAD

# Eine ausgeführte Datei darf eine zweite, vollständig ungetestete Datei nicht verdecken.
printf 'globalThis.otherBehavior = 7;\n' > "$fixture/js/uncovered.js"
if bash "$runtime_checker" --repo "$fixture" --base HEAD > "$temporary/uncovered-file.out" 2>&1; then
    echo 'Ausgeführte Datei verdeckt eine neue ungetestete JS-Datei.' >&2
    exit 1
fi
grep -Fq 'Kein JS-Laufzeitnachweis: js/uncovered.js' "$temporary/uncovered-file.out"

git -C "$fixture" add js tests
git -C "$fixture" -c user.name=Fixture -c user.email=fixture@example.invalid commit -qm behavior
bash "$runtime_checker" --repo "$fixture" --base HEAD
if CI=true bash "$runtime_checker" --repo "$fixture" > "$temporary/ci-base.out" 2>&1; then
    echo 'Sauberer CI-Checkout wurde ohne explizite Vergleichsbasis akzeptiert.' >&2
    exit 1
fi
grep -Fq 'CI benötigt --repo und eine explizite --base' "$temporary/ci-base.out"
if bash "$runtime_checker" --repo "$fixture" --base HEAD^ > "$temporary/committed.out" 2>&1; then
    echo 'Explizite Git-Basis übergeht bereits committete ungetestete JS-Datei.' >&2
    exit 1
fi
grep -Fq 'Kein JS-Laufzeitnachweis: js/uncovered.js' "$temporary/committed.out"
if CI=true JS_RUNTIME_COVERAGE_REPO="$fixture" JS_RUNTIME_COVERAGE_BASE=HEAD^ \
    bash "$runtime_checker" > "$temporary/ci-committed.out" 2>&1; then
    echo 'CI-Übergabe übergeht committetes ungetestetes JS.' >&2
    exit 1
fi
grep -Fq 'Kein JS-Laufzeitnachweis: js/uncovered.js' "$temporary/ci-committed.out"

printf "throw new Error('synthetic failing behavior test');\n" > "$fixture/tests/run-js.mjs"
printf 'globalThis.otherBehavior = 8;\n' > "$fixture/js/uncovered.js"
if bash "$runtime_checker" --repo "$fixture" --base HEAD > "$temporary/failing-test.out" 2>&1; then
    echo 'Fehlgeschlagener Verhaltenstest wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'synthetic failing behavior test' "$temporary/failing-test.out"

printf 'globalThis.vmBehavior = 9;\n' > "$fixture/js/vm-behavior.js"
cat > "$fixture/tests/run-js.mjs" <<'JS'
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import vm from 'node:vm';
await import('../js/uncovered.js');
const context = vm.createContext({});
vm.runInContext(readFileSync('js/vm-behavior.js', 'utf8'), context, { filename: resolve('js/vm-behavior.js') });
assert.equal(context.vmBehavior, 9);
JS
bash "$runtime_checker" --repo "$fixture" --base HEAD
CI=true JS_RUNTIME_COVERAGE_REPO="$fixture" JS_RUNTIME_COVERAGE_BASE=HEAD bash "$runtime_checker"

printf 'globalThis.victimBehavior = 12;\n' > "$fixture/js/victim.js"
cat >> "$fixture/tests/run-js.mjs" <<'JS'
vm.runInContext('// unrelated placeholder', context, { filename: 'js/victim.js' });
JS
if bash "$runtime_checker" --repo "$fixture" --base HEAD > "$temporary/forged-vm.out" 2>&1; then
    echo 'Fremder VM-Inhalt unter einem Produktivdateinamen wurde als Nachweis akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Kein JS-Laufzeitnachweis: js/victim.js' "$temporary/forged-vm.out"
rm "$fixture/js/victim.js"

printf 'globalThis.stagedBehavior = 11;\n' > "$fixture/js/staged.js"
git -C "$fixture" add js/staged.js
rm "$fixture/js/staged.js"
if bash "$runtime_checker" --repo "$fixture" --base HEAD > "$temporary/staged-deleted.out" 2>&1; then
    echo 'Gestagtes JS wurde durch ungestagte Löschung dem Laufzeitcheck entzogen.' >&2
    exit 1
fi
grep -Fq 'Index und Worktree weichen für gestagtes JS ab: js/staged.js' "$temporary/staged-deleted.out"

if ! grep -Fq 'bash scripts/check-js-runtime-coverage' "$workspace/scripts/check-fast"; then
    echo 'JS-Laufzeitnachweis fehlt im Parent-Schnellcheck.' >&2
    exit 1
fi

echo 'JavaScript-Coverage-Baseline-Test: OK'
