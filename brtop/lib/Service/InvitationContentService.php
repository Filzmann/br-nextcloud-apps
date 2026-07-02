<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

class InvitationContentService {
    public function __construct(
        private AgendaService $agendaService,
        private AgendaAttachmentService $agendaAttachmentService,
        private DocumentDateFormatter $dateFormatter
    ) {
    }

    public function subject(array $meeting): string {
        return 'Ladung zur Sitzung am ' . $this->dateFormatter->germanDate((string)$meeting['meeting_date']);
    }

    public function email(array $meeting, array $tops, array $recipients = []): string {
        $lines = [];

        $lines[] = 'Liebe Kolleg*innen,';
        $lines[] = '';
        $lines[] = 'hiermit lade ich euch zur Sitzung ein.';
        $lines[] = '';
        $lines[] = 'Sitzung: ' . (($meeting['title'] ?? '') ?: 'ohne Titel');
        $lines[] = 'Datum: ' . $this->dateFormatter->germanDate((string)$meeting['meeting_date']);
        $lines[] = 'Uhrzeit: ' . (($meeting['meeting_time'] ?? '') ?: 'noch offen');
        $lines[] = 'Ort: ' . (($meeting['location'] ?? '') ?: 'noch offen');
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
            $indent = str_repeat('  ', max(0, (int)($top['level'] ?? 1) - 1));
            $line = $indent . $this->agendaService->numberForItem($top) . '. ' . $top['subject'];

            if (!empty($top['legal_basis'])) {
                $line .= ' (' . $top['legal_basis'] . ')';
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
            $this->appendInvitationDetails($lines, $top, (int)($top['level'] ?? 1));
        }
    }

    private function appendInvitationDetails(array &$lines, array $top, int $level): void {
        $indent = str_repeat('  ', max(0, $level));
        $note = trim((string)($top['invitation_note'] ?? ''));
        if ($note !== '') {
            foreach (preg_split('/\R/', $note) ?: [] as $noteLine) {
                $noteLine = trim($noteLine);
                if ($noteLine !== '') {
                    $lines[] = $indent . '- ' . $noteLine;
                }
            }
        }

        $attachments = $this->agendaAttachmentService->attachmentPathLines((string)($top['attachment_paths'] ?? ''));
        if (count($attachments) > 0) {
            $lines[] = $indent . '- Anhänge: ' . implode('; ', $attachments);
        }
    }
}
