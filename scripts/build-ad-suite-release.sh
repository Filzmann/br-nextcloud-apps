#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
nextcloud_target_major="${NEXTCLOUD_TARGET_MAJOR:-34}"
release_label="${RELEASE_LABEL:-nc${nextcloud_target_major}-rc1}"
dist_root="${DIST_ROOT:-$workspace/dist}"
release_dir="$dist_root/ad-suite-$release_label"
bundle="$dist_root/ad-suite-$release_label.tar.gz"
catalog="$workspace/localbase/resources/ad-product-catalog.json"
catalog_reader="$workspace/scripts/read-ad-product-catalog.php"
reproducible_archiver="$workspace/scripts/create-reproducible-tar-gz.sh"
support_range_checker="$workspace/scripts/validate-nextcloud-support-range.php"
security_checker="$workspace/scripts/check-security-compliance"
sbom_generator="$workspace/scripts/generate-cyclonedx-sbom.php"
evidence_generator="$workspace/scripts/generate-release-evidence.php"
security_scanner="$workspace/scripts/run-security-scanners"
collision_guard="$workspace/scripts/assert-release-output-available.php"
php "$catalog_reader" validate >/dev/null
mapfile -t apps < <(php "$catalog_reader" full-suite)
mapfile -t products < <(php "$catalog_reader" bundle-products)
declare -A archives=()
declare -A commit_epochs=()

for command in git php node tar sha256sum; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Erforderlicher Befehl fehlt: $command" >&2
        exit 1
    fi
done

if [[ "${SECURITY_SCANNER_TEST_MODE:-0}" != '0' ]]; then
    echo 'SECURITY_SCANNER_TEST_MODE ist im Release-Builder unzulässig.' >&2
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
release_mode='candidate'
dirty_sources='false'
if [[ "${DIAGNOSTIC_MODE:-0}" == '1' ]]; then
    release_mode='diagnostic'
    dirty_sources='true'
fi

"$security_checker" >/dev/null
mapping_status='passed'

source_timestamp_for() {
    local latest=0
    local app epoch
    for app in "$@"; do
        epoch="${commit_epochs[$app]}"
        if (( epoch > latest )); then
            latest="$epoch"
        fi
    done
    php -r 'echo gmdate("c", (int) $argv[1]);' "$latest"
}

if [[ "${SKIP_TESTS:-0}" == '1' ]]; then
    builder_test_status='skipped-by-builder'
else
    builder_test_status='passed-by-builder'
fi

release_evidence="$dist_root/ad-suite-$release_label.release-evidence.json"
release_targets=(
    "$release_dir"
    "$bundle"
    "$bundle.sha256"
    "$release_evidence"
    "$release_evidence.sha256"
    "$release_dir/sbom.cdx.json"
)
for product in "${products[@]}"; do
    product_name="ad-product-$product-$release_label"
    product_dir="$dist_root/$product_name"
    product_bundle="$dist_root/$product_name.tar.gz"
    product_evidence="$dist_root/$product_name.release-evidence.json"
    release_targets+=(
        "$product_dir"
        "$product_bundle"
        "$product_bundle.sha256"
        "$product_evidence"
        "$product_evidence.sha256"
        "$product_dir/sbom.cdx.json"
    )
done
php "$collision_guard" "${release_targets[@]}"

stage="$(mktemp -d)"
cleanup() {
    rm -rf "$stage"
}
trap cleanup EXIT

security_scan_evidence="$stage/security-scans.json"
"$security_scanner" --evidence-file "$security_scan_evidence"

mkdir -p "$release_dir"
cp "$catalog" "$release_dir/ad-product-catalog.json"

printf 'app\tversion\tgit_commit\tsha256\tsigned\n' > "$release_dir/manifest.tsv"

