// Wiederverwendbarer CDP-Client fuer den opt-in DDEV-Browser-Smoke.
// Dependency-free (Node >= 22: globales fetch + WebSocket). App-spezifische
// Flows bleiben app-lokal und werden als Modul an runBrowserSmoke() gereicht.
// Nutzung: scripts/browser-ddev-smoke.sh (Chrome-Starter) ruft
// scripts/js/browser-ddev-smoke.mjs auf, das diesen Client + das app-Flow-Modul
// ausfuehrt. Nicht Teil des schnellen Standardlaufs.

export class CdpClient {
    constructor(webSocketDebuggerUrl) {
        this.nextId = 1;
        this.pending = new Map();
        this.socket = new WebSocket(webSocketDebuggerUrl);
    }

    async connect() {
        await new Promise((resolve, reject) => {
            this.socket.addEventListener('open', resolve, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });
        this.socket.addEventListener('message', event => {
            const message = JSON.parse(String(event.data));
            if (!message.id || !this.pending.has(message.id)) return;
            const { resolve, reject } = this.pending.get(message.id);
            this.pending.delete(message.id);
            if (message.error) reject(new Error(`${message.error.message} (${message.error.code})`));
            else resolve(message.result || {});
        });
    }

    call(method, params = {}) {
        const id = this.nextId++;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    close() {
        this.socket.close();
    }
}

export const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

export async function createPage(port) {
    const response = await fetch(`http://127.0.0.1:${port}/json/new?about%3Ablank`, { method: 'PUT' });
    if (!response.ok) throw new Error(`Chrome-Ziel konnte nicht angelegt werden: HTTP ${response.status}`);
    return response.json();
}

export function makeApi(cdp) {
    const evaluate = async expression => {
        const result = await cdp.call('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) {
            throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'JavaScript-Auswertung fehlgeschlagen.');
        }
        return result.result?.value;
    };
    const waitFor = async (expression, label, timeout = 20000) => {
        const deadline = Date.now() + timeout;
        let lastError = null;
        while (Date.now() < deadline) {
            try {
                if (await evaluate(`Boolean(${expression})`)) return;
            } catch (error) {
                lastError = error;
            }
            await sleep(100);
        }
        throw new Error(`Zeitlimit fuer ${label || 'Bedingung'} abgelaufen.${lastError ? ` Letzter Fehler: ${lastError.message}` : ''}`);
    };
    const navigate = async url => {
        await cdp.call('Page.enable');
        await cdp.call('Page.navigate', { url });
        await waitFor('document.readyState === "complete"', `Navigation nach ${url}`);
    };
    const screenshot = async path => {
        const { data } = await cdp.call('Page.captureScreenshot', { format: 'png' });
        const { writeFile } = await import('node:fs/promises');
        await writeFile(path, Buffer.from(data, 'base64'));
    };
    return { evaluate, waitFor, navigate, screenshot };
}

// Login gegen die Nextcloud-Instanz inkl. CSRF-Requesttoken; liefert die
// geoeffnete App-Seite. Schlaegt fehl, wenn Login oder App-Aufruf nicht greifen.
export async function loginAndOpenApp(api, baseUrl, user, password, appId) {
    await api.navigate(`${baseUrl}/login`);
    const token = await api.evaluate(`document.querySelector('head[data-requesttoken]')?.getAttribute('data-requesttoken') || document.body.innerHTML.match(/data-requesttoken="([^"]+)"/)?.[1] || ''`);
    if (!token) throw new Error('Kein CSRF-Requesttoken auf der Login-Seite gefunden.');
    const loginResult = await api.evaluate(`(async () => {
        const response = await fetch('/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'OCS-APIRequest': 'true' },
            body: new URLSearchParams({ user: ${JSON.stringify(user)}, password: ${JSON.stringify(password)}, requesttoken: ${JSON.stringify(token)} }),
            credentials: 'include',
        });
        return { status: response.status, url: location.href, redirected: response.redirected };
    })()`);
    await api.navigate(`${baseUrl}/apps/${appId}/`);
    await api.waitFor(`location.pathname.includes('/apps/${appId}/')`, `App-Oeffnung ${appId}`, 30000);
    return loginResult;
}
