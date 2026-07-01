<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

class DocumentContentService {
    public function __construct(
        private AgendaService $agendaService
    ) {
    }

    public function invitationSubject(array $meeting): string {
        return 'Ladung zur Sitzung am ' . $this->formatGermanDate((string)$meeting['meeting_date']);
    }

    public function invitationEmail(array $meeting, array $tops, array $recipients = []): string {
        $lines = [];

        $lines[] = 'Liebe Kolleg*innen,';
        $lines[] = '';
        $lines[] = 'hiermit lade ich euch zur Sitzung ein.';
        $lines[] = '';
        $lines[] = 'Sitzung: ' . (($meeting['title'] ?? '') ?: 'ohne Titel');
        $lines[] = 'Datum: ' . $this->formatGermanDate((string)$meeting['meeting_date']);
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

    public function invitationMarkdown(string $subject, string $email): string {
        return "# " . $subject . "\n\n```text\n" . $email . "\n```\n";
    }

    public function invitationRecipientList(array $recipients): string {
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

    public function protocolTemplate(array $meeting, array $tops): string {
        $lines = [];

        $lines[] = '# Protokollvorlage';
        $lines[] = '';
        $lines[] = '**Sitzung:** ' . $meeting['title'];
        $lines[] = '**Datum:** ' . $this->formatGermanDate((string)$meeting['meeting_date']);
        $lines[] = '**Beginn:** ';
        $lines[] = '**Ende:** ';
        $lines[] = '**Anwesende BR-Mitglieder:** ';
        $lines[] = '**Ersatzmitglieder:** ';
        $lines[] = '**Verhinderte Mitglieder:** ';
        $lines[] = '';
        $lines[] = 'Anwesenheit und ordnungsgemäße Einladung zu den Tagesordnungspunkten werden festgestellt. Der Betriebsrat ist beschlussfähig.';
        $lines[] = '';

        if (count($tops) === 0) {
            $lines[] = '_Keine Tagesordnungspunkte vorhanden._';
        }

        foreach ($tops as $top) {
            $this->appendProtocolTop($lines, $top);
        }

        return implode("\n", $lines) . "\n";
    }

    public function resolutionDocument(array $meeting, array $top): string {
        $lines = [];

        $lines[] = '# Beschlussdokument';
        $lines[] = '';
        $lines[] = '**Sitzung:** ' . $meeting['title'];
        $lines[] = '**Datum:** ' . $this->formatGermanDate((string)$meeting['meeting_date']);
        $lines[] = '**TOP:** ' . $this->agendaService->numberForItem($top) . ' - ' . $top['subject'];
        $lines[] = '**Verfahren:** ' . $this->agendaService->typeLabel((string)$top['type']);
        $lines[] = '**Rechtsgrundlage:** ' . (($top['legal_basis'] ?? '') ?: '-');
        $lines[] = '**Betroffene Person:** ' . (($top['person_name'] ?? '') ?: '-');
        $lines[] = '';
        $lines[] = '## Beschlussfrage';
        $lines[] = '';
        $lines[] = ($top['resolution_text'] ?? '') ?: $this->agendaService->defaultResolutionText($top);
        $lines[] = '';
        $lines[] = '## Abstimmung';
        $lines[] = '';
        $lines[] = '- Stimmberechtigte Anwesende: ';
        $lines[] = '- Ja-Stimmen: ';
        $lines[] = '- Nein-Stimmen: ';
        $lines[] = '- Enthaltungen: ';
        $lines[] = '';
        $lines[] = '## Ergebnis';
        $lines[] = '';
        $lines[] = 'Der Beschluss wurde angenommen / abgelehnt.';
        $lines[] = '';
        $lines[] = 'Hinweis: Auch bei gemeinsamer Abstimmung wird dieser Fall als eigenes Beschlussdokument geführt.';
        $lines[] = '';
        $lines[] = '## Unterschriften';
        $lines[] = '';
        $lines[] = 'Betriebsratsvorsitz: ___________________________';
        $lines[] = '';
        $lines[] = 'Protokollführung: _____________________________';

        return implode("\n", $lines) . "\n";
    }

    private function appendProtocolTop(array &$lines, array $top): void {
        $kind = $this->agendaService->itemKind($top);
        $level = max(1, min(3, (int)($top['level'] ?? 1)));
        $heading = str_repeat('#', $level + 1);
        $number = $this->agendaService->numberForItem($top);

        $lines[] = '';
        $lines[] = $heading . ' ' . $number . '. ' . $top['subject'];

        if ($kind !== 'section') {
            $lines[] = '';
            $lines[] = '**Art:** ' . $this->agendaService->kindLabel($kind);
            $lines[] = '**Einordnung:** ' . $this->agendaService->typeLabel((string)$top['type']);

            if (!empty($top['person_name'])) {
                $lines[] = '**Person:** ' . $top['person_name'];
            }

            if (!empty($top['legal_basis'])) {
                $lines[] = '**Rechtsgrundlage:** ' . $top['legal_basis'];
            }
        }

        $content = trim((string)($top['protocol_content'] ?? ''));

        if ($kind === 'section') {
            if ($content !== '') {
                $lines[] = '';
                $lines[] = $content;
            }

            return;
        }

        $lines[] = '';

        if ($kind === 'report') {
            $lines[] = '**Bericht:**';
            $lines[] = '';
            $lines[] = $content !== '' ? $content : '> ';
            return;
        }

        $lines[] = '**Beratung:**';
        $lines[] = '';
        $lines[] = $content !== '' ? $content : '> ';

        if ($this->agendaService->isResolutionItem($top)) {
            $lines[] = '';
            $lines[] = '**Beschlussfrage:**';
            $lines[] = '';
            $lines[] = ($top['resolution_text'] ?? '') ?: $this->agendaService->defaultResolutionText($top);
            $lines[] = '';
            $lines[] = '**Abstimmung:**';
            $lines[] = '';
            $lines[] = '- Ja-Stimmen: ';
            $lines[] = '- Nein-Stimmen: ';
            $lines[] = '- Enthaltungen: ';
            $lines[] = '';
            $lines[] = '**Ergebnis:**';
            $lines[] = '';
        }
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
            } elseif ($kind === 'discussion') {
                $line .= ' [Beratung]';
            } elseif ($this->agendaService->isResolutionItem($top)) {
                $line .= ' [Beschluss vorgesehen]';
            }

            $lines[] = $line;
        }
    }

    private function formatGermanDate(string $date): string {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }

        return date('d.m.Y', $ts);
    }
}