for app in "${apps[@]}"; do
    repo="$workspace/$app"
    info="$repo/appinfo/info.xml"
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
        if ($xml === false || (string)$xml->id !== $argv[2]) exit(1);
        if (version_compare((string)$xml->dependencies->php["min-version"], "8.3", "<")) exit(4);
    ' "$info" "$app"

    if [[ "${SKIP_TESTS:-0}" != '1' ]]; then
        (cd "$repo" && php tests/run.php)
        (cd "$repo" && node tests/run-js.mjs)
    fi

    version="$(php -r '$xml=simplexml_load_file($argv[1]); echo (string)$xml->version;' "$info")"
    commit="$(git -C "$repo" rev-parse HEAD)"
    commit_epochs[$app]="$(git -C "$repo" show -s --format=%ct HEAD)"
    app_stage="$stage/$app"
    mkdir -p "$app_stage"
    work_tar="$stage/$app.work.tar"
    tar -C "$repo" \
        --exclude='./.git' \
        --exclude='./.gitignore' \
        --exclude='./.github' \
        --exclude='./.agents' \
        --exclude='./.codex' \
        --exclude='./AGENTS.md' \
        --exclude='./tests' \
        --exclude='./node_modules' \
        --exclude='./vendor' \
        -cf "$work_tar" .
    tar -C "$app_stage" -xf "$work_tar"
    rm -f "$work_tar"

    if find "$app_stage" -type l -print -quit | grep -q .; then
        echo "Symlink im Releaseinhalt gefunden: $app" >&2
        exit 1
    fi
    for required in appinfo/info.xml LICENSE README.md CHANGELOG.md; do
        if [[ ! -f "$app_stage/$required" ]]; then
            echo "Releasepflichtdatei fehlt in $app: $required" >&2
            exit 1
        fi
    done

    signed='no'
    if [[ -n "${SIGNING_KEY_DIR:-}" ]]; then
        : "${NEXTCLOUD_ROOT:?NEXTCLOUD_ROOT fehlt für signierte Releases}"
        key="$SIGNING_KEY_DIR/$app.key"
        certificate="$SIGNING_KEY_DIR/$app.crt"
        if [[ ! -f "$key" || ! -f "$certificate" ]]; then
            echo "Signaturschlüssel oder Zertifikat fehlt für $app" >&2
            exit 1
        fi
        "${PHP_BIN:-php}" "$NEXTCLOUD_ROOT/occ" integrity:sign-app \
            --privateKey="$key" --certificate="$certificate" --path="$app_stage"
        signed='yes'
    fi

    archive="$release_dir/$app-$version.tar.gz"
    "$reproducible_archiver" "$archive" "$stage" "$app"
    mapfile -t roots < <(tar -tzf "$archive" | cut -d/ -f1 | sort -u)
    if [[ "${#roots[@]}" -ne 1 || "${roots[0]}" != "$app" ]]; then
        echo "Archiv besitzt keinen eindeutigen App-Wurzelordner: $archive" >&2
        exit 1
    fi
    if tar -tzf "$archive" | grep -Eq "/(\.git|tests|AGENTS\.md)(/|$)"; then
        echo "Entwicklungsdateien im Releasearchiv gefunden: $archive" >&2
        exit 1
    fi
    hash="$(sha256sum "$archive" | cut -d' ' -f1)"
    archives[$app]="$archive"
    printf '%s  %s\n' "$hash" "$(basename "$archive")" >> "$release_dir/SHA256SUMS"
    printf '%s\t%s\t%s\t%s\t%s\n' "$app" "$version" "$commit" "$hash" "$signed" >> "$release_dir/manifest.tsv"
done

php "$sbom_generator" \
    --manifest "$release_dir/manifest.tsv" \
    --output "$release_dir/sbom.cdx.json" \
    --name ad-suite \
    --version "$release_label" \
    --nextcloud-major "$nextcloud_target_major" \
    --repository-root "$workspace"
sbom_hash="$(sha256sum "$release_dir/sbom.cdx.json" | cut -d' ' -f1)"
printf '%s  %s\n' "$sbom_hash" 'sbom.cdx.json' >> "$release_dir/SHA256SUMS"

catalog_hash="$(sha256sum "$release_dir/ad-product-catalog.json" | cut -d' ' -f1)"
printf '%s  %s\n' "$catalog_hash" 'ad-product-catalog.json' >> "$release_dir/SHA256SUMS"

cp "$workspace/ad-suite/docs/INSTALLATION.md" "$release_dir/INSTALLATION.md"
cp "$workspace/ad-suite/docs/OPERATIONS.md" "$release_dir/BETRIEB-UND-RUECKBAU.md"
cp "$workspace/ad-suite/docs/ACCEPTANCE.md" "$release_dir/ABNAHMEPROTOKOLL.md"
cp "$workspace/ad-suite/docs/DELIVERY-GATE.md" "$release_dir/DELIVERY-GATE.md"
cp "$workspace/ad-suite/docs/LDAP-UNIVENTION.md" "$release_dir/LDAP-UNIVENTION.md"
cp "$workspace/scripts/install-ad-product-bundle.sh" "$release_dir/install.sh"
chmod +x "$release_dir/install.sh"
(cd "$release_dir" && sha256sum --check SHA256SUMS)

