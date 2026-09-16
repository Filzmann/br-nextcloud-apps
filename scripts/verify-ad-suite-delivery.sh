#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog_reader="$workspace/scripts/read-ad-product-catalog.php"
support_range_checker="$workspace/scripts/validate-nextcloud-support-range.php"
nextcloud_target_major="${NEXTCLOUD_TARGET_MAJOR:-34}"
php "$catalog_reader" validate >/dev/null
mapfile -t apps < <(php "$catalog_reader" full-suite)
mapfile -t products < <(php "$catalog_reader" bundle-products)

if [[ "${AD_SUITE_GATE_WRAPPER:-0}" != '1' ]]; then
    echo 'Direkter Aufruf ist nicht freigabefähig; scripts/check-ad-suite-delivery verwenden.' >&2
    exit 2
fi
if [[ "${PARENT_FAST_CHECK_VERIFIED:-0}" != '1' ]]; then
    echo 'Der interne Delivery-Verify braucht einen zuvor erfolgreichen Parent-Fast-Check.' >&2
    exit 2
fi
if [[ "${ALLOW_DIRTY:-0}" == '1' && "${DIAGNOSTIC_MODE:-0}" != '1' ]]; then
    echo 'ALLOW_DIRTY ist ausschließlich im expliziten Diagnosemodus zulässig.' >&2
    exit 2
fi
if [[ "${DIAGNOSTIC_MODE:-0}" == '1' && "${ALLOW_DIRTY:-0}" != '1' ]]; then
    echo 'DIAGNOSTIC_MODE braucht den kontrollierten Dirty-Diagnosepfad.' >&2
    exit 2
fi
temporary_dist="$(mktemp -d)"

cleanup() {
    rm -rf "$temporary_dist"
}
trap cleanup EXIT

for command in bash git php node tar sha256sum; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Erforderlicher Befehl fehlt: $command" >&2
        exit 1
    fi
done

for document in README.md LICENSE SECURITY.md docs/INSTALLATION.md docs/OPERATIONS.md docs/ACCEPTANCE.md docs/DELIVERY-GATE.md docs/LDAP-UNIVENTION.md; do
    if [[ ! -f "$workspace/ad-suite/$document" ]]; then
        echo "Öffentliche Suite-Dokumentation fehlt: $document" >&2
        exit 1
    fi
done

