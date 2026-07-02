<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Model/AgendaItem.php';
require __DIR__ . '/../../lib/Model/GeneratedDocument.php';
require __DIR__ . '/../../lib/Model/ProtocolBlock.php';
require __DIR__ . '/../../lib/Model/Meeting.php';

use OCA\BrTop\Model\AgendaItem;
use OCA\BrTop\Model\GeneratedDocument;
use OCA\BrTop\Model\Meeting;
use OCA\BrTop\Model\ProtocolBlock;

$checkSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$protocolBlock = new ProtocolBlock([
    'id' => 12,
    'meeting_id' => 3,
    'top_id' => 7,
    'block_position' => 1,
    'block_type' => 'text',
    'content' => 'Protokollinhalt',
]);

$top = new AgendaItem([
    'id' => 7,
    'meeting_id' => 3,
    'position' => 2,
    'type' => 'personnel_99',
    'subject' => 'Einstellung Test',
    'person_name' => 'Test Person',
    'legal_basis' => '§ 99 BetrVG',
    'requires_resolution' => 1,
    'resolution_count' => 2,
    'agenda_number' => '2.1',
    'protocol_blocks' => [$protocolBlock],
]);

$checkSame('2.1', $top->number(), 'AgendaItem should prefer explicit agenda numbers.');
$checkSame('resolution', $top->kind(), 'Resolution TOPs should expose their kind.');
$checkSame(true, $top->isResolutionItem(), 'Resolution TOPs should be resolution-relevant.');
$checkSame(2, $top->resolutionCount(), 'Resolution count should be preserved.');
$checkSame(true, $protocolBlock->isTextBlock(), 'ProtocolBlock should expose text block checks.');
$checkSame(
    'Wer verweigert die Zustimmung zu Einstellung Test und widerspricht ihr damit?',
    $top->defaultResolutionText(),
    'Personnel §99 TOPs should build the expected default resolution question.'
);

$document = new GeneratedDocument([
    'id' => 1,
    'meeting_id' => 3,
    'document_type' => 'invitation_markdown',
    'title' => '',
    'file_path' => 'BR-Sitzungen/2026-07-07/01_Ladung.md',
]);

$meeting = new Meeting([
    'id' => 3,
    'owner_uid' => 'simon',
    'title' => '',
    'meeting_date' => '2026-07-07',
    'meeting_time' => '10:00',
    'meeting_type' => 'regular_br',
    'tops' => [$top],
    'documents' => [$document],
]);

$payload = $meeting->toApiArray();

$checkSame('ohne Titel', $meeting->displayTitle(), 'Meeting displayTitle should have a fallback.');
$checkSame(true, $meeting->isRegularBrMeeting(), 'Meeting should expose regular BR sessions.');
$checkSame('Ladung', $document->displayTitle(), 'GeneratedDocument should expose display titles.');
$checkSame(7, $payload['tops'][0]['id'], 'Meeting API payload should include TOP objects as arrays.');
$checkSame('invitation_markdown', $payload['documents'][0]['document_type'], 'Meeting API payload should include document objects as arrays.');

echo 'BRTop model smoke tests passed' . PHP_EOL;
