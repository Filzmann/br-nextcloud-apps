import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { runJavaScriptSuite } from './Support/js-runner.mjs';

const root = dirname(fileURLToPath(import.meta.url));

runJavaScriptSuite({
    root,
    testFiles: [
        'Support/js-runner-smoke.mjs',
    ],
    successMessage: 'Test-harness JavaScript tests passed',
});
