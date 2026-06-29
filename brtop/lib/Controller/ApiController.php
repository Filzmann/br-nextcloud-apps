<?php

declare(strict_types=1);

namespace OCA\BrTop\Controller;

use DateTimeImmutable;
use OCA\BrTop\AppInfo\Application;
use OCA\BrTop\Service\OdtTemplateRenderer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IRootFolder;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private IDBConnection $db,
        private IUserSession $userSession,
        private IRootFolder $rootFolder
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    private function uid(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException('Nicht angemeldet');
        }
        return $user->getUID();
    }

    public function state(): DataResponse {
        $uid = $this->uid();

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_meetings')
            ->where($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)))
            ->orderBy('meeting_date', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults(50);

        $meetings = $qb->executeQuery()->fetchAll();

        foreach ($meetings as &$meeting) {
            $meeting['tops'] = $this->topsForMeeting((int)$meeting['id']);
        }

        return new DataResponse([
            'meetings' => $meetings,
            'notice' => 'Standardstruktur: 1 Protokolle, 2 Personelle Angelegenheiten (§99/§100/§102), 3 Arbeitsorganisatorisches, 4 Sprechstundenbericht. Einladung zusammengefasst, Protokoll und Beschlüsse getrennt.'
        ]);
    }

    public function createMeeting(
        string $title,
        string $meetingDate,
        string $meetingTime = '',
        string $location = ''
    ): DataResponse {
        $uid = $this->uid();

        $qb = $this->db->getQueryBuilder();
        $qb->insert('brtop_meetings')
            ->values([
                'owner_uid' => $qb->createNamedParameter($uid),
                'title' => $qb->createNamedParameter($title),
                'meeting_date' => $qb->createNamedParameter($meetingDate),
                'meeting_time' => $qb->createNamedParameter($meetingTime),
                'location' => $qb->createNamedParameter($location),
                'status' => $qb->createNamedParameter('draft'),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();

        return new DataResponse(['ok' => true, 'id' => (int)$this->db->lastInsertId('brtop_meetings')]);
    }

    public function addTop(
        int $meetingId,
        string $type,
        string $subject,
        string $personName = '',
        string $legalBasis = '',
        string $resolutionText = '',
        bool $requiresResolution = false
    ): DataResponse {
        $this->assertMeetingOwner($meetingId);

        $position = count($this->topsForMeeting($meetingId)) + 1;

        if ($legalBasis === '') {
            $legalBasis = $this->defaultLegalBasis($type);
        }

        $qb = $this->db->getQueryBuilder();
        $qb->insert('brtop_tops')
            ->values([
                'meeting_id' => $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT),
                'position' => $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT),
                'type' => $qb->createNamedParameter($type),
                'subject' => $qb->createNamedParameter($subject),
                'person_name' => $qb->createNamedParameter($personName),
                'legal_basis' => $qb->createNamedParameter($legalBasis),
                'requires_resolution' => $qb->createNamedParameter($requiresResolution, IQueryBuilder::PARAM_BOOL),
                'resolution_text' => $qb->createNamedParameter($resolutionText),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();

        return new DataResponse(['ok' => true]);
    }

    public function seedDemo(): DataResponse {
        $meeting = $this->createMeeting(
            'Ordentliche BR-Sitzung',
            date('Y-m-d', strtotime('+7 days')),
            '10:00',
            'BR-Büro / Videokonferenz'
        )->getData();

        $id = (int)$meeting['id'];

        $this->addTop($id, 'protocol', 'Protokoll der letzten Sitzung', '', '', 'Wer stimmt dem Protokoll der letzten Sitzung zu?', true);

        $this->addTop($id, 'personnel_99', 'Einstellung Hans Müller', 'Hans Müller', '§ 99 BetrVG', 'Wer verweigert die Zustimmung zur Einstellung von Hans Müller gemäß § 99 BetrVG?', true);
        $this->addTop($id, 'personnel_99', 'Eingruppierung Max Muster', 'Max Muster', '§ 99 BetrVG', 'Wer verweigert die Zustimmung zur Eingruppierung von Max Muster gemäß § 99 BetrVG?', true);

        $this->addTop($id, 'personnel_100', 'Vorläufige Einstellung Mathilda Müßig', 'Mathilda Müßig', '§ 100 BetrVG', 'Wer bestreitet, dass die vorläufige Durchführung der personellen Maßnahme aus sachlichen Gründen dringend erforderlich ist?', true);

        $this->addTop($id, 'personnel_102', 'Anhörung Kündigung Nina Narrativ', 'Nina Narrativ', '§ 102 BetrVG', 'Wer widerspricht der beabsichtigten Kündigung von Nina Narrativ gemäß § 102 BetrVG?', true);

        $this->addTop($id, 'organisation', 'Planung nächste Sitzung', '', '', '', false);
        $this->addTop($id, 'consultation_report', 'Bericht aus den Sprechstunden seit der letzten Sitzung', '', '', '', false);

        return new DataResponse(['ok' => true, 'meetingId' => $id]);
    }

    public function generateInvitation(int $meetingId): DataResponse {
        $meeting = $this->assertMeetingOwner($meetingId);
        $tops = $this->topsForMeeting($meetingId);

        $basePath = $this->meetingFolder($meeting);
        $this->ensureFolder($basePath);

        $subject = 'Ladung zur BR-Sitzung am ' . $this->formatGermanDate((string)$meeting['meeting_date']);
        $email = $this->renderInvitationEmail($meeting, $tops);
        $markdown = "# " . $subject . "\n\n```text\n" . $email . "\n```\n";

        $this->putUserFile($basePath . '/01_Einladung_Email.txt', $email);
        $this->putUserFile($basePath . '/01_Ladung.md', $markdown);

        return new DataResponse([
            'ok' => true,
            'type' => 'invitation',
            'folder' => $basePath,
            'subject' => $subject,
            'email' => $email,
            'created' => ['01_Einladung_Email.txt', '01_Ladung.md'],
        ]);
    }

    public function generateProtocol(int $meetingId): DataResponse {
        $meeting = $this->assertMeetingOwner($meetingId);
        $tops = $this->topsForMeeting($meetingId);

        $basePath = $this->meetingFolder($meeting);
        $this->ensureFolder($basePath);

        $created = [];
        $warnings = [];

        $protocol = $this->renderProtocolTemplate($meeting, $tops);
        $this->putUserFile($basePath . '/02_Protokollvorlage.md', $protocol);
        $created[] = '02_Protokollvorlage.md';

        try {
            if (class_exists(OdtTemplateRenderer::class)) {
                $odt = (new OdtTemplateRenderer())->renderProtocol($meeting, $tops);
                $this->putUserFile($basePath . '/02_Protokollvorlage.odt', $odt);
                $created[] = '02_Protokollvorlage.odt';
            } else {
                $warnings[] = 'ODT-Renderer ist noch nicht vorhanden.';
            }
        } catch (\Throwable $e) {
            $warnings[] = 'ODT konnte nicht erzeugt werden: ' . $e->getMessage();
        }

        return new DataResponse([
            'ok' => count($warnings) === 0,
            'type' => 'protocol',
            'folder' => $basePath,
            'created' => $created,
            'warnings' => $warnings,
        ]);
    }

    public function generateResolutions(int $meetingId): DataResponse {
        $meeting = $this->assertMeetingOwner($meetingId);
        $tops = $this->topsForMeeting($meetingId);

        $basePath = $this->meetingFolder($meeting);
        $this->ensureFolder($basePath);

        $created = [];

        foreach ($tops as $top) {
            if ((int)$top['requires_resolution'] !== 1) {
                continue;
            }

            $filename = '03_Beschluss_' . $this->protocolNumber($top) . '_' . $this->safeName((string)$top['subject']) . '.md';

            $this->putUserFile($basePath . '/' . $filename, $this->renderResolutionDocument($meeting, $top));
            $created[] = $filename;
        }

        return new DataResponse([
            'ok' => true,
            'type' => 'resolutions',
            'folder' => $basePath,
            'created' => $created,
            'message' => count($created) === 0 ? 'Keine TOPs mit Beschlussmarkierung vorhanden.' : '',
        ]);
    }

    private function renderInvitationEmail(array $meeting, array $tops): string {
        $g = $this->groupTops($tops);
        $lines = [];

        $lines[] = 'Liebe Kolleg*innen,';
        $lines[] = '';
        $lines[] = 'hiermit lade ich euch zur Betriebsratssitzung ein.';
        $lines[] = '';
        $lines[] = 'Datum: ' . $this->formatGermanDate((string)$meeting['meeting_date']);
        $lines[] = 'Uhrzeit: ' . (($meeting['meeting_time'] ?? '') ?: 'noch offen');
        $lines[] = 'Ort: ' . (($meeting['location'] ?? '') ?: 'noch offen');
        $lines[] = '';
        $lines[] = 'Tagesordnung:';
        $lines[] = '';

        $lines[] = '1. Protokolle';
        $this->appendInvitationItems($lines, $g['protocol'], '1');

        $lines[] = '';
        $lines[] = '2. Personelle Angelegenheiten';
        $lines[] = '2.1 Personelle Einzelmaßnahmen nach § 99 BetrVG' . $this->summarySuffix($g['personnel_99']);
        $this->appendCompactCaseList($lines, $g['personnel_99'], '2.1');

        $lines[] = '2.2 Vorläufige personelle Maßnahmen nach § 100 BetrVG' . $this->summarySuffix($g['personnel_100']);
        $this->appendCompactCaseList($lines, $g['personnel_100'], '2.2');

        $lines[] = '2.3 Anhörungen zu Kündigungen nach § 102 BetrVG' . $this->summarySuffix($g['personnel_102']);
        $this->appendCompactCaseList($lines, $g['personnel_102'], '2.3');

        $lines[] = '';
        $lines[] = '3. Arbeitsorganisatorisches';
        $this->appendInvitationItems($lines, $g['organisation'], '3');

        $lines[] = '';
        $lines[] = '4. Bericht aus den Sprechstunden seit der letzten Sitzung';
        $this->appendInvitationItems($lines, $g['consultation_report'], '4');

        if (count($g['other']) > 0) {
            $lines[] = '';
            $lines[] = '5. Weitere Tagesordnungspunkte';
            $this->appendInvitationItems($lines, $g['other'], '5');
        }

        $lines[] = '';
        $lines[] = 'Viele Grüße';
        $lines[] = '{{ABSENDER}}';

        return implode("\n", $lines) . "\n";
    }

    private function renderProtocolTemplate(array $meeting, array $tops): string {
        $g = $this->groupTops($tops);
        $lines = [];

        $lines[] = '# Protokollvorlage Betriebsratssitzung';
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

        $lines[] = '## 1. Protokolle';
        $this->appendProtocolCases($lines, $g['protocol'], '1');

        $lines[] = '';
        $lines[] = '## 2. Personelle Angelegenheiten';

        $lines[] = '';
        $lines[] = '### 2.1 Personelle Einzelmaßnahmen nach § 99 BetrVG';
        $this->appendProtocolCases($lines, $g['personnel_99'], '2.1');

        $lines[] = '';
        $lines[] = '### 2.2 Vorläufige personelle Maßnahmen nach § 100 BetrVG';
        $this->appendProtocolCases($lines, $g['personnel_100'], '2.2');

        $lines[] = '';
        $lines[] = '### 2.3 Anhörungen zu Kündigungen nach § 102 BetrVG';
        $this->appendProtocolCases($lines, $g['personnel_102'], '2.3');

        $lines[] = '';
        $lines[] = '## 3. Arbeitsorganisatorisches';
        $this->appendProtocolCases($lines, $g['organisation'], '3');
        $lines[] = '';
        $lines[] = '**Nächste Sitzung:**';
        $lines[] = '';
        $lines[] = '- Vorbereitung:';
        $lines[] = '- Nachbereitung:';
        $lines[] = '- Moderation:';
        $lines[] = '- Protokoll:';
        $lines[] = '- Nextcloud:';
        $lines[] = '- TOPs:';

        $lines[] = '';
        $lines[] = '## 4. Bericht aus den Sprechstunden seit der letzten Sitzung';
        $this->appendProtocolCases($lines, $g['consultation_report'], '4');

        if (count($g['other']) > 0) {
            $lines[] = '';
            $lines[] = '## 5. Weitere Tagesordnungspunkte';
            $this->appendProtocolCases($lines, $g['other'], '5');
        }

        return implode("\n", $lines) . "\n";
    }

    private function appendProtocolCases(array &$lines, array $items, string $prefix): void {
        if (count($items) === 0) {
            $lines[] = '';
            $lines[] = '_Keine Einträge._';
            return;
        }

        foreach ($items as $i => $top) {
            $number = $prefix . '.' . ($i + 1);

            $lines[] = '';
            $lines[] = '#### ' . $number . ' ' . $top['subject'];
            $lines[] = '';
            $lines[] = '**Art:** ' . $this->typeLabel((string)$top['type']);
            $lines[] = '**Person:** ' . (($top['person_name'] ?? '') ?: '-');
            $lines[] = '**Rechtsgrundlage:** ' . (($top['legal_basis'] ?? '') ?: '-');
            $lines[] = '';
            $lines[] = '**Beratung:**';
            $lines[] = '';
            $lines[] = '> ';

            if ((int)$top['requires_resolution'] === 1) {
                $lines[] = '';
                $lines[] = '**Beschlussfrage:**';
                $lines[] = '';
                $lines[] = ($top['resolution_text'] ?? '') ?: $this->defaultResolutionText($top);
                $lines[] = '';
                $lines[] = '**Abstimmung:**';
                $lines[] = '';
                $lines[] = '- Ja-Stimmen: ';
                $lines[] = '- Nein-Stimmen: ';
                $lines[] = '- Enthaltungen: ';
                $lines[] = '';
                $lines[] = '**Ergebnis:**';
                $lines[] = '';
            } else {
                $lines[] = '';
                $lines[] = '_Kein Beschluss für diesen Punkt vorgesehen._';
            }
        }
    }

    private function renderResolutionDocument(array $meeting, array $top): string {
        $lines = [];

        $lines[] = '# Beschlussdokument';
        $lines[] = '';
        $lines[] = '**Sitzung:** ' . $meeting['title'];
        $lines[] = '**Datum:** ' . $this->formatGermanDate((string)$meeting['meeting_date']);
        $lines[] = '**TOP:** ' . $this->protocolNumber($top) . ' - ' . $top['subject'];
        $lines[] = '**Verfahren:** ' . $this->typeLabel((string)$top['type']);
        $lines[] = '**Rechtsgrundlage:** ' . (($top['legal_basis'] ?? '') ?: '-');
        $lines[] = '**Betroffene Person:** ' . (($top['person_name'] ?? '') ?: '-');
        $lines[] = '';
        $lines[] = '## Beschlussfrage';
        $lines[] = '';
        $lines[] = ($top['resolution_text'] ?? '') ?: $this->defaultResolutionText($top);
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

    private function appendInvitationItems(array &$lines, array $items, string $prefix): void {
        foreach ($items as $i => $top) {
            $line = $prefix . '.' . ($i + 1) . ' ' . $top['subject'];
            if (!empty($top['legal_basis'])) {
                $line .= ' (' . $top['legal_basis'] . ')';
            }
            if ((int)$top['requires_resolution'] === 1) {
                $line .= ' [Beschluss vorgesehen]';
            }
            $lines[] = $line;
        }
    }

    private function appendCompactCaseList(array &$lines, array $items, string $prefix): void {
        foreach ($items as $i => $top) {
            $line = $prefix . '.' . ($i + 1) . ' ' . $top['subject'];
            if (!empty($top['person_name'])) {
                $line .= ' – ' . $top['person_name'];
            }
            if ((int)$top['requires_resolution'] === 1) {
                $line .= ' [Beschluss vorgesehen]';
            }
            $lines[] = $line;
        }
    }

    private function summarySuffix(array $items): string {
        $count = count($items);
        if ($count === 0) {
            return ' (keine Fälle)';
        }
        return ' (' . $count . ' ' . ($count === 1 ? 'Fall' : 'Fälle') . ')';
    }

    private function groupTops(array $tops): array {
        $groups = [
            'protocol' => [],
            'personnel_99' => [],
            'personnel_100' => [],
            'personnel_102' => [],
            'organisation' => [],
            'consultation_report' => [],
            'other' => [],
        ];

        foreach ($tops as $top) {
            $type = (string)($top['type'] ?? '');

            $key = match ($type) {
                'protocol', 'protokolle' => 'protocol',
                'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige' => 'personnel_99',
                'personnel_100' => 'personnel_100',
                'personnel_102', 'kuendigung', 'pe_kuendigung' => 'personnel_102',
                'organisation' => 'organisation',
                'consultation_report', 'sprechstunden' => 'consultation_report',
                default => 'other',
            };

            $groups[$key][] = $top;
        }

        return $groups;
    }

    private function protocolNumber(array $top): string {
        $type = (string)($top['type'] ?? '');
        $position = (int)($top['position'] ?? 0);

        return match ($type) {
            'protocol', 'protokolle' => '1.' . $position,
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige' => '2.1.' . $position,
            'personnel_100' => '2.2.' . $position,
            'personnel_102', 'kuendigung', 'pe_kuendigung' => '2.3.' . $position,
            'organisation' => '3.' . $position,
            'consultation_report', 'sprechstunden' => '4.' . $position,
            default => '5.' . $position,
        };
    }

    private function typeLabel(string $type): string {
        return match ($type) {
            'protocol', 'protokolle' => 'Protokolle',
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige' => 'Personelle Einzelmaßnahme nach § 99 BetrVG',
            'personnel_100' => 'Vorläufige personelle Maßnahme nach § 100 BetrVG',
            'personnel_102', 'kuendigung', 'pe_kuendigung' => 'Anhörung zu Kündigung nach § 102 BetrVG',
            'organisation' => 'Arbeitsorganisatorisches',
            'consultation_report', 'sprechstunden' => 'Bericht aus den Sprechstunden',
            default => 'Weiterer Tagesordnungspunkt',
        };
    }

    private function defaultLegalBasis(string $type): string {
        return match ($type) {
            'personnel_99' => '§ 99 BetrVG',
            'personnel_100' => '§ 100 BetrVG',
            'personnel_102' => '§ 102 BetrVG',
            default => '',
        };
    }

    private function defaultResolutionText(array $top): string {
        $type = (string)($top['type'] ?? '');
        $subject = trim((string)($top['subject'] ?? ''));
        $person = trim((string)($top['person_name'] ?? ''));

        $measure = $subject !== '' ? $subject : ($person !== '' ? $person : 'die Maßnahme');

        return match ($type) {
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige'
                => 'Wer verweigert die Zustimmung zu ' . $measure . ' und widerspricht ihr damit?',

            'personnel_100'
                => 'Wer bestreitet, dass die vorläufige Durchführung der personellen Maßnahme ' . $measure . ' aus sachlichen Gründen dringend erforderlich ist?',

            'personnel_102', 'kuendigung', 'pe_kuendigung'
                => 'Wer widerspricht der beabsichtigten Kündigung ' . $measure . ' gemäß § 102 BetrVG?',

            'protocol'
                => 'Wer stimmt ' . $measure . ' zu?',

            'organisation', 'consultation_report', 'other'
                => 'Wer stimmt ' . $measure . ' zu?',

            default
                => 'Wer stimmt ' . $measure . ' zu?',
        };
    }

    private function topsForMeeting(int $meetingId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_tops')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->orderBy('position', 'ASC');

        return $qb->executeQuery()->fetchAll();
    }

    private function assertMeetingOwner(int $meetingId): array {
        $uid = $this->uid();

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_meetings')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)));

        $meeting = $qb->executeQuery()->fetch();

        if ($meeting === false) {
            throw new \RuntimeException('Sitzung nicht gefunden oder keine Berechtigung.');
        }

        return $meeting;
    }

    private function meetingFolder(array $meeting): string {
        return 'BR-Sitzungen/' . $meeting['meeting_date'] . ' - ' . $this->safeName((string)$meeting['title']);
    }

    private function ensureFolder(string $path): void {
        $uid = $this->uid();
        $userFolder = $this->rootFolder->getUserFolder($uid);
        $parts = array_filter(explode('/', $path));
        $current = $userFolder;

        foreach ($parts as $part) {
            if (!$current->nodeExists($part)) {
                $current = $current->newFolder($part);
            } else {
                $current = $current->get($part);
            }
        }
    }

    private function putUserFile(string $path, string $content): void {
        $uid = $this->uid();
        $userFolder = $this->rootFolder->getUserFolder($uid);

        $parts = explode('/', $path);
        $filename = array_pop($parts);
        $folderPath = implode('/', $parts);

        $this->ensureFolder($folderPath);
        $folder = $userFolder->get($folderPath);

        if ($folder->nodeExists($filename)) {
            $folder->get($filename)->putContent($content);
        } else {
            $folder->newFile($filename, $content);
        }
    }

    private function safeName(string $name): string {
        $name = preg_replace('/[^A-Za-z0-9äöüÄÖÜß_\- ]/u', '', $name) ?? '';
        $name = preg_replace('/\s+/', '_', trim($name)) ?? '';
        return $name ?: 'ohne_titel';
    }

    private function formatGermanDate(string $date): string {
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }
        return date('d.m.Y', $ts);
    }
}
