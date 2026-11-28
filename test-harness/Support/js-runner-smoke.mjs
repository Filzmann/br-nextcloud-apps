import { spawnSync } from 'node:child_process';
import {
    cpSync,
    existsSync,
    mkdirSync,
    mkdtempSync,
    rmSync,
    writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const workspaceRoot = dirname(dirname(dirname(fileURLToPath(import.meta.url))));
const harnessRoot = join(workspaceRoot, 'test-harness');
const workspace = mkdtempSync(join(tmpdir(), 'flz-js-runner-contract.'));

try {
    const harnessFixture = join(workspace, 'test-harness');
    const consumerRoot = join(workspace, 'consumer');
    // Harness-Fixture ohne js/-Verzeichnis: Der Runner darf ein fehlendes js/ tolerieren.
    mkdirSync(join(harnessFixture, 'js'), { recursive: true });
    mkdirSync(join(consumerRoot, 'js'), { recursive: true });
    mkdirSync(join(consumerRoot, 'tests', 'js'), { recursive: true });

    cpSync(
        join(harnessRoot, 'js', 'helpers'),
        join(harnessFixture, 'js', 'helpers'),
        { recursive: true },
    );
    writeFileSync(join(consumerRoot, 'js', 'consumer.js'), 'globalThis.ConsumerFixture = __filename;\n');
    writeFileSync(join(consumerRoot, 'tests', 'sibling-contract.txt'), 'available\n');
    writeFileSync(
        join(consumerRoot, 'tests', 'js', 'shared-smoke.js'),
        [
            'global.window = {};',
            "const { readFileSync } = require('node:fs');",
            "const { join } = require('node:path');",
            "require('../../js/consumer.js');",
            `if (globalThis.ConsumerFixture !== ${JSON.stringify(join(consumerRoot, 'js', 'consumer.js'))}) throw new Error('App source was not executed from its coverage-attributed path');`,
            "const { FakeElement } = require('../../../test-harness/js/helpers/fake-dom.js');",
            "if (typeof FakeElement !== 'function') throw new Error('Test-harness helper missing');",
            "if (readFileSync(join(__dirname, '..', 'sibling-contract.txt'), 'utf8').trim() !== 'available') throw new Error('App-local test fixture missing');",
            '',
        ].join('\n'),
    );

    const runnerUrl = pathToFileURL(join(harnessRoot, 'Support', 'js-runner.mjs')).href;
    const entrypoint = join(consumerRoot, 'run.mjs');
    writeFileSync(
        entrypoint,
        [
            `import { runJavaScriptSuite } from ${JSON.stringify(runnerUrl)};`,
            `runJavaScriptSuite({ root: ${JSON.stringify(consumerRoot)}, testFiles: ['tests/js/shared-smoke.js'] });`,
            '',
        ].join('\n'),
    );

    const result = spawnSync(process.execPath, [entrypoint], {
        cwd: consumerRoot,
        encoding: 'utf8',
    });
    if (result.status !== 0) {
        throw new Error(`Shared test-harness fixture failed in isolation:\n${result.stderr || result.stdout}`);
    }

    // Runner ohne js/-Verzeichnis im eigenen Root darf nicht abstürzen.
    const bareEntrypoint = join(harnessFixture, 'run-bare.mjs');
    writeFileSync(
        bareEntrypoint,
        [
            `import { runJavaScriptSuite } from ${JSON.stringify(runnerUrl)};`,
            `runJavaScriptSuite({ root: ${JSON.stringify(harnessFixture)}, testFiles: [], successMessage: 'bare ok' });`,
            '',
        ].join('\n'),
    );
    const bare = spawnSync(process.execPath, [bareEntrypoint], { cwd: harnessFixture, encoding: 'utf8' });
    if (bare.status !== 0 || !bare.stdout.includes('bare ok')) {
        throw new Error(`Runner toleriert fehlendes js/ nicht:\n${bare.stderr || bare.stdout}`);
    }
    if (existsSync(join(harnessFixture, 'js', 'consumer.js'))) {
        throw new Error('Runner darf kein fremdes js/-Verzeichnis anlegen.');
    }
} finally {
    rmSync(workspace, { recursive: true, force: true });
}

console.log('Test-harness JavaScript runner sibling contract passed');
