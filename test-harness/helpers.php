<?php

declare(strict_types=1);

namespace OCA\LocalBase\Tests;

use function OCA\LocalBase\Tests\Support\assertSameValue as supportAssertSameValue;
use function OCA\LocalBase\Tests\Support\assertThrows as supportAssertThrows;

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    supportAssertSameValue($expected, $actual, $message);
}

function assertThrows(callable $callback, string $exceptionClass, string $message): \Throwable {
    return supportAssertThrows($callback, $exceptionClass, $message);
}
