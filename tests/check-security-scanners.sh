#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
runner="$workspace/scripts/run-security-scanners"
installer="$workspace/scripts/install-security-scanners"
evaluator="$workspace/scripts/evaluate-security-scan.php"
inventory_inspector="$workspace/scripts/inspect-third-party-inventory.php"
tools_manifest="$workspace/security-compliance/scanner-tools.json"
analysis_exceptions="$workspace/security-compliance/analysis-exceptions.json"
semgrep_rules="$workspace/security-compliance/semgrep-rules.yml"
gitleaks_config="$workspace/security-compliance/gitleaks.toml"
gitleaks_ignore="$workspace/security-compliance/gitleaks-ignore.txt"
osv_config="$workspace/security-compliance/osv-scanner.toml"
temporary="$(mktemp -d)"

cleanup() {
    rm -rf "$temporary"
}
trap cleanup EXIT

for required in "$runner" "$installer" "$evaluator" "$inventory_inspector" "$tools_manifest" "$analysis_exceptions" "$semgrep_rules" \
    "$gitleaks_config" "$gitleaks_ignore" "$osv_config"; do
    [[ -f "$required" ]] || {
        echo "Security-Scanner-Vertragsdatei fehlt: ${required#"$workspace/"}" >&2
        exit 1
    }
done

php -r '
    $manifest = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $expected = [
        "gitleaks" => ["version" => "8.30.1", "linux-amd64" => "551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb", "linux-arm64" => "e4a487ee7ccd7d3a7f7ec08657610aa3606637dab924210b3aee62570fb4b080"],
        "osv-scanner" => ["version" => "2.4.0", "linux-amd64" => "15314940c10d26af9c6649f150b8a47c1262e8fc7e17b1d1029b0e479e8ed8a0", "linux-arm64" => "44e580752910f0ff36ec99aff59af20f65df1e859aa31e5605a8f0d055b496e9"],
    ];
    foreach ($expected as $tool => $contract) {
        if (($manifest["tools"][$tool]["version"] ?? null) !== $contract["version"]) throw new RuntimeException("Toolversion fehlt: {$tool}");
        foreach (["linux-amd64", "linux-arm64"] as $platform) {
            if (($manifest["tools"][$tool]["platforms"][$platform]["sha256"] ?? null) !== $contract[$platform]) throw new RuntimeException("Toolpin fehlt: {$tool}/{$platform}");
        }
    }
    if (($manifest["tools"]["semgrep"]["version"] ?? null) !== "1.176.0") throw new RuntimeException("Semgrep-Version fehlt");
    if (($manifest["tools"]["semgrep"]["image_digest"] ?? null) !== "sha256:e5ea1a270ca5557a114ae7a30a8f860cc16a924f8df85cd975688f17c7c97731") throw new RuntimeException("Semgrep-Digest fehlt");
' "$tools_manifest"

mkdir -p "$temporary/bin"
cat > "$temporary/bin/gitleaks" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
report=''
source=''
config=''
ignore_path=''
ignore_allow=0
while (( $# )); do
    case "$1" in
        --report-path) report="$2"; shift 2 ;;
        --report-path=*) report="${1#*=}"; shift ;;
        --config) config="$2"; shift 2 ;;
        --gitleaks-ignore-path) ignore_path="$2"; shift 2 ;;
        --ignore-gitleaks-allow) ignore_allow=1; shift ;;
        dir) shift ;;
        --*) shift ;;
        *) source="$1"; shift ;;
    esac
done
[[ "$config" == */security-compliance/gitleaks.toml ]] || exit 91
[[ "$ignore_path" == */security-compliance/gitleaks-ignore.txt ]] || exit 92
(( ignore_allow == 1 )) || exit 93
[[ -z "${GITLEAKS_CONFIG:-}${GITLEAKS_CONFIG_TOML:-}" ]] || exit 94
if [[ "${MOCK_GITLEAKS_ERROR:-0}" == '1' ]]; then
    exit 2
fi
if [[ "${MOCK_GITLEAKS_FINDING:-0}" == '1' && "$source" == *'/adcalendar' ]]; then
    printf '%s\n' '[{"RuleID":"generic-api-key","File":"appinfo/info.xml","StartLine":7,"Secret":"SYNTHETIC_SHOULD_NEVER_ESCAPE"}]' > "$report"
else
    printf '%s\n' '[]' > "$report"
