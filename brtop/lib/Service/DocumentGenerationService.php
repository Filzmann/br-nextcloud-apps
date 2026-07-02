<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\Exception\DocumentGenerationException;
use OCA\BrTop\Repository\DocumentRepository;
use OCA\BrTop\Repository\MeetingRepository;
use OCA\BrTop\Repository\ProtocolBlockRepository;

class DocumentGenerationService {
    public function __construct(
        private AgendaService $agendaService,
        private InvitationSnapshotService $invitationSnapshotService,
        private FileExportService $fileExportService,
        private DocumentContentService $documentContentService,
        private DocumentRepository $documentRepository,
        private MeetingRepository $meetingRepository,
        private ProtocolBlockRepository $protocolBlockRepository,
        private BrtopLogger $logger
    ) {
    }

    public function generateInvitation(string $uid, int $meetingId, array $meeting): array {
        $created = [];

        try {
            $tops = $this->agendaService->itemsForMeeting($meetingId);
            $recipients = $this->invitationSnapshotService->getOrCreateForMeeting($meetingId);

            $basePath = $this->fileExportService->meetingFolder($meeting);
            $this->fileExportService->ensureFolder($uid, $basePath);

            $subject = $this->documentContentService->invitationSubject($meeting);
            $email = $this->documentContentService->invitationEmail($meeting, $tops, $recipients);
            $markdown = $this->documentContentService->invitationMarkdown($subject, $email);
            $recipientList = $this->documentContentService->invitationRecipientList($recipients);

            $this->writeDocument($uid, $meetingId, $basePath, '01_Einladung_Email.txt', $email, 'invitation_email', 'Einladung E-Mail');
            $created[] = '01_Einladung_Email.txt';
            $this->writeDocument($uid, $meetingId, $basePath, '01_Ladung.md', $markdown, 'invitation_markdown', 'Ladung');
            $created[] = '01_Ladung.md';
            $this->writeDocument($uid, $meetingId, $basePath, '01_Ladungsliste.txt', $recipientList, 'invitation_recipients', 'Ladungsliste');
            $created[] = '01_Ladungsliste.txt';
            $this->meetingRepository->markInvitationCreated($meetingId);

            return [
                'ok' => true,
                'type' => 'invitation',
                'folder' => $basePath,
                'subject' => $subject,
                'email' => $email,
                'created' => $created,
                'recipientCount' => count($recipients),
            ];
        } catch (\Throwable $e) {
            throw new DocumentGenerationException(['created' => $created], $e);
        }
    }

    public function generateProtocol(string $uid, int $meetingId, array $meeting): array {
        $created = [];
        $warnings = [];

        try {
            $tops = $this->withProtocolBlocks($meetingId, $this->agendaService->itemsForMeeting($meetingId));

            $basePath = $this->fileExportService->meetingFolder($meeting);
            $this->fileExportService->ensureFolder($uid, $basePath);

            $protocol = $this->documentContentService->protocolTemplate($meeting, $tops);
            $this->writeDocument($uid, $meetingId, $basePath, '02_Protokollvorlage.md', $protocol, 'protocol_markdown', 'Protokollvorlage');
            $created[] = '02_Protokollvorlage.md';

            try {
                if (class_exists(OdtTemplateRenderer::class)) {
                    $odt = (new OdtTemplateRenderer())->renderProtocol($meeting, $tops);
                    $this->writeDocument($uid, $meetingId, $basePath, '02_Protokollvorlage.odt', $odt, 'protocol_odt', 'Protokollvorlage ODT');
                    $created[] = '02_Protokollvorlage.odt';
                } else {
                    $warnings[] = 'ODT-Renderer ist noch nicht vorhanden.';
                }
            } catch (\Throwable $e) {
                $this->logger->error('generate_protocol_odt', $e, [
                    'meeting_id' => $meetingId,
                    'document_type' => 'protocol',
                ]);
                $warnings[] = 'Das Protokoll konnte nicht als ODT erzeugt werden. Details stehen im Nextcloud-Log.';
            }

            return [
                'ok' => count($warnings) === 0,
                'type' => 'protocol',
                'folder' => $basePath,
                'created' => $created,
                'warnings' => $warnings,
            ];
        } catch (\Throwable $e) {
            if ($e instanceof DocumentGenerationException) {
                throw $e;
            }

            throw new DocumentGenerationException(['created' => $created], $e);
        }
    }

    public function generateResolutions(string $uid, int $meetingId, array $meeting): array {
        $created = [];

        try {
            $tops = $this->agendaService->itemsForMeeting($meetingId);

            $basePath = $this->fileExportService->meetingFolder($meeting);
            $this->fileExportService->ensureFolder($uid, $basePath);

            foreach ($tops as $top) {
                if (!$this->agendaService->isResolutionItem($top)) {
                    continue;
                }

                $resolutionCount = max(1, $this->agendaService->resolutionCount($top));
                for ($resolutionIndex = 1; $resolutionIndex <= $resolutionCount; $resolutionIndex++) {
                    $number = $this->agendaService->numberForItem($top);
                    $suffix = $resolutionCount > 1 ? '_' . str_pad((string)$resolutionIndex, 2, '0', STR_PAD_LEFT) : '';
                    $filename = '03_Beschluss_' . $number . $suffix . '_' . $this->fileExportService->safeName((string)$top['subject']) . '.md';
                    $this->writeDocument(
                        $uid,
                        $meetingId,
                        $basePath,
                        $filename,
                        $this->documentContentService->resolutionDocument($meeting, $top, $resolutionIndex),
                        'resolution_markdown',
                        'Beschluss ' . $number . ($resolutionCount > 1 ? '.' . $resolutionIndex : '')
                    );
                    $created[] = $filename;
                }
            }

            return [
                'ok' => true,
                'type' => 'resolutions',
                'folder' => $basePath,
                'created' => $created,
                'message' => count($created) === 0 ? 'Keine TOPs mit Beschlussmarkierung vorhanden.' : '',
            ];
        } catch (\Throwable $e) {
            throw new DocumentGenerationException(['created' => $created], $e);
        }
    }

    private function writeDocument(
        string $uid,
        int $meetingId,
        string $basePath,
        string $filename,
        string $content,
        string $documentType,
        string $title
    ): void {
        $path = $basePath . '/' . $filename;
        $this->fileExportService->putUserFile($uid, $path, $content);
        $this->documentRepository->insert($meetingId, $documentType, $title, $path);
    }

    private function withProtocolBlocks(int $meetingId, array $tops): array {
        $blocksByTop = $this->protocolBlockRepository->findForMeetingGrouped($meetingId);

        foreach ($tops as &$top) {
            $top['protocol_blocks'] = $blocksByTop[(int)$top['id']] ?? [];
        }

        return $tops;
    }
}