for app in "${apps[@]}"; do
    repo="$workspace/$app"
    info="$repo/appinfo/info.xml"

    echo "== $app: Metadaten und Repository =="
    if [[ ! -d "$repo/.git" || ! -f "$info" ]]; then
        echo "App-Repository ist unvollständig: $app" >&2
        exit 1
    fi
    if [[ "${ALLOW_DIRTY:-0}" != '1' ]] && [[ -n "$(git -C "$repo" status --porcelain)" ]]; then
        echo "App-Repository ist nicht sauber: $app" >&2
        exit 1
    fi

    php "$support_range_checker" "$info" "$app" 33 "$nextcloud_target_major"
    php -r '
        $xml = simplexml_load_file($argv[1]);
        if ($xml === false) throw new RuntimeException("info.xml ist ungültig");
        $expectedId = $argv[2];
        $required = ["id", "name", "summary", "description", "version", "licence", "author", "website", "bugs", "repository", "namespace"];
        foreach ($required as $field) {
            if (trim((string)$xml->{$field}) === "") throw new RuntimeException("Pflichtfeld fehlt: {$field}");
        }
        if ((string)$xml->id !== $expectedId) throw new RuntimeException("App-ID stimmt nicht mit dem Ordner überein");
        if (strtolower((string)$xml->licence) !== "agpl") throw new RuntimeException("Lizenzmetadatum ist nicht AGPL");
        if (version_compare((string)$xml->dependencies->php["min-version"], "8.3", "<")) throw new RuntimeException("PHP-Minimum liegt unter 8.3");
        if ((string)$xml->website !== "https://github.com/Filzmann/ad-suite") throw new RuntimeException("Zentrale Projektseite fehlt");
        if (!str_starts_with((string)$xml->bugs, "https://github.com/Filzmann/nextcloud-") || !str_ends_with((string)$xml->bugs, "/issues")) throw new RuntimeException("Öffentlicher Fehlerkanal ist ungültig");
        if (!str_starts_with((string)$xml->repository, "https://github.com/Filzmann/nextcloud-")) throw new RuntimeException("Öffentliches Quellrepository ist ungültig");
    ' "$info" "$app"

    for required in LICENSE README.md CHANGELOG.md AGENTS.md tests/run.php tests/run-js.mjs; do
        if [[ ! -f "$repo/$required" ]]; then
            echo "Pflichtdatei fehlt in $app: $required" >&2
            exit 1
        fi
    done
    if ! grep -q 'GNU AFFERO GENERAL PUBLIC LICENSE' "$repo/LICENSE"; then
        echo "AGPL-Lizenztext fehlt in $app/LICENSE" >&2
        exit 1
    fi
    if find "$repo" -path "$repo/.git" -prune -o -type l -print -quit | grep -q .; then
        echo "Symlink im App-Repository gefunden: $app" >&2
        exit 1
    fi

    scan_paths=()
    for path in appinfo lib js templates css README.md CHANGELOG.md; do
        [[ -e "$repo/$path" ]] && scan_paths+=("$repo/$path")
    done
    if rg -n 'example\.invalid|/home/filzmann|nextcloud-dev\.ddev\.site|ChatGPT-Prototyp|Codex-Prototyp|\$wpdb|current_user_can\(|wp_nonce|add_shortcode\(' "${scan_paths[@]}"; then
        echo "Nicht auslieferungsfähiger Entwicklungs- oder WordPress-Verweis in $app" >&2
        exit 1
    fi

    while IFS= read -r script; do
        bash -n "$script"
    done < <(find "$repo/tests" -type f -name '*.sh' | sort)

    echo "== $app: schnelle PHP- und JavaScript-Tests =="
    (cd "$repo" && php tests/run.php)
    (cd "$repo" && node tests/run-js.mjs)
done

if [[ "${RUN_DDEV_CHECKS:-0}" == '1' ]]; then
    echo '== DDEV: Nextcloud- und App-Status =='
    (cd "$workspace/nextcloud-dev" && ddev exec -d /var/www/html/html php occ status)
    app_inventory="$(cd "$workspace/nextcloud-dev" && ddev exec -d /var/www/html/html php occ app:list)"
    for app in "${apps[@]}"; do
        grep -i "$app" <<< "$app_inventory"
    done
fi

if [[ "${RUN_HTTP_SMOKES:-0}" == '1' ]]; then
    : "${AD_SUITE_BASE_URL:?AD_SUITE_BASE_URL fehlt für HTTP-Smokes}"
    : "${AD_SUITE_USER:?AD_SUITE_USER fehlt für HTTP-Smokes}"
    : "${AD_SUITE_PASSWORD:?AD_SUITE_PASSWORD fehlt für HTTP-Smokes}"

    echo '== Authentifizierte HTTP- und CSRF-Smokes =='
    ORGS_BASE_URL="$AD_SUITE_BASE_URL" ORGS_ADMIN_USER="$AD_SUITE_USER" ORGS_ADMIN_PASSWORD="$AD_SUITE_PASSWORD" \
        "$workspace/orgsuite/tests/http-smoke.sh"
    ADC_BASE_URL="$AD_SUITE_BASE_URL" ADC_USER="$AD_SUITE_USER" ADC_PASSWORD="$AD_SUITE_PASSWORD" \
        "$workspace/adcalendar/tests/http-smoke.sh"
    ADP_BASE_URL="$AD_SUITE_BASE_URL" ADP_USER="$AD_SUITE_USER" ADP_PASSWORD="$AD_SUITE_PASSWORD" \
        "$workspace/adplaner/tests/http-smoke.sh"
    ADU_BASE_URL="$AD_SUITE_BASE_URL" ADU_USER="$AD_SUITE_USER" ADU_PASSWORD="$AD_SUITE_PASSWORD" \
        "$workspace/adurlaub/tests/http-smoke.sh"
    ADR_BASE_URL="$AD_SUITE_BASE_URL" ADR_USER="$AD_SUITE_USER" ADR_PASSWORD="$AD_SUITE_PASSWORD" \
        "$workspace/adroom/tests/http-smoke.sh"
