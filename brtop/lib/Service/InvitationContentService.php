<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\Model\AgendaItem;
use OCA\BrTop\Model\Meeting;
use OCA\BrTop\Model\ProtocolBlock;

class InvitationContentService {
    public function __construct(
        private AgendaService $agendaService,
        private AgendaAttachmentService $agendaAttachmentService,
        private DocumentDateFormatter $dateFormatter
    ) {
    }

    public function subject(array|Meeting $meeting): string {
        $meetingData = $this->meetingData($meeting);

        return 'Ladung zur Sitzung am ' . $this->dateFormatter->germanDate((string)$meetingData['meeting_date']);
    }

    public function email(array|Meeting $meeting, array $tops, array $recipients = []): string {
        $meetingData = $this->meetingData($meeting);
        $lines = [];

        $lines[] = 'Liebe Kolleg*innen,';
        $lines[] = '';
        $lines[] = 'hiermit lade ich euch zur Sitzung ein.';
        $lines[] = '';
        $lines[] = 'Sitzung: ' . (($meetingData['title'] ?? '') ?: 'ohne Titel');
        $lines[] = 'Datum: ' . $this->dateFormatter->germanDate((string)$meetingData['meeting_date']);
        $lines[] = 'Uhrzeit: ' . (($meetingData['meeting_time'] ?? '') ?: 'noch offen');
        $lines[] = 'Ort: ' . (($meetingData['location'] ?? '') ?: 'noch offen');
        $lines[] = '';
        $lines[] = 'Geladene Mitglieder: ' . count($recipients);
        $lines[] = '';
        $lines[] = 'Tagesordnung:';
        $lines[] = '';

        $this->appendAgendaLines($lines, $tops);

        $lines[] = '';
        $lines[] = 'Viele Grüße';
        $lines[] = '{{ABSENDER}}';

        return implode("\n", $lines) . "\n";
    }

    public function markdown(string $subject, string $email): string {
        return "# " . $subject . "\n\n```text\n" . $email . "\n```\n";
    }

    public function recipientList(array $recipients): string {
        $lines = [
            'Ladungsliste',
            '',
            'Snapshot zum Zeitpunkt der Einladung. Spätere Gruppenänderungen verändern diese Liste nicht.',
            '',
        ];

        foreach ($recipients as $recipient) {
            $label = (string)($recipient['display_name'] ?? '');
            if ($label === '') {
                $label = (string)$recipient['user_uid'];
            }

            $line = $recipient['snapshot_position'] . '. ' . $label . ' (' . $recipient['user_uid'] . ')';
            if (!empty($recipient['email'])) {
                $line .= ' <' . $recipient['email'] . '>';
            }
            $lines[] = $line;
        }

        return implode("\n", $lines) . "\n";
    }

    private function appendAgendaLines(array &$lines, array $tops): void {
        if (count($tops) === 0) {
            $lines[] = 'Keine Tagesordnungspunkte vorhanden.';
            return;
        }

        foreach ($tops as $top) {
            $topData = $this->topData($top);
            $indent = str_repeat('  ', max(0, (int)($topData['level'] ?? 1) - 1));
            $line = $indent . $this->agendaService->numberForItem($top) . '. ' . $topData['subject'];

            if (!empty($topData['legal_basis'])) {
                $line .= ' (' . $topData['legal_basis'] . ')';
            }

            $kind = $this->agendaService->itemKind($top);
            if ($kind === 'report') {
                $line .= ' [Bericht]';
            } elseif ($this->agendaService->isResolutionItem($top)) {
                $resolutionCount = max(1, $this->agendaService->resolutionCount($top));
                $line .= $resolutionCount > 1
                    ? ' [' . $resolutionCount . ' Beschlüsse vorgesehen]'
                    : ' [Beschluss vorgesehen]';
            } elseif ($kind === 'discussion') {
                $line .= ' [Beratung]';
            }

            $lines[] = $line;
            $this->appendInvitationDetails($lines, $top, (int)($topData['level'] ?? 1));
        }
    }

    private function appendInvitationDetails(array &$lines, array|AgendaItem $top, int $level): void {
        $topData = $this->topData($top);
        $indent = str_repeat('  ', max(0, $level));
        $note = trim((string)($topData['invitation_note'] ?? ''));
        if ($note !== '') {
            foreach (preg_split('/\R/', $note) ?: [] as $noteLine) {
                $noteLine = trim($noteLine);
                if ($noteLine !== '') {
                    $lines[] = $indent . '- ' . $noteLine;
                }
            }
        }

        $attachments = $this->agendaAttachmentService->attachmentPathLines((string)($topData['attachment_paths'] ?? ''));
        if (count($attachments) > 0) {
            $lines[] = $indent . '- Anhänge: ' . implode('; ', $attachments);
        }
    }

    private function meetingData(array|Meeting $meeting): array {
        return $meeting instanceof Meeting ? $meeting->toRepositoryData() : $meeting;
    }

    private function topData(array|AgendaItem $top): array {
        return $top instanceof AgendaItem ? $top->toRepositoryData() + [
            'created_at' => $top->createdAt,
            'agenda_number' => $top->agendaNumber,
            'protocol_blocks' => array_map(
                fn($block): array => $this->protocolBlockData($block),
                $top->protocolBlocks
            ),
        ] : $top;
    }

    private function protocolBlockData(array|ProtocolBlock $block): array {
        return $block instanceof ProtocolBlock ? $block->toApiArray() : $block;
    }
}
