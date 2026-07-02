<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\Model\AgendaItem;
use OCA\BrTop\Repository\DocumentRepository;
use OCA\BrTop\Repository\ProtocolBlockRepository;
use OCA\BrTop\Store\MeetingStore;

class MeetingStateService {
    public function __construct(
        private MeetingStore $meetingStore,
        private DocumentRepository $documentRepository,
        private ProtocolBlockRepository $protocolBlockRepository
    ) {
    }

    public function meetingsForOwner(string $uid): array {
        $meetings = $this->meetingStore->recentForOwner($uid);
        $payload = [];

        foreach ($meetings as $meeting) {
            $meetingId = (int)$meeting->id;
            $meeting->setAgendaItems(
                $this->attachProtocolBlocks($meetingId, $meeting->agendaItems())
            );
            $meeting->setDocuments($this->documentRepository->findForMeeting($meetingId));
            $payload[] = $meeting->toApiArray();
        }

        return $payload;
    }

    private function attachProtocolBlocks(int $meetingId, array $tops): array {
        $blocksByTop = $this->protocolBlockRepository->findForMeetingGrouped($meetingId);

        foreach ($tops as $top) {
            if ($top instanceof AgendaItem && $top->id !== null) {
                $top->protocolBlocks = $blocksByTop[$top->id] ?? [];
            }
        }

        return $tops;
    }
}
