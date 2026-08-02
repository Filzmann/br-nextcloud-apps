#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
catalog_reader="$workspace/scripts/read-ad-product-catalog.php"
php "$catalog_reader" validate >/dev/null
mapfile -t products < <(php "$catalog_reader" products)

for app in localbase orgsuite "${products[@]}"; do
    info="$workspace/$app/appinfo/info.xml"
    if grep -Eq '<app([[:space:]>])' "$info"; then
        echo "Nicht unterstützte App-Abhängigkeit in $app/appinfo/info.xml" >&2
        exit 1
    fi
done

for app in "${products[@]}"; do
    template="$workspace/$app/templates/index.php"
    application="$workspace/$app/lib/AppInfo/Application.php"
    listener="$workspace/$app/lib/Listener/StandaloneNavigationListener.php"

    if grep -Fq "addScript('orgsuite'" "$template" || grep -Fq "addStyle('orgsuite'" "$template"; then
        echo "Direkte OrgSuite-Assetkopplung in $app/templates/index.php" >&2
        exit 1
    fi
    if [[ ! -f "$listener" ]] || ! grep -Fq 'StandaloneAppNavigationService' "$listener"; then
        echo "Standalone-Navigation fehlt in $app" >&2
        exit 1
    fi
    if ! grep -Fq 'LoadAdditionalEntriesEvent::class' "$application"; then
        echo "Standalone-Navigation ist in $app nicht registriert" >&2
        exit 1
    fi
done

org_application="$workspace/orgsuite/lib/AppInfo/Application.php"
org_assets="$workspace/orgsuite/lib/Listener/SuiteAssetsListener.php"
if [[ ! -f "$org_assets" ]] || ! grep -Fq 'BeforeTemplateRenderedEvent::class' "$org_application"; then
    echo 'Zentrale OrgSuite-Assetregistrierung fehlt.' >&2
    exit 1
fi

echo 'AD-Suite-Standalone-Vertrag: OK'
