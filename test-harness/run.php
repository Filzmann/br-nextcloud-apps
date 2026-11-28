<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\LocalBase\Tests\Support\PhpTestRunner;

PhpTestRunner::run(
    root: dirname(__DIR__),
    lintDirectories: ['appinfo', 'tests'],
    testDirectories: ['tests/Support'],
    testSuffixes: ['Test.php'],
    successMessage: 'LocalBase PHP tests passed',
    prependBootstrap: true,
);
