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

# Execute the existing optional inventory stage in isolation. This checks its
# process boundary without building release archives or starting real DDEV.
python3 - "$workspace/scripts/verify-ad-suite-delivery.sh" <<'PY'
import os
from pathlib import Path
import subprocess
import sys
import tempfile

source = Path(sys.argv[1]).read_text()
stage = source.split('if [[ "${RUN_DDEV_CHECKS:-0}"', 1)[1]
stage = 'if [[ "${RUN_DDEV_CHECKS:-0}"' + stage.split('if [[ "${RUN_HTTP_SMOKES:-0}"', 1)[0]
with tempfile.TemporaryDirectory() as temporary:
    root = Path(temporary)
    (root / 'nextcloud-dev').mkdir()
    ddev = root / 'ddev'
    ddev.write_text('''#!/usr/bin/env bash
set -eu
printf '%s\\n' "$*" >> "$CALL_LOG"
case "$*" in
    *'occ status') exit 0 ;;
    *'occ app:list')
        [[ "${FAIL_LIST:-0}" == 0 ]] || exit 42
        printf '%s\\n' "$APP_LIST" ;;
    *) exit 99 ;;
esac
''')
    ddev.chmod(0o755)
    log = root / 'calls'
    env = dict(os.environ, PATH=f'{root}:{os.environ["PATH"]}',
               workspace=str(root), CALL_LOG=str(log), RUN_DDEV_CHECKS='1')
    command = 'set -euo pipefail\napps=(alpha beta gamma)\n' + stage
    for label, inventory, fail_list, enabled, expected in (
        ('complete', 'alpha\nbeta\ngamma', '0', '1', 0),
        ('missing', 'alpha\ngamma', '0', '1', 1),
        ('failed', 'alpha\nbeta\ngamma', '1', '1', 42),
        ('opt-out', '', '0', '0', 0),
    ):
        log.write_text('')
        result = subprocess.run(['bash', '-c', command], env=dict(
            env, APP_LIST=inventory, FAIL_LIST=fail_list, RUN_DDEV_CHECKS=enabled),
            capture_output=True, text=True)
        assert result.returncode == expected, (label, result.returncode, result.stderr)
        calls = log.read_text().splitlines()
        expected_calls = 0 if enabled == '0' else 1
        assert sum('occ app:list' in call for call in calls) == expected_calls, (
            label, 'DDEV app:list must run exactly once per inventory stage', calls)
        assert sum('occ status' in call for call in calls) == expected_calls, (label, calls)
        if label == 'complete':
            assert all(app in result.stdout.splitlines() for app in ('alpha', 'beta', 'gamma'))
PY

echo 'AD-Suite-Standalone-Vertrag: OK'