fi

if [[ "${RUN_ACCESS_MATRICES:-0}" == '1' ]]; then
    echo '== Selbstbereinigende DDEV-Rechtematrizen =='
    ADP_BASE_URL="${AD_SUITE_BASE_URL:-https://nextcloud-dev.ddev.site}" \
        "$workspace/adplaner/tests/access-matrix-ddev-smoke.sh"
    ADC_BASE_URL="${AD_SUITE_BASE_URL:-https://nextcloud-dev.ddev.site}" \
        "$workspace/adcalendar/tests/access-matrix-ddev-smoke.sh"
    ADU_BASE_URL="${AD_SUITE_BASE_URL:-https://nextcloud-dev.ddev.site}" \
        "$workspace/adurlaub/tests/access-matrix-ddev-smoke.sh"
fi

if [[ "${RUN_INTEGRATION_SMOKES:-0}" == '1' ]]; then
    echo '== Reale DDEV-Integrations- und Migrations-Smokes =='
    "$workspace/adplaner/tests/integration-ddev-smoke.sh"
    ADC_BASE_URL="${AD_SUITE_BASE_URL:-https://nextcloud-dev.ddev.site}" \
        "$workspace/adcalendar/tests/admin-defaults-ddev-smoke.sh"
    "$workspace/adcalendar/tests/integration-ddev-smoke.sh"
    "$workspace/adurlaub/tests/migration-schema-ddev-smoke.sh"
    RECR_BASE_URL="${AD_SUITE_BASE_URL:-https://nextcloud-dev.ddev.site}" \
        "$workspace/adrecruitment/tests/ddev-smoke.sh"
fi

echo '== Reproduzierbarer Paketbau =='
DIST_ROOT="$temporary_dist" RELEASE_LABEL='delivery-check' SKIP_TESTS=1 \
    NEXTCLOUD_TARGET_MAJOR="$nextcloud_target_major" \
    "$workspace/scripts/build-ad-suite-release.sh"
(cd "$temporary_dist" && sha256sum --check ad-suite-delivery-check.tar.gz.sha256)
(cd "$temporary_dist" && sha256sum --check ad-suite-delivery-check.release-evidence.json.sha256)
(cd "$temporary_dist/ad-suite-delivery-check" && sha256sum --check SHA256SUMS)
evidence_release_mode='candidate'
if [[ "${DIAGNOSTIC_MODE:-0}" == '1' ]]; then
    evidence_release_mode='diagnostic'
fi
php -r '
    $evidence = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $actual = hash_file("sha256", $argv[2]);
    $expectedMode = $argv[3];
    $expectedDirty = $expectedMode === "diagnostic";
    if ($evidence["artifact"]["sha256"] !== $actual) throw new RuntimeException("Suite-Evidence-Hash stimmt nicht");
    if ($evidence["tests"]["builder"] !== "skipped-by-builder") throw new RuntimeException("Builder-Teststatus ist unzutreffend");
    if ($evidence["tests"]["delivery_gate"] !== "not-evaluated-by-builder") throw new RuntimeException("Delivery-Gate-Status ist unzutreffend");
    if ($evidence["security"]["mapping_consistency"] !== "passed") throw new RuntimeException("Mapping-Check fehlt");
    if ($evidence["build_context"]["release_mode"] !== $expectedMode) throw new RuntimeException("Release-Modus ist unzutreffend");
    if ($evidence["build_context"]["dirty_sources"] !== $expectedDirty) throw new RuntimeException("Dirty-Status ist unzutreffend");
    if ($evidence["build_context"]["publishable"] !== false) throw new RuntimeException("Builder behauptet Veröffentlichbarkeit");
    if ($evidence["compliance"]["certification"] !== "not-certified") throw new RuntimeException("Zertifizierungsgrenze fehlt");
