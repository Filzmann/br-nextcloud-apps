<?php

declare(strict_types=1);

namespace OCA\LocalBase\Tests\Support;


assertSameValue(['a' => 1], ['a' => 1], 'Equal arrays should pass.');
assertContainsString('base', 'localbase', 'Needles should be detected.');

$exception = assertThrows(
    static fn(): never => throw new \DomainException('Kaputt'),
    \DomainException::class,
    'Expected exceptions should be returned.'
);
assertSameValue('Kaputt', $exception->getMessage(), 'Returned exceptions should be inspectable.');

assertThrows(
    static fn(): never => assertSameValue(1, 2, 'Mismatch'),
    \RuntimeException::class,
    'Failed assertions should throw RuntimeException.'
);
assertThrows(
    static fn(): never => assertContainsString('x', 'abc', 'Missing string'),
    \RuntimeException::class,
    'Missing strings should throw RuntimeException.'
);
assertThrows(
    static fn(): never => assertThrows(static fn(): string => 'ok', \DomainException::class, 'No throw'),
    \RuntimeException::class,
    'Missing exceptions should throw RuntimeException.'
);

echo 'LocalBase assertion helper smoke tests passed' . PHP_EOL;
