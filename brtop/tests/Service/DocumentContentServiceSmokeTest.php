<?php

declare(strict_types=1);

require __DIR__ . '/../../lib/Model/AgendaItem.php';
require __DIR__ . '/../../lib/Model/Meeting.php';
require __DIR__ . '/../../lib/Model/ProtocolBlock.php';
require __DIR__ . '/../../lib/Service/AgendaService.php';
require __DIR__ . '/../../lib/Service/AgendaAttachmentService.php';
require __DIR__ . '/../../lib/Service/DocumentDateFormatter.php';
require __DIR__ . '/../../lib/Service/InvitationContentService.php';
require __DIR__ . '/../../lib/Service/DocumentContentService.php';

use OCA\BrTop\Model\AgendaItem;
use OCA\BrTop\Model\Meeting;
use OCA\BrTop\Model\ProtocolBlock;
use OCA\BrTop\Service\AgendaAttachmentService;
use OCA\BrTop\Service\AgendaService;
use OCA\BrTop\Service\DocumentContentService;
use OCA\BrTop\Service\DocumentDateFormatter;
use OCA\BrTop\Service\InvitationContentService;

$checkContains = static function (string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
};

$agendaService = (new ReflectionClass(AgendaService::class))->newInstanceWithoutConstructor();
$attachmentService = new AgendaAttachmentService();
$dateFormatter = new DocumentDateFormatter();
$invitationContentService = new InvitationContentService($agendaService, $attachmentService, $dateFormatter);
$contentService = new DocumentContentService($agendaService, $invitationContentService, $dateFormatter);

$meeting = new Meeting([
    'id' => 3,
    'title' => 'Ordentliche BR-Sitzung',
    'meeting_date' => '2026-07-07',
    'meeting_time' => '10:00',
    'location' => 'BR-Büro',
]);

$top = new AgendaItem([
    'id' => 7,
    'meeting_id' => 3,
    'position' => 1,
    'type' => 'personnel_99',
    'subject' => 'Einstellung Test',
    'person_name' => 'Test Person',
    'legal_basis' => '§ 99 BetrVG',
    'requires_resolution' => 1,
    'resolution_count' => 2,
    'agenda_number' => '2.1.1',
    'protocol_blocks' => [
        new ProtocolBlock(['content' => 'Der Betriebsrat berät den Vorgang.']),
    ],
]);

$email = $contentService->invitationEmail($meeting, [$top], [
    ['snapshot_position' => 1, 'user_uid' => 'simon', 'display_name' => 'Simon', 'email' => 'simon@example.invalid'],
]);
$protocol = $contentService->protocolTemplate($meeting, [$top]);
$resolution = $contentService->resolutionDocument($meeting, $top, 2);

$checkContains('Sitzung: Ordentliche BR-Sitzung', $email, 'Invitation email should accept Meeting models.');
$checkContains('2.1.1. Einstellung Test', $email, 'Invitation email should accept AgendaItem models.');
$checkContains('2 Beschlüsse vorgesehen', $email, 'Invitation email should show multiple resolutions.');
$checkContains('## 2.1.1. Einstellung Test', $protocol, 'Protocol template should accept AgendaItem models.');
$checkContains('Der Betriebsrat berät den Vorgang.', $protocol, 'Protocol template should use protocol blocks from AgendaItem models.');
$checkContains('**Beschluss:** 2 von 2', $resolution, 'Resolution document should accept Meeting and AgendaItem models.');

echo 'DocumentContentService smoke tests passed' . PHP_EOL;
