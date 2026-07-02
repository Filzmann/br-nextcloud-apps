<?php

declare(strict_types=1);

namespace OCA\BrTop\Controller;

use OCA\BrTop\AppInfo\Application;
use OCA\BrTop\Exception\DocumentGenerationException;
use OCA\BrTop\Model\Meeting;
use OCA\BrTop\Service\AgendaService;
use OCA\BrTop\Service\AgendaMutationService;
use OCA\BrTop\Service\AgendaTemplateService;
use OCA\BrTop\Service\BrtopLogger;
use OCA\BrTop\Service\BrtopSettingsService;
use OCA\BrTop\Service\DocumentGenerationService;
use OCA\BrTop\Service\MeetingService;
use OCA\BrTop\Service\MeetingStateService;
use OCA\BrTop\Store\ProtocolBlockStore;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends Controller {
    public function __construct(
        IRequest $request,
        private IUserSession $userSession,
        private BrtopLogger $logger,
        private BrtopSettingsService $settings,
        private AgendaTemplateService $agendaTemplateService,
        private AgendaService $agendaService,
        private AgendaMutationService $agendaMutationService,
        private MeetingStateService $meetingStateService,
        private DocumentGenerationService $documentGenerationService,
        private MeetingService $meetingService,
        private ProtocolBlockStore $protocolBlockStore
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
        return new DataResponse([
            'meetings' => $this->meetingStateService->meetingsForOwner($this->uid()),
            'settings' => $this->settingsPayload(),
            'notice' => 'Standardstruktur: 1 Protokolle, 2 Personelle Angelegenheiten (§99/§100/§102), 3 Arbeitsorganisatorisches, 4 Sprechstundenbericht. Einladung zusammengefasst, Protokoll und Beschlüsse getrennt.'
        ]);
    }

    private function settingsPayload(): array {
        $settings = $this->settings->values();
        $settings['regularAgendaTemplateJson'] = $this->agendaTemplateService->jsonForSettings();

        return $settings;
    }

    public function updateSettings(
        string $defaultMeetingTitle,
        int $regularMeetingWeekday,
        int $invitationWeekday,
        string $defaultMeetingTime,
        string $defaultLocation,
        string $memberGroupName,
        string $regularAgendaTemplateJson = ''
    ): DataResponse {
        try {
            $regularAgendaTemplateJson = $this->agendaTemplateService->normalizeJsonForStorage($regularAgendaTemplateJson);

            $this->settings->save(
                $defaultMeetingTitle,
                $regularMeetingWeekday,
                $invitationWeekday,
                $defaultMeetingTime,
                $defaultLocation,
                $memberGroupName,
                $regularAgendaTemplateJson
            );

            return new DataResponse([
                'ok' => true,
                'settings' => $this->settingsPayload(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('update_settings', $e);

            return new DataResponse([
                'ok' => false,
                'message' => 'Die Einstellungen konnten nicht gespeichert werden. Details stehen im Nextcloud-Log.',
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    public function createMeeting(
        string $title,
        string $meetingDate,
        string $meetingTime = '',
        string $location = '',
        string $meetingType = 'custom',
        string $committeeCode = ''
    ): DataResponse {
        return new DataResponse([
            'ok' => true,
            'id' => $this->meetingService->create(
                $this->uid(),
                $title,
                $meetingDate,
                $meetingTime,
                $location,
                $meetingType,
                $committeeCode
            ),
        ]);
    }

    public function planNextRegularMeeting(): DataResponse {
        try {
            $planned = $this->meetingService->planNextRegular($this->uid());

            return new DataResponse([
                'ok' => true,
            ] + $planned);
        } catch (\Throwable $e) {
            $this->logger->error('plan_next_regular_meeting', $e);

            return new DataResponse([
                'ok' => false,
                'message' => 'Die nächste BR-Sitzung konnte nicht geplant werden. Details stehen im Nextcloud-Log.',
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    public function addTop(
        int $meetingId,
        string $type,
        string $subject,
        string $personName = '',
        string $legalBasis = '',
        string $resolutionText = '',
        bool $requiresResolution = false,
        string $agendaItemKind = '',
        int $parentId = 0,
        string $protocolContent = '',
        string $invitationNote = '',
        string $attachmentPaths = '',
        int $resolutionCount = 0
    ): DataResponse {
        $this->assertMeetingOwner($meetingId);

        try {
            $this->agendaMutationService->addItem(
                $meetingId,
                $type,
                $subject,
                $personName,
                $legalBasis,
                $resolutionText,
                $requiresResolution,
                $agendaItemKind,
                $protocolContent,
                $invitationNote,
                $attachmentPaths,
                $resolutionCount
            );
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse(['ok' => true]);
    }

    public function moveTop(int $meetingId, int $topId, string $direction): DataResponse {
        $this->assertMeetingOwner($meetingId);

        try {
            $this->agendaMutationService->moveItem($meetingId, $topId, $direction);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse(['ok' => true]);
    }

    public function changeTopDepth(int $meetingId, int $topId, string $direction): DataResponse {
        $this->assertMeetingOwner($meetingId);

        try {
            $this->agendaMutationService->changeItemDepth($meetingId, $topId, $direction);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse(['ok' => true]);
    }

    public function updateTopSubject(int $meetingId, int $topId, string $subject): DataResponse {
        $this->assertMeetingOwner($meetingId);

        try {
            $this->agendaMutationService->updateItemSubject($meetingId, $topId, $subject);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        }

        return new DataResponse(['ok' => true]);
    }

    public function deleteTop(int $meetingId, int $topId): DataResponse {
        $this->assertMeetingOwner($meetingId);

        try {
            $this->agendaMutationService->deleteItemWithProtocolBlocks($meetingId, $topId);
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('delete_top', $e, [
                'meeting_id' => $meetingId,
                'top_id' => $topId,
            ]);

            return new DataResponse([
                'ok' => false,
                'message' => 'Der TOP konnte nicht gelöscht werden. Details stehen im Nextcloud-Log.',
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new DataResponse(['ok' => true]);
    }

    public function deleteMeeting(int $meetingId): DataResponse {
        $this->assertMeetingOwner($meetingId);

        try {
            $this->meetingService->delete($meetingId);
        } catch (\Throwable $e) {
            $this->logger->error('delete_meeting', $e, ['meeting_id' => $meetingId]);

            return new DataResponse([
                'ok' => false,
                'message' => 'Die Sitzung konnte nicht gelöscht werden. Details stehen im Nextcloud-Log.',
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }

        return new DataResponse(['ok' => true]);
    }

    public function addProtocolBlock(
        int $meetingId,
        int $topId,
        string $blockType = 'text',
        string $content = ''
    ): DataResponse {
        $this->assertMeetingOwner($meetingId);

        if ($this->agendaService->itemForMeeting($meetingId, $topId) === null) {
            return new DataResponse([
                'ok' => false,
                'message' => 'TOP nicht gefunden.',
            ], Http::STATUS_NOT_FOUND);
        }

        if ($blockType !== 'text') {
            $blockType = 'text';
        }
        $block = $this->protocolBlockStore->addBlock($meetingId, $topId, $blockType, $content);

        return new DataResponse([
            'ok' => true,
            'block' => $block->toApiArray(),
        ]);
    }

    public function updateProtocolBlock(
        int $meetingId,
        int $topId,
        int $blockId,
        string $content = ''
    ): DataResponse {
        $this->assertMeetingOwner($meetingId);

        if ($this->agendaService->itemForMeeting($meetingId, $topId) === null) {
            return new DataResponse([
                'ok' => false,
                'message' => 'TOP nicht gefunden.',
            ], Http::STATUS_NOT_FOUND);
        }

        $updated = $this->protocolBlockStore->updateContent($meetingId, $topId, $blockId, $content);
        if (!$updated) {
            return new DataResponse([
                'ok' => false,
                'message' => 'Protokollblock nicht gefunden.',
            ], Http::STATUS_NOT_FOUND);
        }

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

        try {
            return new DataResponse($this->documentGenerationService->generateInvitation($this->uid(), $meeting));
        } catch (\Throwable $e) {
            return $this->documentErrorResponse(
                'generate_invitation',
                $e,
                'Die Einladung konnte nicht erzeugt werden. Details stehen im Nextcloud-Log.',
                ['meeting_id' => $meetingId, 'document_type' => 'invitation'],
                $this->partialDocumentData($e)
            );
        }
    }

    public function generateProtocol(int $meetingId): DataResponse {
        $meeting = $this->assertMeetingOwner($meetingId);

        try {
            return new DataResponse($this->documentGenerationService->generateProtocol($this->uid(), $meeting));
        } catch (\Throwable $e) {
            return $this->documentErrorResponse(
                'generate_protocol',
                $e,
                'Das Protokoll konnte nicht erzeugt werden. Details stehen im Nextcloud-Log.',
                ['meeting_id' => $meetingId, 'document_type' => 'protocol'],
                $this->partialDocumentData($e)
            );
        }
    }

    public function generateResolutions(int $meetingId): DataResponse {
        $meeting = $this->assertMeetingOwner($meetingId);

        try {
            return new DataResponse($this->documentGenerationService->generateResolutions($this->uid(), $meeting));
        } catch (\Throwable $e) {
            return $this->documentErrorResponse(
                'generate_resolutions',
                $e,
                'Die Beschlussdokumente konnten nicht erzeugt werden. Details stehen im Nextcloud-Log.',
                ['meeting_id' => $meetingId, 'document_type' => 'resolutions'],
                $this->partialDocumentData($e)
            );
        }
    }

    private function partialDocumentData(\Throwable $e): array {
        if ($e instanceof DocumentGenerationException) {
            return $e->responseData();
        }

        return [];
    }

    private function documentErrorResponse(
        string $action,
        \Throwable $exception,
        string $message,
        array $context = [],
        array $data = []
    ): DataResponse {
        $this->logger->error($action, $exception, $context);

        return new DataResponse(array_merge($data, [
            'ok' => false,
            'message' => $message,
        ]), Http::STATUS_INTERNAL_SERVER_ERROR);
    }

    private function assertMeetingOwner(int $meetingId): Meeting {
        return $this->meetingService->assertOwnedMeeting($meetingId, $this->uid());
    }

}
