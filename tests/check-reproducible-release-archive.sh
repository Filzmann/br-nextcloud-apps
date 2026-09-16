#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
archiver="$workspace/scripts/create-reproducible-tar-gz.sh"
temporary="$(mktemp -d)"

cleanup() {
    rm -rf "$temporary"
}
trap cleanup EXIT

mkdir -p "$temporary/source/demo/sub"
printf '%s\n' 'alpha' > "$temporary/source/demo/a.txt"
printf '%s\n' 'beta' > "$temporary/source/demo/sub/b.txt"

touch -t 202601010101 "$temporary/source/demo/a.txt" "$temporary/source/demo/sub/b.txt"
"$archiver" "$temporary/first.tar.gz" "$temporary/source" demo

touch -t 202608090909 "$temporary/source/demo/a.txt" "$temporary/source/demo/sub/b.txt"
"$archiver" "$temporary/second.tar.gz" "$temporary/source" demo

if ! cmp -s "$temporary/first.tar.gz" "$temporary/second.tar.gz"; then
    echo 'Releasearchive sind bei identischem Inhalt nicht bytegleich reproduzierbar.' >&2
    exit 1
fi

if [[ "$(tar -tzf "$temporary/first.tar.gz" | head -n 1)" != 'demo/' ]]; then
    echo 'Das reproduzierbare Archiv besitzt nicht den erwarteten Wurzelordner.' >&2
    exit 1
fi

builder="$workspace/scripts/build-ad-suite-release.sh"
for expected_call in \
    '"$reproducible_archiver" "$archive" "$stage" "$app"' \
    '"$reproducible_archiver" "$product_bundle" "$dist_root" "$product_name"' \
    '"$reproducible_archiver" "$bundle" "$dist_root" "$(basename "$release_dir")"'; do
    if ! grep -Fq "$expected_call" "$builder"; then
        echo "Der AD-Suite-Builder verwendet den reproduzierbaren Archivierer nicht vollständig: $expected_call" >&2
        exit 1
    fi
done

for required in \
    "$workspace/scripts/generate-cyclonedx-sbom.php" \
    "$workspace/scripts/generate-release-evidence.php" \
    "$workspace/scripts/assert-release-output-available.php"; do
    [[ -f "$required" ]] || {
        echo "Generator für Release-Security-Evidence fehlt: ${required#"$workspace/"}" >&2
        exit 1
    }
done

mkdir -p "$temporary/repositories/localbase/appinfo" "$temporary/repositories/adcalendar/appinfo"
printf '%s\n' '<info><id>localbase</id><dependencies><nextcloud min-version="33" max-version="34"/></dependencies></info>' \
    > "$temporary/repositories/localbase/appinfo/info.xml"
printf '%s\n' '<info><id>adcalendar</id><dependencies><nextcloud min-version="33" max-version="34"/><app>localbase</app></dependencies></info>' \
    > "$temporary/repositories/adcalendar/appinfo/info.xml"
