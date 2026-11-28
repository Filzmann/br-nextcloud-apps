<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\LocalBase\Tests\Support\PhpTestRunner;

PhpTestRunner::run(
    root: __DIR__,
    lintDirectories: ['Support'],
    testDirectories: ['Support'],
    testSuffixes: ['Test.php'],
    successMessage: 'Test-harness PHP tests passed',
    prependBootstrap: false,
);
