#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog="$workspace/localbase/resources/flz-product-catalog.json"
reader="$workspace/scripts/read-flz-product-catalog.php"

[[ -f "$catalog" ]] || { echo 'Kanonischer FLZ-Produktkatalog fehlt.' >&2; exit 1; }
[[ -f "$reader" ]] || { echo 'Maschinenlesbarer FLZ-Produktkatalog-Reader fehlt.' >&2; exit 1; }

php "$reader" validate >/dev/null
mapfile -t products < <(php "$reader" products)
expected=(flzcalendar flzplaner flzurlaub flzroom flzrecruitment flzbqplanung)
[[ "${products[*]}" == "${expected[*]}" ]] || { echo 'FLZ-Produktliste oder Reihenfolge weicht ab.' >&2; exit 1; }

mapfile -t full_suite < <(php "$reader" full-suite)
expected_full=(localbase orgsuite flzcalendar flzplaner flzurlaub flzroom flzrecruitment)
[[ "${full_suite[*]}" == "${expected_full[*]}" ]] || { echo 'Vollständige Suite enthält nicht alle katalogisierten Apps.' >&2; exit 1; }

mapfile -t bundle_products < <(php "$reader" bundle-products)
[[ "${bundle_products[*]}" == 'flzcalendar flzplaner flzurlaub flzroom flzrecruitment' ]] || { echo 'Nicht freigegebene Produkte gelangen in Produktbundles.' >&2; exit 1; }

mapfile -t recruitment_bundle < <(php "$reader" product-bundle flzrecruitment)
[[ "${recruitment_bundle[*]}" == 'localbase orgsuite flzrecruitment' ]] || { echo 'Recruitment-Produktbundle ist falsch zusammengesetzt.' >&2; exit 1; }
if php "$reader" product-bundle flzbqplanung >/dev/null 2>&1; then
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
    scripts/build-flz-full-suite-release.sh \
    scripts/install-flz-product-bundle.sh \
    scripts/verify-flz-full-suite-delivery.sh \
    tests/check-flz-full-suite-standalone-contract.sh; do
    if ! grep -Fq 'flz-product-catalog' "$workspace/$consumer"; then
        echo "Katalogverbrauch fehlt: $consumer" >&2
        exit 1
    fi
done

for document in flz-full-suite/README.md flz-full-suite/docs/ARCHITECTURE.md flz-full-suite/docs/INSTALLATION.md; do
    for product in "${products[@]}"; do
        if ! grep -Fq "$product" "$workspace/$document"; then
            echo "Katalogisiertes Produkt fehlt in öffentlicher Dokumentation: $document -> $product" >&2
            exit 1
        fi
    done
done
for contract in \
    'vollständigen Archiv der Filzmann Full Suite' \
    'eigenes Produktpaket' \
    'Navigation erteilt keine Rechte'; do
    if ! rg -Fq "$contract" "$workspace/flz-full-suite/README.md" "$workspace/flz-full-suite/docs/ARCHITECTURE.md" "$workspace/flz-full-suite/docs/INSTALLATION.md"; then
        echo "Öffentliche Katalog-/Bundle-Abgrenzung fehlt: $contract" >&2
        exit 1
    fi
done

echo 'FLZ-Produktkatalog-Vertrag: OK'
