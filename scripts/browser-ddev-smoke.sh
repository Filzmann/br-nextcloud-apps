#!/usr/bin/env bash
# Opt-in DDEV-Browser-Smoke (Parent-Baustein, freigegeben 27.11.2026).
# Startet headless Chrome und ruft scripts/js/browser-ddev-smoke.mjs auf, das
# Login + App-Oeffnung (gemeinsam) und das app-spezifische Flow-Modul ausfuehrt.
# Nicht Teil des schnellen Standardlaufs; Aufruf aus dem jeweiligen App-Repo:
#
#   FLZ_BROWSER_USER=... FLZ_BROWSER_PASSWORD=... \
#   FLZ_BROWSER_APP=flzplaner FLZ_BROWSER_FLOW="$PWD/tests/js/browser-ddev-smoke.mjs" \
#   ../br-nextcloud-apps/scripts/browser-ddev-smoke.sh
#
# Env: FLZ_BROWSER_BASE_URL (Default https://nextcloud-dev.ddev.site),
#      FLZ_BROWSER_USER, FLZ_BROWSER_PASSWORD, FLZ_BROWSER_APP,
#      FLZ_BROWSER_FLOW (Pfad zum app-lokalen Flow-Modul mit export run(api)),
#      optional FLZ_BROWSER_SCREENSHOT.
set -euo pipefail

workspace="$(cd "$(dirname "$0")/.." && pwd)"
base_url="${FLZ_BROWSER_BASE_URL:-https://nextcloud-dev.ddev.site}"
for name in FLZ_BROWSER_USER FLZ_BROWSER_PASSWORD FLZ_BROWSER_APP FLZ_BROWSER_FLOW; do
    if [[ -z "${!name:-}" ]]; then
        echo "$name fehlt." >&2
        exit 2
    fi
done
if [[ ! -f "$FLZ_BROWSER_FLOW" ]]; then
    echo "Flow-Modul $FLZ_BROWSER_FLOW existiert nicht." >&2
    exit 2
fi

workdir="$(mktemp -d)"
chrome_profile="$workdir/chrome-profile"
chrome_log="$workdir/chrome.log"
chrome_pid=''
cleanup() {
    if [[ -n "$chrome_pid" ]] && kill -0 "$chrome_pid" >/dev/null 2>&1; then
        kill "$chrome_pid" >/dev/null 2>&1 || true
        wait "$chrome_pid" >/dev/null 2>&1 || true
    fi
    rm -rf "$workdir"
}
trap cleanup EXIT

mkdir -p "$chrome_profile"
google-chrome \
    --headless=new \
    --disable-dev-shm-usage \
    --ignore-certificate-errors \
    --remote-allow-origins='*' \
    --remote-debugging-port=0 \
    --user-data-dir="$chrome_profile" \
    about:blank >"$chrome_log" 2>&1 &
chrome_pid=$!

for _ in $(seq 1 100); do
    [[ -s "$chrome_profile/DevToolsActivePort" ]] && break
    if ! kill -0 "$chrome_pid" >/dev/null 2>&1; then
        echo 'Headless Chrome wurde unerwartet beendet.' >&2
        sed -n '1,120p' "$chrome_log" >&2
        exit 1
    fi
    sleep 0.1
done
if [[ ! -s "$chrome_profile/DevToolsActivePort" ]]; then
    echo 'Chrome stellte innerhalb des Zeitlimits keinen DevTools-Port bereit.' >&2
    exit 1
fi
cdp_port="$(sed -n '1p' "$chrome_profile/DevToolsActivePort")"

FLZ_CDP_PORT="$cdp_port" \
FLZ_BROWSER_BASE_URL="$base_url" \
FLZ_BROWSER_USER="$FLZ_BROWSER_USER" \
FLZ_BROWSER_PASSWORD="$FLZ_BROWSER_PASSWORD" \
FLZ_BROWSER_APP="$FLZ_BROWSER_APP" \
FLZ_BROWSER_FLOW="$FLZ_BROWSER_FLOW" \
FLZ_BROWSER_SCREENSHOT="${FLZ_BROWSER_SCREENSHOT:-$workdir/screenshot.png}" \
node "$workspace/scripts/js/browser-ddev-smoke.mjs"
