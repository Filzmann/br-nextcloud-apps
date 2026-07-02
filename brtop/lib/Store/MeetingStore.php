<?php

declare(strict_types=1);

namespace OCA\BrTop\Store;

use OCA\BrTop\Model\AgendaItem;
use OCA\BrTop\Model\Meeting;
use OCA\BrTop\Repository\MeetingRepository;

class MeetingStore {
    public function __construct(
        private MeetingRepository $meetingRepository,
        private AgendaItemStore $agendaItemStore
    ) {
    }

    public function recentForOwner(string $uid): array {
        return array_map(
            fn(array $row): Meeting => $this->fromRow($row),
            $this->meetingRepository->findRecentByOwner($uid)
        );
    }

    public function getOwned(int $meetingId, string $uid): ?Meeting {
        $row = $this->meetingRepository->findByIdAndOwner($meetingId, $uid);

        return $row === null ? null : $this->fromRow($row);
    }

    public function save(Meeting $meeting): int {
        $data = $meeting->toRepositoryData();

        if ($meeting->id !== null && $meeting->id > 0) {
            $this->meetingRepository->updateFromData($data);
            return $meeting->id;
        }

        $meeting->id = $this->meetingRepository->insert(
            $meeting->ownerUid,
            $meeting->title,
            $meeting->meetingDate,
            $meeting->meetingTime,
            $meeting->location,
            $meeting->meetingType,
            $meeting->committeeCode,
            $meeting->invitationDate,
            $meeting->invitationStatus,
            $meeting->status
        );

        return $meeting->id;
    }

    public function agendaItemsFor(Meeting $meeting): array {
        if ($meeting->id === null) {
            return [];
        }

        return $this->agendaItemStore->forMeeting($meeting->id);
    }

    public function setAgendaItems(Meeting $meeting, array $agendaItems): void {
        $meeting->setAgendaItems(array_values(array_filter(
            $agendaItems,
            static fn($item): bool => $item instanceof AgendaItem
        )));
    }

    private function fromRow(array $row): Meeting {
        return new Meeting($row, $this);
    }
}
