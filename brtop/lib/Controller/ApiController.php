<?php

declare(strict_types=1);

namespace OCA\BrTop\Controller;

use OCA\BrTop\AppInfo\Application;
use OCA\BrTop\Exception\DocumentGenerationException;
use OCA\BrTop\Repository\DocumentRepository;
use OCA\BrTop\Repository\MeetingRepository;
use OCA\BrTop\Service\AgendaService;
use OCA\BrTop\Service\AgendaTemplateService;
use OCA\BrTop\Service\BrtopLogger;
use OCA\BrTop\Service\BrtopSettingsService;
use OCA\BrTop\Service\DocumentGenerationService;
use OCA\BrTop\Service\MeetingScheduleService;
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
        private MeetingScheduleService $meetingScheduleService,
        private AgendaTemplateService $agendaTemplateService,
        private AgendaService $agendaService,
        private DocumentGenerationService $documentGenerationService,
        private MeetingRepository $meetingRepository,
        private DocumentRepository $documentRepository
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

        $meetings = $this->meetingRepository->findRecentByOwner($uid);

        foreach ($meetings as &$meeting) {
            $meetingId = (int)$meeting['id'];
            $meeting['tops'] = $this->agendaService->itemsForMeeting($meetingId);
            $meeting['documents'] = $this->documentRepository->findForMeeting($meetingId);
        }

        return new DataResponse([
            'meetings' => $meetings,
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
        $uid = $this->uid();
        $meetingType = $this->settings->normalizeMeetingType($meetingType);
        $committeeCode = $this->normalizeCommitteeCodeForMeeting($meetingType, $committeeCode);

        $id = $this->meetingRepository->insert($uid, $title, $meetingDate, $meetingTime, $location, $meetingType, $committeeCode, null, 'not_created');

        return new DataResponse(['ok' => true, 'id' => $id]);
    }

    public function planNextRegularMeeting(): DataResponse {
        $uid = $this->uid();
        $defaults = $this->meetingScheduleService->nextRegularMeetingDefaults();
        $items = $this->agendaTemplateService->regularBrMeetingItems();

        try {
            $id = $this->meetingRepository->transactional(function () use ($uid, $defaults, $items): int {
                $meetingId = $this->meetingRepository->insert(
                    $uid,
                    $defaults['title'],
                    $defaults['meetingDate'],
                    $defaults['meetingTime'],
                    $defaults['location'],
                    'regular_br',
                    '',
                    $defaults['invitationDate'],
                    'planned'
                );

                $this->agendaService->addTemplateItems($meetingId, $items);

                return $meetingId;
            });

            return new DataResponse([
                'ok' => true,
                'id' => $id,
                'meetingDate' => $defaults['meetingDate'],
                'invitationDate' => $defaults['invitationDate'],
                'agendaItemsCreated' => count($items),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('plan_next_regular_meeting', $e);

            return new DataResponse([
                'ok' => false,
                'message' => 'Die nächste BR-Sitzung konnte nicht geplant werden. Details stehen im Nextcloud-Log.',
            ], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
    }

    private function normalizeCommitteeCodeForMeeting(string $meetingType, string $committeeCode): string {
        if ($meetingType === 'works_committee') {
            return 'BA';
        }

        if ($meetingType !== 'committee') {
            return '';
        }

        return $this->settings->normalizeCommitteeCode($committeeCode);
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
        string $protocolContent = ''
    ): DataResponse {
        $this->assertMeetingOwner($meetingId);

        try {
            $this->agendaService->addItem(
                $meetingId,
                $type,
                $subject,
                $personName,
                $legalBasis,
                $resolutionText,
                $requiresResolution,
                $agendaItemKind,
                $parentId,
                $protocolContent
            );
        } catch (\InvalidArgumentException $e) {
            return new DataResponse([
                'ok' => false,
                'message' => $e->getMessage(),
            ], Http::STATUS_BAD_REQUEST);
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
            return new DataResponse($this->documentGenerationService->generateInvitation($this->uid(), $meetingId, $meeting));
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
            return new DataResponse($this->documentGenerationService->generateProtocol($this->uid(), $meetingId, $meeting));
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
            return new DataResponse($this->documentGenerationService->generateResolutions($this->uid(), $meetingId, $meeting));
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

    private function assertMeetingOwner(int $meetingId): array {
        $meeting = $this->meetingRepository->findByIdAndOwner($meetingId, $this->uid());
        if ($meeting === null) {
            throw new \RuntimeException('Sitzung nicht gefunden oder keine Berechtigung.');
        }

        return $meeting;
    }

}