fi
MOCK
cat > "$temporary/bin/semgrep" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
source="${*: -1}"
[[ " $* " == *' --disable-nosem '* ]] || exit 91
[[ " $* " == *' --no-git-ignore '* ]] || exit 92
[[ " $* " == *' --x-ignore-semgrepignore-files '* ]] || exit 93
[[ " $* " == *' --config '* ]] || exit 94
[[ -z "${SEMGREP_RULES:-}${SEMGREP_BASELINE_COMMIT:-}" ]] || exit 95
if [[ "${MOCK_SEMGREP_ERROR:-0}" == '1' ]]; then
    exit 2
fi
finding='[]'
sentinel="$(find "$source" -path '*/js/vendor/*' -type f -exec grep -l 'THIRD_PARTY_SEMGREP_SENTINEL' {} \; | head -n 1)"
if [[ -n "$sentinel" ]]; then
    relative="${sentinel#"$source"/}"
    finding="[{\"check_id\":\"filzmann.third-party-entered-first-party-sast\",\"path\":\"$relative\",\"start\":{\"line\":1},\"end\":{\"line\":1},\"extra\":{\"severity\":\"ERROR\"}}]"
fi
if [[ "${MOCK_SEMGREP_FINDING:-0}" == '1' && "$source" == *'/adcalendar' ]]; then
    finding='[{"check_id":"filzmann.php.eval","path":"appinfo/info.xml","start":{"line":7},"end":{"line":7},"extra":{"severity":"ERROR","lines":"synthetic"}}]'
fi
issues='[]'
if [[ "${MOCK_SEMGREP_WARNING:-0}" == '1' && "$source" == *'/adcalendar' ]]; then
    issues='[{"code":3,"level":"warn","type":"PartialParsing","message":"synthetic partial parsing warning","path":"appinfo/info.xml"}]'
elif [[ "${MOCK_SEMGREP_REPORT_ERROR:-0}" == '1' && "$source" == *'/adcalendar' ]]; then
    issues='[{"code":3,"level":"error","type":"ParseError","message":"synthetic parser error","path":"appinfo/info.xml"}]'
fi
printf '{"results":%s,"errors":%s}\n' "$finding" "$issues"
MOCK
cat > "$temporary/bin/osv-scanner" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
output=''
lockfile=''
sbom=''
config=''
while (( $# )); do
    case "$1" in
        --output-file) output="$2"; shift 2 ;;
        --lockfile) lockfile="$2"; shift 2 ;;
        --sbom) sbom="$2"; shift 2 ;;
        --config) config="$2"; shift 2 ;;
        *) shift ;;
    esac
done
[[ "$config" == */security-compliance/osv-scanner.toml ]] || exit 91
if [[ -n "$sbom" ]]; then
    grep -Fq '"purl"' "$sbom" || exit 92
    lockfile="$sbom"
fi
if [[ "${MOCK_OSV_ERROR:-0}" == '1' ]]; then
    exit 128
fi
if [[ "${MOCK_OSV_MALFORMED:-0}" == '1' ]]; then
    printf '%s\n' '{}' > "$output"
elif [[ "${MOCK_OSV_PURL_FINDING:-0}" == '1' && -n "$sbom" ]]; then
    cat > "$output" <<JSON
{
  "results": [{
    "source": {"path": "$sbom", "type": "sbom"},
    "packages": [{
      "package": {"name": "synthetic-library", "version": "1.2.3", "ecosystem": "npm", "purl": "pkg:npm/synthetic-library@1.2.3"},
      "dependency_groups": ["runtime"],
      "groups": [{"ids": ["GHSA-1111-2222-3333"], "max_severity": "8.0"}],
      "vulnerabilities": [{"id": "GHSA-1111-2222-3333", "database_specific": {"severity": "HIGH"}, "affected": []}]
    }]
  }]
}
JSON
elif [[ "${MOCK_OSV_FINDING:-0}" == '1' && "$lockfile" == *'package-lock.json' ]]; then
    cat > "$output" <<JSON
{
  "results": [
    {
      "source": {"path": "$lockfile", "type": "lockfile"},
      "packages": [
        {
          "package": {"name": "synthetic-package", "version": "1.0.0", "ecosystem": "npm"},
          "dependency_groups": ["dev"],
          "groups": [{"ids": ["GHSA-0000-0000-0000"], "max_severity": "7.5"}],
          "vulnerabilities": [
            {
              "id": "GHSA-0000-0000-0000",
              "database_specific": {"severity": "HIGH"},
              "affected": [{"ranges": [{"events": [{"introduced": "0"}, {"fixed": "1.0.1"}]}]}]
            }
          ]
        }
      ]
    }
  ]
}
JSON
else
    printf '%s\n' '{"results":[]}' > "$output"
