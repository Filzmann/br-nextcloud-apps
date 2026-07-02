<?php

declare(strict_types=1);

namespace OCA\BrTop\Store;

use OCA\BrTop\Model\ProtocolBlock;
use OCA\BrTop\Repository\ProtocolBlockRepository;

class ProtocolBlockStore {
    public function __construct(
        private ProtocolBlockRepository $protocolBlockRepository
    ) {
    }

    public function groupedForMeeting(int $meetingId): array {
        $grouped = [];

        foreach ($this->protocolBlockRepository->findForMeetingGrouped($meetingId) as $topId => $rows) {
            $grouped[(int)$topId] = array_map(
                static fn(array $row): ProtocolBlock => new ProtocolBlock($row),
                $rows
            );
        }

        return $grouped;
    }

    public function findOneForMeeting(int $meetingId, int $topId, int $blockId): ?ProtocolBlock {
        $row = $this->protocolBlockRepository->findOneForMeeting($meetingId, $topId, $blockId);

        return $row === null ? null : new ProtocolBlock($row);
    }

    public function addBlock(int $meetingId, int $topId, string $blockType, string $content): ProtocolBlock {
        $blockId = $this->protocolBlockRepository->insert($meetingId, $topId, $blockType, $content);
        $block = $this->findOneForMeeting($meetingId, $topId, $blockId);

        if ($block === null) {
            throw new \RuntimeException('Protokollblock wurde angelegt, konnte aber nicht geladen werden.');
        }

        return $block;
    }

    public function updateContent(int $meetingId, int $topId, int $blockId, string $content): bool {
        return $this->protocolBlockRepository->updateContent($meetingId, $topId, $blockId, $content);
    }

    public function deleteForMeeting(int $meetingId): void {
        $this->protocolBlockRepository->deleteForMeeting($meetingId);
    }

    public function deleteForTop(int $meetingId, int $topId): void {
        $this->protocolBlockRepository->deleteForTop($meetingId, $topId);
    }
}