' "$temporary_dist/ad-suite-delivery-check.release-evidence.json" "$temporary_dist/ad-suite-delivery-check.tar.gz" "$evidence_release_mode"
full_bundle_members="$(tar -tzf "$temporary_dist/ad-suite-delivery-check.tar.gz")"
for contract in \
    'ad-suite-delivery-check/install.sh' \
    'ad-suite-delivery-check/ad-product-catalog.json' \
    'ad-suite-delivery-check/sbom.cdx.json' \
    'ad-suite-delivery-check/LDAP-UNIVENTION.md'; do
    if ! grep -Fq "$contract" <<< "$full_bundle_members"; then
        echo "Vollständiger Suite-Bundle-Vertrag fehlt: $contract" >&2
        exit 1
    fi
done
for product in "${products[@]}"; do
    product_bundle="$temporary_dist/ad-product-$product-delivery-check.tar.gz"
    product_hash="$product_bundle.sha256"
    product_evidence="$temporary_dist/ad-product-$product-delivery-check.release-evidence.json"
    product_evidence_hash="$product_evidence.sha256"
    if [[ ! -f "$product_bundle" || ! -f "$product_hash" || ! -f "$product_evidence" || ! -f "$product_evidence_hash" ]]; then
        echo "Produktpaket fehlt: $product" >&2
        exit 1
    fi
    (cd "$temporary_dist" && sha256sum --check "$(basename "$product_hash")")
    (cd "$temporary_dist" && sha256sum --check "$(basename "$product_evidence_hash")")
    php -r '
        $evidence = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
        if ($evidence["artifact"]["sha256"] !== hash_file("sha256", $argv[2])) throw new RuntimeException("Produkt-Evidence-Hash stimmt nicht");
        if ($evidence["build_context"]["release_mode"] !== $argv[3]) throw new RuntimeException("Produkt-Evidence-Modus ist unzutreffend");
        if ($evidence["build_context"]["publishable"] !== false) throw new RuntimeException("Produkt-Builder behauptet Veröffentlichbarkeit");
    ' "$product_evidence" "$product_bundle" "$evidence_release_mode"
    product_members="$(tar -tzf "$product_bundle")"
    for contract in \
        "ad-product-$product-delivery-check/install.sh" \
        "ad-product-$product-delivery-check/INSTALLATION.md" \
        "ad-product-$product-delivery-check/BETRIEB-UND-RUECKBAU.md" \
        "ad-product-$product-delivery-check/ABNAHMEPROTOKOLL.md" \
        "ad-product-$product-delivery-check/ad-product-catalog.json" \
        "ad-product-$product-delivery-check/sbom.cdx.json" \
        "ad-product-$product-delivery-check/localbase-" \
        "ad-product-$product-delivery-check/orgsuite-" \
        "ad-product-$product-delivery-check/$product-"; do
        if ! grep -Fq "$contract" <<< "$product_members"; then
            echo "Produktpaketvertrag fehlt für $product: $contract" >&2
            exit 1
        fi
    done
    for other_product in "${products[@]}"; do
        [[ "$other_product" == "$product" ]] && continue
        if grep -Eq "ad-product-$product-delivery-check/$other_product-[^/]+\\.tar\\.gz$" <<< "$product_members"; then
            echo "Fremdes Fachprodukt im Produktpaket $product: $other_product" >&2
            exit 1
        fi
    done
done

if [[ "${DIAGNOSTIC_MODE:-0}" == '1' ]]; then
    echo 'DIAGNOSE ABGESCHLOSSEN – KEIN RELEASE-URTEIL'
else
    echo 'AD-Suite Delivery-Gate: OK'
fi
