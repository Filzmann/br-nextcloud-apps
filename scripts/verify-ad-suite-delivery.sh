#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog_reader="$workspace/scripts/read-ad-product-catalog.php"
php "$catalog_reader" validate >/dev/null
mapfile -t apps < <(php "$catalog_reader" full-suite)
mapfile -t products < <(php "$catalog_reader" products)

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
        if ((string)$xml->dependencies->nextcloud["min-version"] !== "34") throw new RuntimeException("Nextcloud-Minimum ist nicht 34");
        if ((string)$xml->dependencies->nextcloud["max-version"] !== "34") throw new RuntimeException("Nextcloud-Maximum ist nicht 34");
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
    for app in "${apps[@]}"; do
        (cd "$workspace/nextcloud-dev" && ddev exec -d /var/www/html/html php occ app:list | grep -i "$app")
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
    ADC_BASE_URL="${AD_SUITE_BASE_URL:-https://nextcloud-dev.ddev.site}" \
        "$workspace/adcalendar/tests/access-matrix-ddev-smoke.sh"
    ADU_BASE_URL="${AD_SUITE_BASE_URL:-https://nextcloud-dev.ddev.site}" \
        "$workspace/adurlaub/tests/access-matrix-ddev-smoke.sh"
fi

echo '== Reproduzierbarer Paketbau =='
DIST_ROOT="$temporary_dist" RELEASE_LABEL='delivery-check' SKIP_TESTS=1 \
    "$workspace/scripts/build-ad-suite-release.sh"
(cd "$temporary_dist" && sha256sum --check ad-suite-delivery-check.tar.gz.sha256)
(cd "$temporary_dist/ad-suite-delivery-check" && sha256sum --check SHA256SUMS)
full_bundle_members="$(tar -tzf "$temporary_dist/ad-suite-delivery-check.tar.gz")"
for contract in \
    'ad-suite-delivery-check/install.sh' \
    'ad-suite-delivery-check/ad-product-catalog.json' \
    'ad-suite-delivery-check/LDAP-UNIVENTION.md'; do
    if ! grep -Fq "$contract" <<< "$full_bundle_members"; then
        echo "Vollständiger Suite-Bundle-Vertrag fehlt: $contract" >&2
        exit 1
    fi
done
for product in "${products[@]}"; do
    product_bundle="$temporary_dist/ad-product-$product-delivery-check.tar.gz"
    product_hash="$product_bundle.sha256"
    if [[ ! -f "$product_bundle" || ! -f "$product_hash" ]]; then
        echo "Produktpaket fehlt: $product" >&2
        exit 1
    fi
    (cd "$temporary_dist" && sha256sum --check "$(basename "$product_hash")")
    product_members="$(tar -tzf "$product_bundle")"
    for contract in \
        "ad-product-$product-delivery-check/install.sh" \
        "ad-product-$product-delivery-check/INSTALLATION.md" \
        "ad-product-$product-delivery-check/BETRIEB-UND-RUECKBAU.md" \
        "ad-product-$product-delivery-check/ABNAHMEPROTOKOLL.md" \
        "ad-product-$product-delivery-check/ad-product-catalog.json" \
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