fi
MOCK
chmod 700 "$temporary/bin/gitleaks" "$temporary/bin/semgrep" "$temporary/bin/osv-scanner"

run_scanners() {
    HOME="$temporary/hostile-home" \
        GITLEAKS_CONFIG="$temporary/hostile-gitleaks.toml" \
        GITLEAKS_CONFIG_TOML='title = "hostile"' \
        SEMGREP_RULES="$temporary/hostile-semgrep.yml" \
        SEMGREP_BASELINE_COMMIT=HEAD~1 \
        MOCK_OSV_MALFORMED="${MOCK_OSV_MALFORMED:-0}" \
        MOCK_OSV_PURL_FINDING="${MOCK_OSV_PURL_FINDING:-0}" \
        SECURITY_SCANNER_TEST_MODE=1 SECURITY_SCANNER_TEST_BIN_DIR="$temporary/bin" \
        "$runner" --evidence-file "$1" "${@:2}"
}

mkdir -p "$temporary/repository-id-probe"
printf '%s\n' '<?php' > "$temporary/repository-id-probe/probe.php"
git -C "$temporary/repository-id-probe" init -q
git -C "$temporary/repository-id-probe" add probe.php
invalid_repository_ids=('../escape' 'nested/id' 'nested\id' $'line\nbreak')
for index in "${!invalid_repository_ids[@]}"; do
    invalid_id="${invalid_repository_ids[$index]}"
    invalid_evidence="$temporary/invalid-repository-id-$index.json"
    if run_scanners "$invalid_evidence" \
        --repository "$invalid_id=$temporary/repository-id-probe" \
        >"$temporary/invalid-repository-id-$index.out" 2>&1; then
        echo 'Eine unsichere Scanner-Repository-ID wurde akzeptiert.' >&2
        exit 1
    fi
    grep -Fq 'Ungültige Scanner-Repository-ID.' "$temporary/invalid-repository-id-$index.out"
    if [[ -e "$invalid_evidence" || -L "$invalid_evidence" ]]; then
        echo 'Eine abgelehnte Scanner-Repository-ID erzeugte Evidence.' >&2
        exit 1
    fi
done

run_scanners "$temporary/passed.json"
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if ($data["status"] !== "diagnostic") throw new RuntimeException("Testmodus ist nicht diagnostisch");
    if (($data["execution"]["mode"] ?? null) !== "diagnostic" || ($data["execution"]["test_doubles"] ?? null) !== true || ($data["execution"]["publishable"] ?? null) !== false) throw new RuntimeException("Testmodus kann als Release-Nachweis erscheinen");
    if (count($data["scope"]["repositories"]) !== 13) throw new RuntimeException("Parent plus zwölf Apps wurden nicht vollständig gescannt");
    foreach (["gitleaks", "semgrep", "osv-scanner"] as $scanner) {
        if (($data["scanners"][$scanner]["status"] ?? null) !== "passed") throw new RuntimeException("Scannerstatus fehlt: {$scanner}");
        if (($data["scanners"][$scanner]["scanned_repositories"] ?? null) !== 13) throw new RuntimeException("Scanner-Scope ist unvollständig: {$scanner}");
    }
    $thirdParty = $data["scope"]["third_party_components"] ?? [];
    if (count($thirdParty) !== 1 || ($thirdParty[0]["repository"] ?? null) !== "adrecruitment" || ($thirdParty[0]["purl"] ?? null) !== "pkg:npm/pdfjs-dist@6.2.108") throw new RuntimeException("App-lokales Third-Party-Inventar fehlt im Scanner-Scope");
    if (($data["scanners"]["osv-scanner"]["scanned_inventories"] ?? null) !== 1) throw new RuntimeException("PURL-Inventar wurde nicht per OSV geprüft");
' "$temporary/passed.json"

