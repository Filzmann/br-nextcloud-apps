import { spawnSync } from 'node:child_process';
import { cpSync, existsSync, mkdirSync, mkdtempSync, readdirSync, rmSync, statSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, dirname, join, relative } from 'node:path';

export function collectJsFiles(root, directory) {
    const path = join(root, directory);
    const files = [];

    if (!existsSync(path)) {
        return files;
    }

    function walk(currentPath) {
        for (const entry of readdirSync(currentPath)) {
            const entryPath = join(currentPath, entry);
            const stats = statSync(entryPath);

            if (stats.isDirectory()) {
                walk(entryPath);
                continue;
            }

            if (stats.isFile() && entryPath.endsWith('.js')) {
                files.push(relative(root, entryPath));
            }
        }
    }

    walk(path);
    files.sort();

    return files;
}

export function runCommand(root, command, args) {
    console.log(`> ${[command, ...args].join(' ')}`);
    const result = spawnSync(command, args, {
        cwd: root,
        stdio: 'inherit',
    });

    if (result.status !== 0) {
        process.exit(result.status ?? 1);
    }
}

export function runJavaScriptSuite(options) {
    const sourceDirectories = options.sourceDirectories || ['js'];
    const testFiles = options.testFiles || [];

    for (const directory of sourceDirectories) {
        for (const file of collectJsFiles(options.root, directory)) {
            runCommand(options.root, 'node', ['--check', file]);
        }
    }

    const commonJsTests = testFiles.filter((file) => file.endsWith('.js'));
    const moduleTests = testFiles.filter((file) => !file.endsWith('.js'));
    if (commonJsTests.length > 0) {
        const isolatedWorkspace = mkdtempSync(join(tmpdir(), 'flz-js-tests.'));
        const isolatedRoot = join(isolatedWorkspace, basename(options.root));
        try {
            mkdirSync(isolatedRoot, { recursive: true });
            // Keep test files isolated while resolving app modules from their original paths.
            // This preserves C8's source-path attribution for consumer coverage gates.
            symlinkSync(join(options.root, 'js'), join(isolatedRoot, 'js'), 'dir');
            cpSync(join(options.root, 'tests'), join(isolatedRoot, 'tests'), { recursive: true });

            const workspaceRoot = dirname(options.root);
            const harnessRoot = existsSync(join(workspaceRoot, 'test-harness'))
                ? join(workspaceRoot, 'test-harness')
                : join(workspaceRoot, 'parent', 'test-harness');
            if (existsSync(harnessRoot)) {
                mkdirSync(join(isolatedWorkspace, 'test-harness'), { recursive: true });
                if (existsSync(join(harnessRoot, 'js'))) {
                    symlinkSync(join(harnessRoot, 'js'), join(isolatedWorkspace, 'test-harness', 'js'), 'dir');
                }
            }
            for (const file of commonJsTests) {
                runCommand(isolatedRoot, 'node', [file]);
            }
        } finally {
            rmSync(isolatedWorkspace, { recursive: true, force: true });
        }
    }

    for (const file of moduleTests) {
        runCommand(options.root, 'node', [file]);
    }

    if (options.successMessage) {
        console.log(options.successMessage);
    }
}
