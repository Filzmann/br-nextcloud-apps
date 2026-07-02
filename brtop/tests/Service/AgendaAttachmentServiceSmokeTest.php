<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Service/AgendaAttachmentService.php';

use OCA\BrTop\Service\AgendaAttachmentService;

$checkSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$checkThrows = static function (callable $callback, string $message): void {
    try {
        $callback();
    } catch (\InvalidArgumentException) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
};

$service = new AgendaAttachmentService();

$checkSame(
    "Kurzinfo\nzweite Zeile",
    $service->normalizeInvitationNote("  Kurzinfo  \n\n zweite Zeile "),
    'Invitation notes should be trimmed and empty lines should be removed.'
);

$checkSame(
    "/BR/Einladung.pdf\nTeam/Fall 1.pdf",
    $service->normalizeAttachmentPaths(" /BR/Einladung.pdf \nTeam/Fall 1.pdf\n/BR/Einladung.pdf"),
    'Attachment paths should be trimmed, deduplicated, and keep order.'
);

$checkSame(
    ['/BR/Einladung.pdf', 'Team/Fall 1.pdf'],
    $service->attachmentPathLines("/BR/Einladung.pdf\nTeam/Fall 1.pdf"),
    'Attachment path lines should parse normalized paths.'
);

$checkThrows(
    static fn() => $service->normalizeAttachmentPaths('/BR/../secret.pdf'),
    'Attachment paths with .. segments should be rejected.'
);

echo 'AgendaAttachmentService smoke tests passed' . PHP_EOL;