create_inventory_repository() {
    local repository="$1"
    local owner="$2"
    local bundled_root="${3:-js/vendor/synthetic-library}"
    mkdir -p "$repository/js/vendor/synthetic-library" "$repository/resources"
    printf '%s\n' 'THIRD_PARTY_SEMGREP_SENTINEL' > "$repository/js/vendor/synthetic-library/library.js"
    local tree_hash
    tree_hash="$(php -r '$root=$argv[1]; $files=[]; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)); foreach($it as $file){if($file->isFile())$files[]=substr($file->getPathname(),strlen($root)+1);} sort($files,SORT_STRING); $hash=hash_init("sha256"); foreach($files as $file){hash_update($hash,$file."\0"); hash_update_file($hash,$root."/".$file);} echo hash_final($hash);' "$repository/js/vendor/synthetic-library")"
    local file_hash
    file_hash="$(sha256sum "$repository/js/vendor/synthetic-library/library.js" | cut -d' ' -f1)"
    php -r '
        $inventory = [
            "bomFormat" => "CycloneDX", "specVersion" => "1.6", "version" => 1,
            "metadata" => ["properties" => [["name" => "filzmann:inventory-owner", "value" => $argv[2]]]],
            "components" => [[
                "type" => "library", "bom-ref" => "pkg:npm/synthetic-library@1.2.3",
                "name" => "synthetic-library", "version" => "1.2.3", "scope" => "required",
                "purl" => "pkg:npm/synthetic-library@1.2.3",
                "properties" => [
                    ["name" => "filzmann:bundled-root", "value" => $argv[3]],
                    ["name" => "filzmann:bundled-tree-sha256", "value" => $argv[4]],
                    ["name" => "filzmann:bundled-file-count", "value" => "1"],
                    ["name" => "filzmann:runtime-scope", "value" => "app-local-browser"],
                    ["name" => "filzmann:dependency-scan", "value" => "osv-by-purl"],
                    ["name" => "filzmann:sast-treatment", "value" => "pinned-third-party-source"],
                    ["name" => "filzmann:license-path", "value" => "js/vendor/synthetic-library/library.js"]
                ],
                "components" => [[
                    "type" => "file", "bom-ref" => "file:js/vendor/synthetic-library/library.js",
                    "name" => "js/vendor/synthetic-library/library.js", "scope" => "required",
                    "hashes" => [["alg" => "SHA-256", "content" => $argv[5]]]
                ]]
            ]]
        ];
        file_put_contents($argv[1], json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    ' "$repository/resources/third-party-components.cdx.json" "$owner" "$bundled_root" "$tree_hash" "$file_hash"
    git -C "$repository" init -q
    git -C "$repository" add .
}

create_inventory_repository "$temporary/valid-inventory" validapp
run_scanners "$temporary/valid-inventory.json" --repository "validapp=$temporary/valid-inventory" >/dev/null
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (($data["scanners"]["semgrep"]["findings"] ?? null) !== 0) throw new RuntimeException("Validierter Third-Party-Baum wurde als First-Party-Code gescannt");
    if (($data["scanners"]["osv-scanner"]["scanned_inventories"] ?? null) !== 1) throw new RuntimeException("Validiertes PURL-Inventar wurde nicht geprüft");
' "$temporary/valid-inventory.json"

if MOCK_OSV_MALFORMED=1 run_scanners "$temporary/malformed-osv.json" \
    --repository "validapp=$temporary/valid-inventory" >"$temporary/malformed-osv.out" 2>&1; then
    echo 'Ein strukturell unvollständiger OSV-Bericht wurde als sauber bewertet.' >&2
    exit 1
fi
grep -Fq 'Osv-scanner konnte nicht vollständig ausgeführt werden.' "$temporary/malformed-osv.out"
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $error = array_values(array_filter($data["errors"] ?? [], static fn (array $entry): bool => ($entry["scanner"] ?? null) === "osv-scanner"))[0] ?? null;
    if (($data["status"] ?? null) !== "failed" || ($data["scanners"]["osv-scanner"]["status"] ?? null) !== "error") throw new RuntimeException("Unvollständiger OSV-Bericht bleibt nicht fail-closed");
    if (($error["type"] ?? null) !== "InvalidOrIncompleteReport" || ($error["reason"] ?? null) !== "invalid-or-incomplete-report") throw new RuntimeException("Unvollständiger OSV-Bericht ist nicht als Auswertungsfehler typisiert");
' "$temporary/malformed-osv.json"

if MOCK_OSV_PURL_FINDING=1 run_scanners "$temporary/purl-finding.json" \
    --repository "validapp=$temporary/valid-inventory" >/dev/null 2>&1; then
    echo 'Ein PURL-basierter OSV-Fund wurde zugelassen.' >&2
    exit 1
fi
php -r '
    $finding = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["findings"][0] ?? null;
    if (($finding["component"] ?? null) !== "pkg:npm/synthetic-library@1.2.3" || ($finding["dependency_relation"] ?? null) !== "direct") throw new RuntimeException("PURL-basierter OSV-Fund ist nicht an die direkte gebündelte Dependency gebunden");
' "$temporary/purl-finding.json"

cp -a "$temporary/valid-inventory" "$temporary/tampered-inventory"
printf '%s\n' 'tampered' >> "$temporary/tampered-inventory/js/vendor/synthetic-library/library.js"
if run_scanners "$temporary/tampered.json" --repository "validapp=$temporary/tampered-inventory" >"$temporary/tampered.out" 2>&1; then
    echo 'Ein manipulierter Third-Party-Dateibaum wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Third-Party-Baumhash stimmt nicht' "$temporary/tampered.out"

create_inventory_repository "$temporary/traversal-inventory" validapp '../outside'
if run_scanners "$temporary/traversal.json" --repository "validapp=$temporary/traversal-inventory" >"$temporary/traversal.out" 2>&1; then
    echo 'Ein Traversal-Pfad im Third-Party-Inventar wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Ungültiger app-relativer Third-Party-Pfad' "$temporary/traversal.out"

create_inventory_repository "$temporary/owner-mismatch" foreignapp
if run_scanners "$temporary/owner-mismatch.json" --repository "validapp=$temporary/owner-mismatch" >"$temporary/owner-mismatch.out" 2>&1; then
    echo 'Ein fremdes Third-Party-Inventar wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Third-Party-Inventar-Owner stimmt nicht' "$temporary/owner-mismatch.out"

create_inventory_repository "$temporary/missing-metadata" validapp
php -r '$data=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR); $data["components"][0]["properties"]=array_values(array_filter($data["components"][0]["properties"],static fn(array $property):bool=>$property["name"]!=="filzmann:bundled-tree-sha256")); file_put_contents($argv[1],json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");' "$temporary/missing-metadata/resources/third-party-components.cdx.json"
if run_scanners "$temporary/missing-metadata.json" --repository "validapp=$temporary/missing-metadata" >"$temporary/missing-metadata.out" 2>&1; then
    echo 'Fehlende Integritätsmetadaten wurden akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Third-Party-Pflichteigenschaft fehlt' "$temporary/missing-metadata.out"

if MOCK_GITLEAKS_FINDING=1 run_scanners "$temporary/gitleaks-failed.json" >"$temporary/gitleaks.out" 2>&1; then
    echo 'Ein nicht akzeptierter Secret-Fund wurde zugelassen.' >&2
    exit 1
fi
if rg -q 'SYNTHETIC_SHOULD_NEVER_ESCAPE' "$temporary/gitleaks-failed.json" "$temporary/gitleaks.out"; then
    echo 'Secret-Inhalt ist in Scanner-Evidence oder Log gelangt.' >&2
    exit 1
fi
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $finding = $data["findings"][0] ?? null;
    if ($data["status"] !== "failed" || $finding["scanner"] !== "gitleaks") throw new RuntimeException("Secret-Fund fehlt");
    if (isset($finding["secret"], $finding["snippet"])) throw new RuntimeException("Secret-Daten wurden persistiert");
    if (!preg_match("/^[a-f0-9]{64}$/", $finding["content_hash"] ?? "")) throw new RuntimeException("Fundinhalt ist nicht gehasht gebunden");
    if (!preg_match("/^[a-f0-9]{64}$/", $finding["fingerprint"])) throw new RuntimeException("Stabiler Finding-Fingerprint fehlt");
' "$temporary/gitleaks-failed.json"

php -r '
    $finding = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["findings"][0];
    $data = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][] = [
        "id" => "TEST-GITLEAKS-FALSE-POSITIVE",
        "scanner" => "gitleaks",
        "repository" => $finding["repository"],
        "path" => $finding["path"],
        "finding_id" => $finding["finding_id"],
        "content_hash" => $finding["content_hash"],
        "fingerprint" => $finding["fingerprint"],
        "classification" => "false-positive",
        "reason" => "synthetic contract fixture",
        "compensating_controls" => ["fixture-only"],
        "review_on" => "2099-01-01",
        "expires_on" => "2099-12-31"
    ];
    file_put_contents($argv[3], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/gitleaks-failed.json" "$analysis_exceptions" "$temporary/analysis-accepted.json"
MOCK_GITLEAKS_FINDING=1 run_scanners "$temporary/gitleaks-accepted.json" \
    --analysis-exceptions "$temporary/analysis-accepted.json" >/dev/null

mkdir -p "$temporary/fingerprint-repository/appinfo"
printf '%s\n' one two three four five six 'first synthetic content' \
    > "$temporary/fingerprint-repository/appinfo/info.xml"
git -C "$temporary/fingerprint-repository" init -q
git -C "$temporary/fingerprint-repository" add appinfo/info.xml
MOCK_GITLEAKS_FINDING=1 run_scanners "$temporary/fingerprint-first.json" \
    --repository "adcalendar=$temporary/fingerprint-repository" >/dev/null 2>&1 || true
php -r '
    $finding = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["findings"][0];
    $data = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][] = [
        "id" => "TEST-CONTENT-BOUND-FINGERPRINT",
        "scanner" => "gitleaks",
        "repository" => $finding["repository"],
        "path" => $finding["path"],
        "finding_id" => $finding["finding_id"],
        "content_hash" => $finding["content_hash"],
        "fingerprint" => $finding["fingerprint"],
        "classification" => "false-positive",
        "reason" => "synthetic contract fixture",
        "compensating_controls" => ["fixture-only"],
        "review_on" => "2099-01-01",
        "expires_on" => "2099-12-31"
    ];
    file_put_contents($argv[3], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/fingerprint-first.json" "$analysis_exceptions" "$temporary/fingerprint-exception.json"
MOCK_GITLEAKS_FINDING=1 run_scanners "$temporary/fingerprint-accepted.json" \
    --repository "adcalendar=$temporary/fingerprint-repository" \
    --analysis-exceptions "$temporary/fingerprint-exception.json" >/dev/null
printf '%s\n' one two three four five six 'changed synthetic content' \
    > "$temporary/fingerprint-repository/appinfo/info.xml"
if MOCK_GITLEAKS_FINDING=1 run_scanners "$temporary/fingerprint-changed.json" \
    --repository "adcalendar=$temporary/fingerprint-repository" \
    --analysis-exceptions "$temporary/fingerprint-exception.json" >/dev/null 2>&1; then
    echo 'Eine Ausnahme blieb nach geändertem Fundinhalt wirksam.' >&2
    exit 1
fi
php -r '
    $before = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["findings"][0];
    $after = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR)["findings"][0];
    if ($before["content_hash"] === $after["content_hash"] || $before["fingerprint"] === $after["fingerprint"]) throw new RuntimeException("Geänderter Fundinhalt ändert die Ausnahmebindung nicht");
