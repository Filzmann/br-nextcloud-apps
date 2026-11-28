#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog="$workspace/orgsuite/resources/flz-product-catalog.json"
reader="$workspace/scripts/read-flz-product-catalog.php"

[[ -f "$catalog" ]] || { echo 'Kanonischer FLZ-Produktkatalog fehlt.' >&2; exit 1; }
[[ -f "$reader" ]] || { echo 'Maschinenlesbarer FLZ-Produktkatalog-Reader fehlt.' >&2; exit 1; }

# Solange LocalBase noch eine eigene Kopie ausliefert, darf diese nicht von der
# kanonischen OrgSuite-Quelle abweichen; beim LocalBase-Retirement entfällt die Kopie.
legacy_copy="$workspace/localbase/resources/flz-product-catalog.json"
if [[ -f "$legacy_copy" ]] && ! cmp -s "$legacy_copy" "$catalog"; then
    echo 'LocalBase-Kopie des FLZ-Produktkatalogs weicht von der kanonischen OrgSuite-Quelle ab.' >&2
    exit 1
fi

php "$reader" validate >/dev/null
mapfile -t products < <(php "$reader" products)
expected_navigation=(flzcalendar flzplaner flzurlaub flzroom flzrecruitment flzbqplanung flz_akut)
[[ "${products[*]}" == "${expected_navigation[*]}" ]] || { echo 'FLZ-Navigationsproduktliste oder Reihenfolge weicht ab.' >&2; exit 1; }

mapfile -t full_suite < <(php "$reader" full-suite)
expected_full=(localbase orgsuite flzcalendar flzplaner flzurlaub flzroom flzrecruitment flz_akut)
[[ "${full_suite[*]}" == "${expected_full[*]}" ]] || { echo 'Vollständige Suite enthält nicht alle katalogisierten Apps.' >&2; exit 1; }

mapfile -t bundle_products < <(php "$reader" bundle-products)
[[ "${bundle_products[*]}" == 'flzcalendar flzplaner flzurlaub flzroom flzrecruitment flz_akut' ]] || { echo 'Freigegebene und ausgeschlossene Produktbundles weichen vom Katalogvertrag ab.' >&2; exit 1; }

mapfile -t recruitment_bundle < <(php "$reader" product-bundle flzrecruitment)
[[ "${recruitment_bundle[*]}" == 'localbase orgsuite flzrecruitment' ]] || { echo 'Recruitment-Produktbundle ist falsch zusammengesetzt.' >&2; exit 1; }
mapfile -t acute_bundle < <(php "$reader" product-bundle flz_akut)
[[ "${acute_bundle[*]}" == 'localbase orgsuite flz_akut' ]] || { echo 'Akut-Produktbundle ist falsch zusammengesetzt.' >&2; exit 1; }
if php "$reader" product-bundle flzbqplanung >/dev/null 2>&1; then
    echo 'flzbqplanung besitzt vor seiner Releasefreigabe unerwartet ein Produktbundle.' >&2
    exit 1
fi

for product in "${products[@]}"; do
    route="$(php "$reader" field "$product" route)"
    route_suffix="${route#"$product".}"
    route_name="${route_suffix/./#}"
    if ! rg -q -- "'name'[[:space:]]*=>[[:space:]]*'$route_name'" "$workspace/$product/appinfo/routes.php"; then
        echo "Katalogroute ist in $product nicht vorhanden: $route" >&2
        exit 1
    fi
    if [[ "$(php "$reader" field "$product" menu)" != 'true' ]]; then
        echo "Katalogisiertes FLZ-Produkt ist nicht fuer die Navigation freigegeben: $product" >&2
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
    for product in "${bundle_products[@]}"; do
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
