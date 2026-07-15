#!/usr/bin/env bash
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
release_label="${RELEASE_LABEL:-nc34-rc1}"
dist_root="${DIST_ROOT:-$workspace/dist}"
release_dir="$dist_root/ad-suite-$release_label"
bundle="$dist_root/ad-suite-$release_label.tar.gz"
apps=(localbase orgsuite adcalendar adplaner adurlaub adroom)
stage="$(mktemp -d)"

cleanup() {
    rm -rf "$stage"
}
trap cleanup EXIT

for command in git php node tar sha256sum; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Erforderlicher Befehl fehlt: $command" >&2
        exit 1
    fi
done

if [[ -e "$release_dir" || -e "$bundle" ]]; then
    echo "Releaseziel existiert bereits: $release_dir oder $bundle" >&2
    exit 1
fi
mkdir -p "$release_dir"

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

    php -r '
        $xml = simplexml_load_file($argv[1]);
        if ($xml === false || (string)$xml->id !== $argv[2]) exit(1);
        if ((string)$xml->dependencies->nextcloud["min-version"] !== "34") exit(2);
        if ((string)$xml->dependencies->nextcloud["max-version"] !== "34") exit(3);
        if (version_compare((string)$xml->dependencies->php["min-version"], "8.3", "<")) exit(4);
    ' "$info" "$app"

    if [[ "${SKIP_TESTS:-0}" != '1' ]]; then
        (cd "$repo" && php tests/run.php)
        (cd "$repo" && node tests/run-js.mjs)
    fi

    version="$(php -r '$xml=simplexml_load_file($argv[1]); echo (string)$xml->version;' "$info")"
    commit="$(git -C "$repo" rev-parse HEAD)"
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
    tar -C "$stage" -czf "$archive" "$app"
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
    printf '%s  %s\n' "$hash" "$(basename "$archive")" >> "$release_dir/SHA256SUMS"
    printf '%s\t%s\t%s\t%s\t%s\n' "$app" "$version" "$commit" "$hash" "$signed" >> "$release_dir/manifest.tsv"
done

cp "$workspace/ad-suite/docs/INSTALLATION.md" "$release_dir/INSTALLATION.md"
cp "$workspace/ad-suite/docs/OPERATIONS.md" "$release_dir/BETRIEB-UND-RUECKBAU.md"
cp "$workspace/ad-suite/docs/ACCEPTANCE.md" "$release_dir/ABNAHMEPROTOKOLL.md"
cp "$workspace/ad-suite/docs/DELIVERY-GATE.md" "$release_dir/DELIVERY-GATE.md"
(cd "$release_dir" && sha256sum --check SHA256SUMS)
tar -C "$dist_root" -czf "$bundle" "$(basename "$release_dir")"
(cd "$dist_root" && sha256sum "$(basename "$bundle")" > "$(basename "$bundle").sha256")

echo "AD-Suite-Release erstellt:"
echo "  $release_dir"
echo "  $bundle"
echo "  $bundle.sha256"