' "$temporary/fingerprint-first.json" "$temporary/fingerprint-changed.json"

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][0]["classification"] = "risk-accepted";
    file_put_contents($argv[2], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/analysis-accepted.json" "$temporary/gitleaks-risk-accepted.json"
if MOCK_GITLEAKS_FINDING=1 run_scanners "$temporary/gitleaks-risk-accepted-evidence.json" \
    --repository "adcalendar=$workspace/adcalendar" \
    --analysis-exceptions "$temporary/gitleaks-risk-accepted.json" >/dev/null 2>&1; then
    echo 'Ein tatsächlicher Gitleaks-Fund wurde im Teil-Scope als Risiko akzeptiert.' >&2
    exit 1
fi

if MOCK_SEMGREP_FINDING=1 run_scanners "$temporary/semgrep-failed.json" >/dev/null 2>&1; then
    echo 'Ein nicht akzeptierter SAST-Fund wurde zugelassen.' >&2
    exit 1
fi
if MOCK_SEMGREP_WARNING=1 MOCK_SEMGREP_FINDING=1 \
    run_scanners "$temporary/semgrep-warning-finding.json" >"$temporary/semgrep-warning-finding.out" 2>&1; then
    echo 'Eine partielle Semgrep-Analyse mit Fund wurde zugelassen.' >&2
    exit 1
fi
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $semgrepFindings = array_values(array_filter($data["findings"], static fn (array $finding): bool => $finding["scanner"] === "semgrep"));
    if (($data["status"] ?? null) !== "failed" || ($data["scanners"]["semgrep"]["status"] ?? null) !== "failed") throw new RuntimeException("Semgrep-Warnung mit Fund blockiert nicht");
    if (($data["scanners"]["semgrep"]["warnings"] ?? null) !== 1 || count($data["warnings"] ?? []) !== 1) throw new RuntimeException("Semgrep-Warnung fehlt in der Evidence");
    if (count($semgrepFindings) !== 1 || $semgrepFindings[0]["finding_id"] !== "filzmann.php.eval") throw new RuntimeException("Semgrep-Fund wurde wegen Warnung verworfen");
    $warning = $data["warnings"][0];
    if (($warning["level"] ?? null) !== "warning" || ($warning["type"] ?? null) !== "PartialParsing" || ($warning["path"] ?? null) !== "appinfo/info.xml") throw new RuntimeException("Semgrep-Warnung ist nicht explizit typisiert");
    if (($data["errors"] ?? []) !== []) throw new RuntimeException("Semgrep-Warnung wurde als Fehler typisiert");
' "$temporary/semgrep-warning-finding.json"

if MOCK_SEMGREP_WARNING=1 run_scanners "$temporary/semgrep-warning-only.json" >"$temporary/semgrep-warning-only.out" 2>&1; then
    echo 'Eine partielle Semgrep-Analyse ohne Fund wurde als vollständig sauber gezählt.' >&2
    exit 1
fi
grep -Fq 'Semgrep wurde wegen Analysewarnungen nur teilweise ausgeführt.' "$temporary/semgrep-warning-only.out"
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if (($data["status"] ?? null) !== "failed" || ($data["scanners"]["semgrep"]["status"] ?? null) !== "partial") throw new RuntimeException("Semgrep-Warnung wird als vollständig sauber gezählt");
    if (($data["scanners"]["semgrep"]["warnings"] ?? null) !== 1 || count($data["warnings"] ?? []) !== 1) throw new RuntimeException("Partielle Semgrep-Evidence ist unvollständig");
    if (($data["findings"] ?? []) !== [] || ($data["errors"] ?? []) !== []) throw new RuntimeException("Warnlauf enthält unerwartete Funde oder Fehler");
' "$temporary/semgrep-warning-only.json"

if MOCK_SEMGREP_REPORT_ERROR=1 run_scanners "$temporary/semgrep-report-error.json" >"$temporary/semgrep-report-error.out" 2>&1; then
    echo 'Ein Semgrep-Parserfehler im Bericht wurde als grün behandelt.' >&2
    exit 1
fi
grep -Fq 'Semgrep konnte nicht vollständig ausgeführt werden.' "$temporary/semgrep-report-error.out"
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $error = $data["errors"][0] ?? null;
    if (($data["scanners"]["semgrep"]["status"] ?? null) !== "error") throw new RuntimeException("Semgrep-Parserfehler bleibt nicht fail-closed");
    if (($error["level"] ?? null) !== "error" || ($error["type"] ?? null) !== "ParseError") throw new RuntimeException("Semgrep-Parserfehler ist nicht explizit typisiert");
    if (($data["warnings"] ?? []) !== []) throw new RuntimeException("Semgrep-Parserfehler wurde als Warnung typisiert");
' "$temporary/semgrep-report-error.json"
if MOCK_OSV_FINDING=1 run_scanners "$temporary/osv-failed.json" >/dev/null 2>&1; then
    echo 'Eine nicht akzeptierte Dependency-Schwachstelle wurde zugelassen.' >&2
    exit 1
fi
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $finding = $data["findings"][0] ?? null;
    if ($finding["scanner"] !== "osv-scanner" || $finding["severity"] !== "HIGH") throw new RuntimeException("OSV-Fund ist unvollständig");
    if ($finding["dependency_relation"] !== "transitive" || $finding["fix_available"] !== true) throw new RuntimeException("Dependency-Metadaten fehlen");
' "$temporary/osv-failed.json"

php -r '
    $finding = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR)["findings"][0];
    $data = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][] = [
        "id" => "TEST-OSV-ACCEPTED",
        "scanner" => "osv-scanner",
        "repository" => $finding["repository"],
        "path" => $finding["path"],
        "fingerprint" => $finding["fingerprint"],
        "vulnerability" => $finding["vulnerability"],
        "severity" => $finding["severity"],
        "component" => $finding["component"],
        "dependency_relation" => $finding["dependency_relation"],
        "fix_available" => $finding["fix_available"],
        "reason" => "synthetic contract fixture",
        "compensating_controls" => ["fixture-only"],
        "review_on" => "2099-01-01",
        "expires_on" => "2099-12-31"
    ];
    file_put_contents($argv[3], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/osv-failed.json" "$workspace/security-compliance/vulnerability-exceptions.json" "$temporary/osv-accepted.json"
MOCK_OSV_FINDING=1 run_scanners "$temporary/osv-accepted-evidence.json" \
    --vulnerability-exceptions "$temporary/osv-accepted.json" >/dev/null

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $data["exceptions"][0]["severity"] = "LOW";
    file_put_contents($argv[2], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/osv-accepted.json" "$temporary/osv-stale-severity.json"
if MOCK_OSV_FINDING=1 run_scanners "$temporary/osv-stale-severity-evidence.json" \
    --vulnerability-exceptions "$temporary/osv-stale-severity.json" >/dev/null 2>&1; then
    echo 'Eine veraltete Dependency-Risikoakzeptanz blieb trotz geänderter Schwere wirksam.' >&2
    exit 1
fi

if MOCK_SEMGREP_ERROR=1 run_scanners "$temporary/error.json" >"$temporary/error.out" 2>&1; then
    echo 'Ein Scannerfehler wurde als grün behandelt.' >&2
    exit 1
fi
grep -Fq 'Semgrep konnte nicht vollständig ausgeführt werden.' "$temporary/error.out"

mkdir -p "$temporary/real-probe"
git -C "$temporary/real-probe" init -q
printf '%s\n' '[allowlist]' 'description = "target-controlled suppression"' "paths = ['.*']" \
    > "$temporary/real-probe/.gitleaks.toml"
printf '%s\n' '*' > "$temporary/real-probe/.gitleaksignore"
printf '%s\n' 'danger.php' > "$temporary/real-probe/.semgrepignore"
printf '%s\n' '[[PackageOverrides]]' 'ignore = true' 'reason = "target-controlled suppression"' \
    > "$temporary/real-probe/osv-scanner.toml"
printf '%s\n' '<?php' > "$temporary/real-probe/danger.php"
printf '%s%s%s\n' '$token = "' 'AKIA' 'ABCDEFGHIJKLMNOP"; // gitleaks:allow' \
    >> "$temporary/real-probe/danger.php"
printf '%s\n' 'eval($request); // nosem' >> "$temporary/real-probe/danger.php"
printf '%s\n' '{"name":"scanner-probe","version":"1.0.0","lockfileVersion":3,"requires":true,"packages":{"":{"dependencies":{"lodash":"4.17.20"}},"node_modules/lodash":{"version":"4.17.20"}},"dependencies":{"lodash":{"version":"4.17.20"}}}' \
    > "$temporary/real-probe/package-lock.json"
git -C "$temporary/real-probe" add .
if GITLEAKS_CONFIG="$temporary/real-probe/.gitleaks.toml" \
    SEMGREP_RULES="$temporary/hostile-semgrep.yml" \
    "$runner" --evidence-file "$temporary/real-probe.json" \
        --repository "suppression-probe=$temporary/real-probe" \
        >"$temporary/real-probe.out" 2>&1; then
    echo 'Repository-lokale Suppressionen haben reale Scannerfunde verborgen.' >&2
    exit 1
fi
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    foreach (["gitleaks", "semgrep", "osv-scanner"] as $scanner) {
        if (($data["scanners"][$scanner]["findings"] ?? 0) < 1) throw new RuntimeException("Realer Suppressionsprobe-Fund fehlt: {$scanner}");
    }
    if (($data["execution"]["mode"] ?? null) !== "production" || ($data["execution"]["test_doubles"] ?? null) !== false || ($data["execution"]["publishable"] ?? null) !== true) throw new RuntimeException("Realer Scannerlauf ist falsch gekennzeichnet");
' "$temporary/real-probe.json"

mkdir -p "$temporary/builder-test-mode"
if SECURITY_SCANNER_TEST_MODE=1 DIST_ROOT="$temporary/builder-test-mode" RELEASE_LABEL='test-mode-rejected' \
    "$workspace/scripts/build-ad-suite-release.sh" >"$temporary/builder-test-mode.out" 2>&1; then
    echo 'Der Release-Builder akzeptiert SECURITY_SCANNER_TEST_MODE.' >&2
    exit 1
fi
grep -Fq 'SECURITY_SCANNER_TEST_MODE ist im Release-Builder unzulässig.' "$temporary/builder-test-mode.out"
if find "$temporary/builder-test-mode" -mindepth 1 -print -quit | grep -q .; then
    echo 'Der Release-Builder schrieb trotz Scanner-Testmodus Release-Dateien.' >&2
    exit 1
fi

for expected in \
    'run-security-scanners' \
    '--security-scan-evidence'; do
    grep -Fq -- "$expected" "$workspace/scripts/build-ad-suite-release.sh" || {
        echo "Release-Builder integriert den Scannervertrag nicht: $expected" >&2
        exit 1
    }
done
grep -Fq 'run-security-scanners' "$workspace/.github/workflows/deploy-staging.yml" || {
    echo 'Der wiederverwendbare CI-/Staging-Pfad blockiert nicht auf Scanner-Funden.' >&2
    exit 1
}

echo 'Security-Scanner-Vertrag: OK'
