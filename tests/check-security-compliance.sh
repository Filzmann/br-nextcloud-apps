#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
checker="$workspace/scripts/check-security-compliance"
temporary="$(mktemp -d)"

cleanup() {
    rm -rf "$temporary"
}
trap cleanup EXIT

[[ -x "$checker" ]] || {
    echo 'Ausführbarer Security-Compliance-Check fehlt.' >&2
    exit 1
}

"$checker" | grep -Fqx 'Security-Compliance-Konsistenz: OK'

workspace_impact="$($checker --workspace-diff)"
grep -Fqx 'Security-Impact-Auswertung: tatsächliche App-Worktree-Diffs' <<< "$workspace_impact"
grep -Fqx 'Security-Compliance-Konsistenz: OK' <<< "$workspace_impact"
grep -Fq 'scripts/check-security-compliance --workspace-diff' "$workspace/scripts/check-apps" || {
    echo 'Der reale App-Harnesspfad führt keine Security-Impact-Auswertung aus.' >&2
    exit 1
}

cp "$workspace/security-compliance/bsi-mapping.json" "$temporary/bsi-mapping.json"
sed -i '0,/tests\/permission-provider-v1-contract.php/s//tests\/missing-security-proof.php/' "$temporary/bsi-mapping.json"
if "$checker" --mapping "$temporary/bsi-mapping.json" >"$temporary/missing.out" 2>&1; then
    echo 'Eine fehlende Nachweisreferenz wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Referenz existiert nicht: tests/missing-security-proof.php' "$temporary/missing.out"

cp "$workspace/security-compliance/scope.json" "$temporary/scope.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    array_pop($data["applications"]);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/scope.json"
if "$checker" --scope "$temporary/scope.json" >"$temporary/scope.out" 2>&1; then
    echo 'Ein unvollständiger Security-Scope wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Scope und Repositoryinventar weichen voneinander ab.' "$temporary/scope.out"

cp "$workspace/security-compliance/scope.json" "$temporary/duplicate-scope.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["product_sets"] = [["id" => "duplicate", "members" => ["adcalendar"]]];
    $data["applications"][0]["runtime_dependencies"] = ["nextcloud"];
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/duplicate-scope.json"
if "$checker" --scope "$temporary/duplicate-scope.json" >"$temporary/duplicate-scope.out" 2>&1; then
    echo 'Doppelte Produkt-/Dependency-Wahrheiten im Security-Scope wurden akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Produktmengen und Runtime-Abhängigkeiten müssen aus ihren kanonischen Quellen abgeleitet werden.' "$temporary/duplicate-scope.out"

cp "$workspace/security-compliance/vulnerability-exceptions.json" "$temporary/exceptions.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][] = [
        "id" => "TEST-OVERDUE",
        "scanner" => "osv-scanner",
        "repository" => "localbase",
        "path" => "tests/coverage/package-lock.json",
        "fingerprint" => str_repeat("a", 64),
        "vulnerability" => "CVE-2099-0001",
        "severity" => "high",
        "component" => "synthetic-component",
        "dependency_relation" => "tooling",
        "fix_available" => false,
        "reason" => "synthetic contract fixture",
        "compensating_controls" => ["fixture-only"],
        "review_on" => "2020-01-01",
        "expires_on" => "2099-01-01"
    ];
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/exceptions.json"
if "$checker" --exceptions "$temporary/exceptions.json" >"$temporary/exceptions.out" 2>&1; then
    echo 'Eine überfällige Vulnerability-Ausnahme wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Überfällige Vulnerability-Ausnahme: TEST-OVERDUE' "$temporary/exceptions.out"

cp "$workspace/security-compliance/vulnerability-exceptions.json" "$temporary/null-field-exceptions.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][] = [
        "id" => "TEST-NULL-FIELD",
        "scanner" => "osv-scanner",
        "repository" => "localbase",
        "path" => "tests/coverage/package-lock.json",
        "fingerprint" => str_repeat("b", 64),
        "vulnerability" => "CVE-2099-0002",
        "severity" => "high",
        "component" => "synthetic-component",
        "dependency_relation" => "direct",
        "fix_available" => false,
        "reason" => null,
        "compensating_controls" => ["fixture-only"],
        "review_on" => "2099-01-01",
        "expires_on" => "2099-12-31"
    ];
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/null-field-exceptions.json"
if "$checker" --exceptions "$temporary/null-field-exceptions.json" >"$temporary/null-field.out" 2>&1; then
    echo 'Ein null-Wert in einem String-Pflichtfeld wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Vulnerability-Ausnahme mit ungültigem Stringfeld: reason' "$temporary/null-field.out"

