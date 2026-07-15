#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
apps=(localbase orgsuite adcalendar adplaner adurlaub adroom)
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

for document in README.md LICENSE SECURITY.md docs/INSTALLATION.md docs/OPERATIONS.md docs/ACCEPTANCE.md docs/DELIVERY-GATE.md; do
    if [[ ! -f "$workspace/ad-suite/$document" ]]; then
        echo "Öffentliche Suite-Dokumentation fehlt: $document" >&2
        exit 1
    fi
done

echo '== Suite: Coverage-Baseline-Vertrag =='
bash "$workspace/tests/check-ad-suite-coverage-baseline.sh"
echo '== Suite: GitHub-CI-Vertrag =='
bash "$workspace/tests/check-ad-suite-ci-contract.sh"

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

echo 'AD-Suite Delivery-Gate: OK'
