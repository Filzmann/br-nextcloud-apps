<?php

declare(strict_types=1);

namespace OCA\BrTop\Store;

use OCA\BrTop\Model\AgendaItem;
use OCA\BrTop\Repository\AgendaItemRepository;
use OCA\BrTop\Service\AgendaTreeService;

class AgendaItemStore {
    public function __construct(
        private AgendaItemRepository $agendaItemRepository,
        private AgendaTreeService $agendaTreeService
    ) {
    }

    public function forMeeting(int $meetingId): array {
        return array_map(
            fn(array $row): AgendaItem => $this->fromRow($row),
            $this->agendaTreeService->numberedItems($this->agendaItemRepository->findForMeeting($meetingId))
        );
    }

    public function findOneForMeeting(int $meetingId, int $topId): ?AgendaItem {
        $row = $this->agendaItemRepository->findOneForMeeting($meetingId, $topId);

        return $row === null ? null : $this->fromRow($row);
    }

    public function save(AgendaItem $item): int {
        $data = $item->toRepositoryData();

        if ($item->id !== null && $item->id > 0) {
            $this->agendaItemRepository->updateFromData($data);
            return $item->id;
        }

        $item->id = $this->agendaItemRepository->insert(
            $item->meetingId,
            $item->position,
            $item->type,
            $item->subject,
            $item->personName,
            $item->legalBasis,
            $item->resolutionText,
            $item->requiresResolution,
            $item->parentId,
            $item->level,
            $item->agendaItemKind,
            $item->protocolContent,
            $item->invitationNote,
            $item->attachmentPaths,
            $item->resolutionCount
        );

        return $item->id;
    }

    private function fromRow(array $row): AgendaItem {
        return new AgendaItem($row, $this);
    }
}
