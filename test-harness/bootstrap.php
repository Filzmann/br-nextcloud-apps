<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'OCA\\LocalBase\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = $root . '/lib/' . $relative . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once __DIR__ . '/Support/assertions.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/Support/PhpTestRunner.php';
