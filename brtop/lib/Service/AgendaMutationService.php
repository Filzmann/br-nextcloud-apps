<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\Repository\MeetingRepository;
use OCA\BrTop\Store\ProtocolBlockStore;

class AgendaMutationService {
    public function __construct(
        private AgendaService $agendaService,
        private MeetingRepository $meetingRepository,
        private ProtocolBlockStore $protocolBlockStore
    ) {
    }

    public function addItem(
        int $meetingId,
        string $type,
        string $subject,
        string $personName,
        string $legalBasis,
        string $resolutionText,
        bool $requiresResolution,
        string $agendaItemKind,
        string $protocolContent,
        string $invitationNote,
        string $attachmentPaths,
        int $resolutionCount
    ): int {
        return $this->agendaService->addItem(
            $meetingId,
            $type,
            $subject,
            $personName,
            $legalBasis,
            $resolutionText,
            $requiresResolution,
            $agendaItemKind,
            0,
            $protocolContent,
            $invitationNote,
            $attachmentPaths,
            $resolutionCount
        );
    }

    public function moveItem(int $meetingId, int $topId, string $direction): void {
        $this->agendaService->moveItem($meetingId, $topId, $direction);
    }

    public function changeItemDepth(int $meetingId, int $topId, string $direction): void {
        $this->agendaService->changeItemDepth($meetingId, $topId, $direction);
    }

    public function updateItemSubject(int $meetingId, int $topId, string $subject): void {
        $this->agendaService->updateItemSubject($meetingId, $topId, $subject);
    }

    public function deleteItemWithProtocolBlocks(int $meetingId, int $topId): void {
        $this->meetingRepository->transactional(function () use ($meetingId, $topId): void {
            $deletedTopIds = $this->agendaService->deleteItem($meetingId, $topId);
            foreach ($deletedTopIds as $deletedTopId) {
                $this->protocolBlockStore->deleteForTop($meetingId, $deletedTopId);
            }
        });
    }
}
