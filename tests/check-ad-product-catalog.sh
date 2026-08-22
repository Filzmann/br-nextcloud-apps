#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog="$workspace/localbase/resources/ad-product-catalog.json"
reader="$workspace/scripts/read-ad-product-catalog.php"

[[ -f "$catalog" ]] || { echo 'Kanonischer AD-Produktkatalog fehlt.' >&2; exit 1; }
[[ -f "$reader" ]] || { echo 'Maschinenlesbarer AD-Produktkatalog-Reader fehlt.' >&2; exit 1; }

php "$reader" validate >/dev/null
mapfile -t products < <(php "$reader" products)
expected=(adcalendar adplaner adurlaub adroom adrecruitment adbqplanung)
[[ "${products[*]}" == "${expected[*]}" ]] || { echo 'AD-Produktliste oder Reihenfolge weicht ab.' >&2; exit 1; }

mapfile -t full_suite < <(php "$reader" full-suite)
expected_full=(localbase orgsuite adcalendar adplaner adurlaub adroom adrecruitment)
[[ "${full_suite[*]}" == "${expected_full[*]}" ]] || { echo 'Vollständige Suite enthält nicht alle katalogisierten Apps.' >&2; exit 1; }

mapfile -t bundle_products < <(php "$reader" bundle-products)
[[ "${bundle_products[*]}" == 'adcalendar adplaner adurlaub adroom adrecruitment' ]] || { echo 'Nicht freigegebene Produkte gelangen in Produktbundles.' >&2; exit 1; }

mapfile -t recruitment_bundle < <(php "$reader" product-bundle adrecruitment)
[[ "${recruitment_bundle[*]}" == 'localbase orgsuite adrecruitment' ]] || { echo 'Recruitment-Produktbundle ist falsch zusammengesetzt.' >&2; exit 1; }
if php "$reader" product-bundle adbqplanung >/dev/null 2>&1; then
    echo 'BQ-Planer besitzt vor seiner Releasefreigabe unerwartet ein Produktbundle.' >&2
    exit 1
fi

for product in "${products[@]}"; do
    route="$(php "$reader" field "$product" route)"
    route_suffix="${route#"$product".}"
    route_name="${route_suffix/./#}"
    if ! grep -Fq "'name' => '$route_name'" "$workspace/$product/appinfo/routes.php"; then
        echo "Katalogroute ist in $product nicht vorhanden: $route" >&2
        exit 1
    fi
done

for consumer in \
    scripts/build-ad-suite-release.sh \
    scripts/install-ad-product-bundle.sh \
    scripts/verify-ad-suite-delivery.sh \
    tests/check-ad-suite-standalone-contract.sh; do
    if ! grep -Fq 'ad-product-catalog' "$workspace/$consumer"; then
        echo "Katalogverbrauch fehlt: $consumer" >&2
        exit 1
    fi
done

for document in ad-suite/README.md ad-suite/docs/ARCHITECTURE.md ad-suite/docs/INSTALLATION.md; do
    for product in "${products[@]}"; do
        if ! grep -Fq "$product" "$workspace/$document"; then
            echo "Katalogisiertes Produkt fehlt in öffentlicher Dokumentation: $document -> $product" >&2
            exit 1
        fi
    done
done
for contract in \
    'vollständigen AD-Suite-Archiv' \
    'eigenes Produktpaket' \
    'Navigation erteilt keine Rechte'; do
    if ! rg -Fq "$contract" "$workspace/ad-suite/README.md" "$workspace/ad-suite/docs/ARCHITECTURE.md" "$workspace/ad-suite/docs/INSTALLATION.md"; then
        echo "Öffentliche Katalog-/Bundle-Abgrenzung fehlt: $contract" >&2
        exit 1
    fi
done

echo 'AD-Produktkatalog-Vertrag: OK'