mkdir -p "$temporary/repositories/adcalendar/js/vendor/synthetic-library" "$temporary/repositories/adcalendar/resources"
printf '%s\n' 'library-content' > "$temporary/repositories/adcalendar/js/vendor/synthetic-library/library.js"
third_party_file_hash="$(sha256sum "$temporary/repositories/adcalendar/js/vendor/synthetic-library/library.js" | cut -d' ' -f1)"
third_party_tree_hash="$(php -r '$root=$argv[1];$hash=hash_init("sha256");hash_update($hash,"library.js\0");hash_update_file($hash,$root."/library.js");echo hash_final($hash);' "$temporary/repositories/adcalendar/js/vendor/synthetic-library")"
php -r '
    $inventory=[
        "bomFormat"=>"CycloneDX","specVersion"=>"1.6","version"=>1,
        "metadata"=>["properties"=>[["name"=>"filzmann:inventory-owner","value"=>"adcalendar"]]],
        "components"=>[[
            "type"=>"library","bom-ref"=>"pkg:npm/synthetic-library@1.2.3","name"=>"synthetic-library","version"=>"1.2.3","scope"=>"required","purl"=>"pkg:npm/synthetic-library@1.2.3",
            "properties"=>[
                ["name"=>"filzmann:bundled-root","value"=>"js/vendor/synthetic-library"],
                ["name"=>"filzmann:bundled-tree-sha256","value"=>$argv[2]],
                ["name"=>"filzmann:bundled-file-count","value"=>"1"],
                ["name"=>"filzmann:runtime-scope","value"=>"app-local-browser"],
                ["name"=>"filzmann:dependency-scan","value"=>"osv-by-purl"],
                ["name"=>"filzmann:sast-treatment","value"=>"pinned-third-party-source"],
                ["name"=>"filzmann:license-path","value"=>"js/vendor/synthetic-library/library.js"]
            ],
            "components"=>[["type"=>"file","bom-ref"=>"file:js/vendor/synthetic-library/library.js","name"=>"js/vendor/synthetic-library/library.js","scope"=>"required","hashes"=>[["alg"=>"SHA-256","content"=>$argv[3]]]]]
        ]]
    ];
    file_put_contents($argv[1],json_encode($inventory,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
' "$temporary/repositories/adcalendar/resources/third-party-components.cdx.json" "$third_party_tree_hash" "$third_party_file_hash"

cat > "$temporary/manifest.tsv" <<'EOF'
app	version	git_commit	sha256	signed
localbase	1.2.3	1111111111111111111111111111111111111111	aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa	no
adcalendar	2.3.4	2222222222222222222222222222222222222222	bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb	yes
EOF

php "$workspace/scripts/generate-cyclonedx-sbom.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/sbom.cdx.json" \
    --name ad-suite-test \
    --version test-1 \
    --nextcloud-major 34 \
    --repository-root "$temporary/repositories"

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if ($data["bomFormat"] !== "CycloneDX") throw new RuntimeException("CycloneDX-Format fehlt");
    if ($data["specVersion"] !== "1.6") throw new RuntimeException("CycloneDX-Version fehlt");
    if ($data["metadata"]["component"]["name"] !== "ad-suite-test") throw new RuntimeException("SBOM-Komponente fehlt");
    if (count($data["components"]) !== 4) throw new RuntimeException("SBOM-Komponenten unvollständig");
    if (count($data["dependencies"]) !== 3) throw new RuntimeException("SBOM-Abhängigkeiten unvollständig");
    if ($data["components"][1]["hashes"][0]["alg"] !== "SHA-256") throw new RuntimeException("SBOM-Hash fehlt");
    if (!in_array("application:localbase@1.2.3", $data["dependencies"][2]["dependsOn"], true)) throw new RuntimeException("App-Laufzeitabhängigkeit fehlt");
    $libraries=array_values(array_filter($data["components"],static fn(array $component):bool=>($component["bom-ref"]??null)==="pkg:npm/synthetic-library@1.2.3"));
    if (count($libraries)!==1 || ($libraries[0]["components"][0]["hashes"][0]["content"]??null)!==$argv[2]) throw new RuntimeException("Validierte Third-Party-Komponente wurde nicht unverändert und eindeutig übernommen");
    if (!in_array("pkg:npm/synthetic-library@1.2.3",$data["dependencies"][2]["dependsOn"],true)) throw new RuntimeException("Gebündelte Third-Party-Abhängigkeit fehlt");
' "$temporary/sbom.cdx.json" "$third_party_file_hash"

printf '%s\n' 'tampered' >> "$temporary/repositories/adcalendar/js/vendor/synthetic-library/library.js"
if php "$workspace/scripts/generate-cyclonedx-sbom.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/tampered-sbom.cdx.json" \
    --name ad-suite-test \
    --version test-1 \
    --nextcloud-major 34 \
    --repository-root "$temporary/repositories" >"$temporary/tampered-sbom.out" 2>&1; then
    echo 'Eine manipulierte gebündelte Dependency gelangte in die Release-SBOM.' >&2
    exit 1
fi
grep -Fq 'Third-Party-Baumhash stimmt nicht' "$temporary/tampered-sbom.out"
printf '%s\n' 'library-content' > "$temporary/repositories/adcalendar/js/vendor/synthetic-library/library.js"

printf '%s\n' '<info><id>adcalendar</id><dependencies><nextcloud min-version="33" max-version="34"/><app>missing-runtime-app</app></dependencies></info>' \
    > "$temporary/repositories/adcalendar/appinfo/info.xml"
if php "$workspace/scripts/generate-cyclonedx-sbom.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/invalid-sbom.cdx.json" \
    --name ad-suite-test \
    --version test-1 \
    --nextcloud-major 34 \
    --repository-root "$temporary/repositories" \
    >"$temporary/missing-runtime.out" 2>&1; then
    echo 'Eine deklarierte, aber im Release fehlende Laufzeitabhängigkeit wurde akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Deklarierte App-Laufzeitabhängigkeit fehlt im Release: adcalendar -> missing-runtime-app' "$temporary/missing-runtime.out"

printf '%s\n' '<info><id>adcalendar</id><dependencies><nextcloud min-version="33" max-version="34"/><app>localbase</app></dependencies></info>' \
    > "$temporary/repositories/adcalendar/appinfo/info.xml"

sbom_hash="$(sha256sum "$temporary/sbom.cdx.json" | cut -d' ' -f1)"
cat > "$temporary/security-scans.json" <<'JSON'
{
  "schema_version": 1,
  "generated_at": "2026-09-14T12:00:00+00:00",
  "status": "passed",
  "execution": {"mode": "production", "test_doubles": false, "publishable": true},
  "scope": {"repositories": ["parent", "localbase", "adcalendar"], "git_history_scanned": false, "third_party_components": [{"repository":"adcalendar","inventory_path":"resources/third-party-components.cdx.json","purl":"pkg:npm/synthetic-library@1.2.3","bundled_root":"js/vendor/synthetic-library","tree_sha256":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","file_count":1}]},
  "tools": {
    "gitleaks": {"version": "8.30.1", "integrity": "sha256-pinned"},
    "osv-scanner": {"version": "2.4.0", "integrity": "sha256-pinned"},
    "semgrep": {"version": "1.176.0", "integrity": "sha256:e5ea1a270ca5557a114ae7a30a8f860cc16a924f8df85cd975688f17c7c97731"}
  },
  "scanners": {
    "gitleaks": {"status": "passed", "scanned_repositories": 3, "scanned_lockfiles": 0, "scanned_inventories": 0, "findings": 0, "accepted_findings": 0, "warnings": 0},
    "semgrep": {"status": "passed", "scanned_repositories": 3, "scanned_lockfiles": 0, "scanned_inventories": 0, "findings": 0, "accepted_findings": 0, "warnings": 0},
    "osv-scanner": {"status": "passed", "scanned_repositories": 3, "scanned_lockfiles": 1, "scanned_inventories": 1, "findings": 0, "accepted_findings": 0, "warnings": 0}
  },
  "findings": [],
  "warnings": [],
  "errors": []
}
JSON
php "$workspace/scripts/generate-release-evidence.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/release-evidence.json" \
    --release-id ad-suite-test \
    --source-timestamp 2026-09-14T12:00:00+00:00 \
    --mapping-status passed \
    --test-status passed-by-builder \
    --release-mode candidate \
    --dirty-sources false \
    --security-scan-evidence "$temporary/security-scans.json" \
    --sbom-file sbom.cdx.json \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file ad-suite-test.tar.gz \
    --artifact-sha256 cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if ($data["release_id"] !== "ad-suite-test") throw new RuntimeException("Release-ID fehlt");
    if ($data["tests"]["builder"] !== "passed-by-builder") throw new RuntimeException("Teststatus fehlt");
    if ($data["security"]["mapping_consistency"] !== "passed") throw new RuntimeException("Mappingstatus fehlt");
    if ($data["security"]["sast"] !== "passed" || $data["security"]["dependency_scan"] !== "passed" || $data["security"]["secret_scan"] !== "passed") throw new RuntimeException("Scannerstatus fehlt");
    if ($data["security"]["scanner_evidence"]["tools"]["semgrep"]["version"] !== "1.176.0") throw new RuntimeException("Scannerprovenienz fehlt");
    if ($data["sbom"]["sha256"] !== $argv[2]) throw new RuntimeException("SBOM-Hash stimmt nicht");
    if (count($data["sources"]) !== 2) throw new RuntimeException("Quellen unvollständig");
    if ($data["build_context"]["release_mode"] !== "candidate") throw new RuntimeException("Release-Modus fehlt");
    if ($data["build_context"]["dirty_sources"] !== false) throw new RuntimeException("Dirty-Status fehlt");
    if ($data["build_context"]["publishable"] !== false) throw new RuntimeException("Builder behauptet Veröffentlichbarkeit");
' "$temporary/release-evidence.json" "$sbom_hash"

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $data["scanners"]["gitleaks"]["scanned_repositories"] = 0;
    file_put_contents($argv[2], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/security-scans.json" "$temporary/incomplete-security-scans.json"
if php "$workspace/scripts/generate-release-evidence.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/incomplete-release-evidence.json" \
    --release-id incomplete-security-scope \
    --source-timestamp 2026-09-14T12:00:00+00:00 \
    --mapping-status passed \
    --test-status passed-by-builder \
    --release-mode candidate \
    --dirty-sources false \
    --security-scan-evidence "$temporary/incomplete-security-scans.json" \
    --sbom-file sbom.cdx.json \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file incomplete-security-scope.tar.gz \
    --artifact-sha256 cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc \
    >"$temporary/incomplete-security-scope.out" 2>&1; then
    echo 'Unvollständiger Scanner-Scope wurde als bestandene Release-Evidence akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Security-Scanner-Scope ist unvollständig: gitleaks' "$temporary/incomplete-security-scope.out"

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $data["scanners"]["osv-scanner"]["scanned_inventories"] = 0;
    file_put_contents($argv[2], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/security-scans.json" "$temporary/incomplete-inventory-coverage.json"
if php "$workspace/scripts/generate-release-evidence.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/incomplete-inventory-evidence.json" \
    --release-id incomplete-inventory-coverage \
    --source-timestamp 2026-09-14T12:00:00+00:00 \
    --mapping-status passed \
    --test-status passed-by-builder \
    --release-mode candidate \
    --dirty-sources false \
    --security-scan-evidence "$temporary/incomplete-inventory-coverage.json" \
    --sbom-file sbom.cdx.json \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file incomplete-inventory-coverage.tar.gz \
    --artifact-sha256 cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc \
    >"$temporary/incomplete-inventory-coverage.out" 2>&1; then
    echo 'Unvollständige OSV-Coverage eines Third-Party-Inventars wurde als Release-Evidence akzeptiert.' >&2
    exit 1
fi
grep -Fq 'OSV-Scanner-Coverage der Third-Party-Inventare ist unvollständig.' "$temporary/incomplete-inventory-coverage.out"

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $data["scanners"]["semgrep"]["warnings"] = 1;
    $data["warnings"][] = ["scanner" => "semgrep", "repository" => "adcalendar", "level" => "warning", "type" => "PartialParsing", "reason" => "semgrep-analysis-warning"];
    file_put_contents($argv[2], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/security-scans.json" "$temporary/partial-security-scans.json"
if php "$workspace/scripts/generate-release-evidence.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/partial-release-evidence.json" \
    --release-id partial-security-scan \
    --source-timestamp 2026-09-14T12:00:00+00:00 \
    --mapping-status passed \
    --test-status passed-by-builder \
    --release-mode candidate \
    --dirty-sources false \
    --security-scan-evidence "$temporary/partial-security-scans.json" \
    --sbom-file sbom.cdx.json \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file partial-security-scan.tar.gz \
    --artifact-sha256 cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc \
    >"$temporary/partial-security-scan.out" 2>&1; then
    echo 'Partielle Semgrep-Evidence wurde als releasefähig akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Nur vollständig bestandene Security-Scanner-Evidence darf in Release-Evidence eingehen.' "$temporary/partial-security-scan.out"

php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $data["status"] = "diagnostic";
    $data["execution"] = ["mode" => "diagnostic", "test_doubles" => true, "publishable" => false];
    file_put_contents($argv[2], json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$temporary/security-scans.json" "$temporary/test-mode-security-scans.json"
if php "$workspace/scripts/generate-release-evidence.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/test-mode-release-evidence.json" \
    --release-id rejected-test-mode \
    --source-timestamp 2026-09-14T12:00:00+00:00 \
    --mapping-status passed \
    --test-status passed-by-builder \
    --release-mode candidate \
    --dirty-sources false \
    --security-scan-evidence "$temporary/test-mode-security-scans.json" \
    --sbom-file sbom.cdx.json \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file rejected-test-mode.tar.gz \
    --artifact-sha256 cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc \
    >"$temporary/test-mode-release.out" 2>&1; then
    echo 'Diagnostische Scanner-Test-Evidence wurde als Release-Nachweis akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Diagnostische Security-Scanner-Evidence ist nicht releasefähig.' "$temporary/test-mode-release.out"

php "$workspace/scripts/generate-release-evidence.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/diagnostic-evidence.json" \
    --release-id ad-suite-diagnostic \
    --source-timestamp 2026-09-14T12:00:00+00:00 \
    --mapping-status passed \
    --test-status skipped-by-builder \
    --release-mode diagnostic \
    --dirty-sources true \
    --security-scan-evidence "$temporary/security-scans.json" \
    --sbom-file sbom.cdx.json \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file ad-suite-diagnostic.tar.gz \
    --artifact-sha256 dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd
php -r '
    $data = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    if ($data["tests"]["builder"] !== "skipped-by-builder") throw new RuntimeException("Übersprungene Builder-Tests sind nicht sichtbar");
    if ($data["build_context"]["release_mode"] !== "diagnostic") throw new RuntimeException("Diagnosemodus fehlt");
    if ($data["build_context"]["dirty_sources"] !== true) throw new RuntimeException("Dirty-Diagnose wird verdeckt");
    if ($data["tests"]["delivery_gate"] !== "not-evaluated-by-builder") throw new RuntimeException("Delivery-Gate wird fälschlich behauptet");
' "$temporary/diagnostic-evidence.json"

if php "$workspace/scripts/generate-release-evidence.php" \
    --manifest "$temporary/manifest.tsv" \
    --output "$temporary/invalid-candidate-evidence.json" \
    --release-id invalid-candidate \
    --source-timestamp 2026-09-14T12:00:00+00:00 \
    --mapping-status passed \
    --test-status skipped-by-builder \
    --release-mode candidate \
    --dirty-sources true \
    --security-scan-evidence "$temporary/security-scans.json" \
    --sbom-file sbom.cdx.json \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file invalid-candidate.tar.gz \
    --artifact-sha256 eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee \
    >"$temporary/invalid-candidate.out" 2>&1; then
    echo 'Dirty Sources wurden als Release-Kandidat akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Dirty Sources sind nur im Diagnosemodus zulässig.' "$temporary/invalid-candidate.out"

for expected_call in \
    'generate-cyclonedx-sbom.php' \
    'generate-release-evidence.php' \
    'sbom.cdx.json' \
    'release-evidence.json'; do
    if ! grep -Fq "$expected_call" "$builder"; then
        echo "Security-Evidence ist nicht in den Release-Builder integriert: $expected_call" >&2
        exit 1
    fi
done

if grep -Eq 'SECURITY_MAPPING_VERIFIED|BUILDER_TEST_EVIDENCE' "$builder"; then
    echo 'Release-Evidence kann weiterhin über ungeprüfte Statusvariablen hochgestuft werden.' >&2
    exit 1
fi
grep -Fq '"$security_checker"' "$builder" || {
    echo 'Der Release-Builder führt den Mapping-Check nicht selbst aus.' >&2
    exit 1
}
grep -Fq 'assert-release-output-available.php' "$builder" || {
    echo 'Der Release-Builder besitzt keinen zentralen Kollisionsguard.' >&2
    exit 1
}

collision_guard="$workspace/scripts/assert-release-output-available.php"
php "$collision_guard" "$temporary/free.release-evidence.json" "$temporary/free.sbom.cdx.json"
touch "$temporary/existing.release-evidence.json"
if php "$collision_guard" "$temporary/existing.release-evidence.json" >"$temporary/collision.out" 2>&1; then
    echo 'Eine vorhandene Release-Evidence wurde überschreibbar akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Releaseziel existiert bereits' "$temporary/collision.out"

ln -s "$temporary/missing-target" "$temporary/dangling.sbom.cdx.json"
if php "$collision_guard" "$temporary/dangling.sbom.cdx.json" >"$temporary/symlink.out" 2>&1; then
    echo 'Ein Dangling-Symlink wurde als freies SBOM-Ziel akzeptiert.' >&2
    exit 1
fi
grep -Fq 'Releaseziel existiert bereits' "$temporary/symlink.out"

mkdir -p "$temporary/collision-dist"
touch "$temporary/collision-dist/ad-suite-contract-collision.release-evidence.json"
if DIST_ROOT="$temporary/collision-dist" RELEASE_LABEL='contract-collision' SKIP_TESTS=1 \
    "$builder" >"$temporary/builder-collision.out" 2>&1; then
    echo 'Der Builder akzeptiert eine vorhandene Release-Evidence.' >&2
    exit 1
fi
grep -Fq 'Releaseziel existiert bereits' "$temporary/builder-collision.out"
if [[ -e "$temporary/collision-dist/ad-suite-contract-collision" ]]; then
    echo 'Der Builder schrieb vor Abschluss des Kollisionsguards Release-Dateien.' >&2
    exit 1
fi

if grep -Fq -- '-czf' "$builder"; then
    echo 'Der AD-Suite-Builder enthält weiterhin einen nicht normalisierten gzip-Tar-Aufruf.' >&2
    exit 1
fi

echo 'Reproduzierbarer Release-Archivvertrag: OK'
