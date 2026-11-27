// Opt-in DDEV-Browser-Smoke-Runner (Parent). Startet gegen einen laufenden
// headless Chrome (CDP-Port via FLZ_CDP_PORT) und fuehrt das app-spezifische
// Flow-Modul (FLZ_BROWSER_FLOW) mit dem gemeinsamen Client aus.
// Env: FLZ_CDP_PORT, FLZ_BROWSER_BASE_URL, FLZ_BROWSER_USER, FLZ_BROWSER_PASSWORD,
//      FLZ_BROWSER_APP, FLZ_BROWSER_FLOW, optional FLZ_BROWSER_SCREENSHOT.
import { pathToFileURL } from 'node:url';
import { CdpClient, createPage, makeApi, loginAndOpenApp } from './cdp-browser.mjs';

const required = ['FLZ_CDP_PORT', 'FLZ_BROWSER_BASE_URL', 'FLZ_BROWSER_USER', 'FLZ_BROWSER_PASSWORD', 'FLZ_BROWSER_APP', 'FLZ_BROWSER_FLOW'];
for (const name of required) {
    if (!process.env[name]) throw new Error(`${name} fehlt.`);
}

const port = Number(process.env.FLZ_CDP_PORT);
const baseUrl = process.env.FLZ_BROWSER_BASE_URL.replace(/\/$/, '');
const flowPath = process.env.FLZ_BROWSER_FLOW;

const target = await createPage(port);
const cdp = new CdpClient(target.webSocketDebuggerUrl);
await cdp.connect();
const api = makeApi(cdp);

try {
    await loginAndOpenApp(api, baseUrl, process.env.FLZ_BROWSER_USER, process.env.FLZ_BROWSER_PASSWORD, process.env.FLZ_BROWSER_APP);
    const flow = await import(pathToFileURL(flowPath).href);
    if (typeof flow.run !== 'function') throw new Error(`Flow-Modul ${flowPath} exportiert keine run(api).`);
    await flow.run(api);
    if (process.env.FLZ_BROWSER_SCREENSHOT) await api.screenshot(process.env.FLZ_BROWSER_SCREENSHOT);
    console.log(`Browser-Smoke fuer ${process.env.FLZ_BROWSER_APP}: OK`);
} finally {
    cdp.close();
}
