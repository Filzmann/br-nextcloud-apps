<?php

declare(strict_types=1);

namespace OCA\LocalBase\Tests\Support;

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected === $actual) {
        return;
    }

    throw new \RuntimeException(
        $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
    );
}

function assertContainsString(string $needle, string $haystack, string $message): void {
    if (str_contains($haystack, $needle)) {
        return;
    }

    throw new \RuntimeException($message . ' Missing: ' . $needle);
}

function assertThrows(callable $callback, string $exceptionClass, string $message): \Throwable {
    try {
        $callback();
    } catch (\Throwable $exception) {
        if ($exception instanceof $exceptionClass) {
            return $exception;
        }

        throw new \RuntimeException($message . ' Threw ' . get_class($exception) . ' instead.');
    }

    throw new \RuntimeException($message . ' No exception was thrown.');
}