cp "$temporary/null-field-exceptions.json" "$temporary/non-string-exceptions.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][0]["reason"] = "synthetic fixture";
    $data["exceptions"][0]["severity"] = 7;
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/non-string-exceptions.json"
if "$checker" --exceptions "$temporary/non-string-exceptions.json" >"$temporary/non-string.out" 2>&1; then
    echo 'Ein numerisches String-Pflichtfeld wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Vulnerability-Ausnahme mit ungültigem Stringfeld: severity' "$temporary/non-string.out"

cp "$temporary/non-string-exceptions.json" "$temporary/relation-exceptions.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][0]["severity"] = "high";
    $data["exceptions"][0]["dependency_relation"] = "undeclared-runtime";
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/relation-exceptions.json"
if "$checker" --exceptions "$temporary/relation-exceptions.json" >"$temporary/relation.out" 2>&1; then
    echo 'Eine nicht erlaubte Dependency-Beziehung wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Unzulässige Dependency-Beziehung: undeclared-runtime' "$temporary/relation.out"

cp "$temporary/relation-exceptions.json" "$temporary/date-exceptions.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][0]["dependency_relation"] = "direct";
    $data["exceptions"][0]["review_on"] = "2099-02-30";
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/date-exceptions.json"
if "$checker" --exceptions "$temporary/date-exceptions.json" >"$temporary/date.out" 2>&1; then
    echo 'Ein nicht kanonisches oder ungültiges Kalenderdatum wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Nicht kanonisches Ausnahmedatum review_on: 2099-02-30' "$temporary/date.out"

cp "$temporary/relation-exceptions.json" "$temporary/date-shape-exceptions.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][0]["dependency_relation"] = "direct";
    $data["exceptions"][0]["review_on"] = "2099-2-03";
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/date-shape-exceptions.json"
if "$checker" --exceptions "$temporary/date-shape-exceptions.json" >"$temporary/date-shape.out" 2>&1; then
    echo 'Ein nicht exakt als YYYY-MM-DD formatiertes Datum wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Nicht kanonisches Ausnahmedatum review_on: 2099-2-03' "$temporary/date-shape.out"

cp "$workspace/security-compliance/analysis-exceptions.json" "$temporary/analysis-exceptions.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][] = [
        "id" => "TEST-GITLEAKS-RISK-ACCEPTANCE",
        "scanner" => "gitleaks",
        "repository" => "parent",
        "path" => "README.md",
        "finding_id" => "synthetic-secret",
        "content_hash" => str_repeat("d", 64),
        "fingerprint" => str_repeat("c", 64),
        "classification" => "risk-accepted",
        "reason" => "synthetic contract fixture",
        "compensating_controls" => ["fixture-only"],
        "review_on" => "2099-01-01",
        "expires_on" => "2099-12-31"
    ];
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/analysis-exceptions.json"
if "$checker" --analysis-exceptions "$temporary/analysis-exceptions.json" >"$temporary/analysis.out" 2>&1; then
    echo 'Ein mutmaßliches Secret wurde als allgemeines Restrisiko akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Unzulässige Analysis-Klassifikation: TEST-GITLEAKS-RISK-ACCEPTANCE' "$temporary/analysis.out"

cp "$workspace/security-compliance/scanner-tools.json" "$temporary/scanner-tools.json"
php -r '
    $path = $argv[1];
    $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $data["tools"]["gitleaks"]["platforms"]["linux-amd64"]["sha256"] = str_repeat("0", 63);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/scanner-tools.json"
if "$checker" --scanner-tools "$temporary/scanner-tools.json" >"$temporary/scanner-tools.out" 2>&1; then
    echo 'Ein ungültiger Scanner-Checksum-Pin wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Ungültiger Toolpin: gitleaks/linux-amd64' "$temporary/scanner-tools.out"

impact="$($checker \
    --changed-file adcalendar/appinfo/routes.php \
    --changed-file adcalendar/lib/Service/CalendarAccessService.php \
    --changed-file adcalendar/lib/Migration/Version999Date.php \
    --changed-file localbase/tests/coverage/composer.lock)"
grep -Fq 'Review-Kontext: adcalendar/appinfo/routes.php -> api-contract' <<< "$impact" || {
    echo 'Der Security-Impact nennt keinen konkreten App-/Dateikontext.' >&2
    exit 1
}
for expected in \
    'authorization-negative-tests' \
    'api-contract' \
    'migration-reinstall' \
    'dependency-sbom' \
    'security-mapping' \
    'threat-model'; do
    grep -Fq "Review-Trigger: $expected" <<< "$impact" || {
        echo "Kontexttrigger fehlt: $expected" >&2
        exit 1
    }
done

neutral="$($checker --changed-file adcalendar/docs/manual-acceptance.md)"
grep -Fqx 'Security-Impact: keine kontextuelle Prüfung ausgelöst' <<< "$neutral"

echo 'Security-Compliance-Vertrag: OK'