for product in "${products[@]}"; do
    product_name="ad-product-$product-$release_label"
    product_dir="$dist_root/$product_name"
    product_bundle="$dist_root/$product_name.tar.gz"
    mkdir -p "$product_dir"

    mapfile -t product_apps < <(php "$catalog_reader" product-bundle "$product")
    for app in "${product_apps[@]}"; do
        cp "${archives[$app]}" "$product_dir/"
    done
    cp "$catalog" "$product_dir/ad-product-catalog.json"
    cp "$workspace/scripts/install-ad-product-bundle.sh" "$product_dir/install.sh"
    chmod +x "$product_dir/install.sh"
    cp "$workspace/ad-suite/docs/INSTALLATION.md" "$product_dir/INSTALLATION.md"
    cp "$workspace/ad-suite/docs/OPERATIONS.md" "$product_dir/BETRIEB-UND-RUECKBAU.md"
    cp "$workspace/ad-suite/docs/ACCEPTANCE.md" "$product_dir/ABNAHMEPROTOKOLL.md"
    cp "$workspace/ad-suite/docs/DELIVERY-GATE.md" "$product_dir/DELIVERY-GATE.md"
    cp "$workspace/ad-suite/docs/LDAP-UNIVENTION.md" "$product_dir/LDAP-UNIVENTION.md"

    printf 'app\tversion\tgit_commit\tsha256\tsigned\n' > "$product_dir/manifest.tsv"
    for app in "${product_apps[@]}"; do
        awk -F '\t' -v app="$app" '$1 == app { print }' "$release_dir/manifest.tsv" >> "$product_dir/manifest.tsv"
    done
    php "$sbom_generator" \
        --manifest "$product_dir/manifest.tsv" \
        --output "$product_dir/sbom.cdx.json" \
        --name "ad-product-$product" \
        --version "$release_label" \
        --nextcloud-major "$nextcloud_target_major" \
        --repository-root "$workspace"
    (cd "$product_dir" && sha256sum ./*.tar.gz ad-product-catalog.json sbom.cdx.json > SHA256SUMS && sha256sum --check SHA256SUMS)
    "$reproducible_archiver" "$product_bundle" "$dist_root" "$product_name"
    (cd "$dist_root" && sha256sum "$product_name.tar.gz" > "$product_name.tar.gz.sha256")

    product_bundle_hash="$(sha256sum "$product_bundle" | cut -d' ' -f1)"
    product_sbom_hash="$(sha256sum "$product_dir/sbom.cdx.json" | cut -d' ' -f1)"
    product_timestamp="$(source_timestamp_for "${product_apps[@]}")"
    product_evidence="$dist_root/$product_name.release-evidence.json"
    php "$evidence_generator" \
        --manifest "$product_dir/manifest.tsv" \
        --output "$product_evidence" \
        --release-id "$product_name" \
        --source-timestamp "$product_timestamp" \
        --mapping-status "$mapping_status" \
        --test-status "$builder_test_status" \
        --release-mode "$release_mode" \
        --dirty-sources "$dirty_sources" \
        --security-scan-evidence "$security_scan_evidence" \
        --sbom-file "$product_name/sbom.cdx.json" \
        --sbom-sha256 "$product_sbom_hash" \
        --artifact-file "$product_name.tar.gz" \
        --artifact-sha256 "$product_bundle_hash"
    (cd "$dist_root" && sha256sum "$product_name.release-evidence.json" > "$product_name.release-evidence.json.sha256")
done

"$reproducible_archiver" "$bundle" "$dist_root" "$(basename "$release_dir")"
(cd "$dist_root" && sha256sum "$(basename "$bundle")" > "$(basename "$bundle").sha256")

bundle_hash="$(sha256sum "$bundle" | cut -d' ' -f1)"
release_timestamp="$(source_timestamp_for "${apps[@]}")"
php "$evidence_generator" \
    --manifest "$release_dir/manifest.tsv" \
    --output "$release_evidence" \
    --release-id "ad-suite-$release_label" \
    --source-timestamp "$release_timestamp" \
    --mapping-status "$mapping_status" \
    --test-status "$builder_test_status" \
    --release-mode "$release_mode" \
    --dirty-sources "$dirty_sources" \
    --security-scan-evidence "$security_scan_evidence" \
    --sbom-file "ad-suite-$release_label/sbom.cdx.json" \
    --sbom-sha256 "$sbom_hash" \
    --artifact-file "$(basename "$bundle")" \
    --artifact-sha256 "$bundle_hash"
(cd "$dist_root" && sha256sum "$(basename "$release_evidence")" > "$(basename "$release_evidence").sha256")

echo "AD-Suite-Release erstellt:"
echo "  $release_dir"
echo "  $bundle"
echo "  $bundle.sha256"
echo "  $release_evidence"
echo "  $release_evidence.sha256"
for product in "${products[@]}"; do
    echo "  $dist_root/ad-product-$product-$release_label.tar.gz"
    echo "  $dist_root/ad-product-$product-$release_label.release-evidence.json"
done
