<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Model;

class ShiftCandidate {
    public function __construct(
        public int $id,
        public int $slotId,
        public string $assistantUid,
        public string $createdByUid,
        public string $createdAt = ''
    ) {
    }

    public static function fromRow(array $row): self {
        return new self(
            (int)($row['id'] ?? 0),
            (int)($row['slot_id'] ?? 0),
            (string)($row['assistant_uid'] ?? ''),
            (string)($row['created_by_uid'] ?? ''),
            (string)($row['created_at'] ?? '')
        );
    }

    public function toApiArray(array $assistantLabels = [], string $currentUid = ''): array {
        return [
            'id' => $this->id,
            'uid' => $this->assistantUid,
            'displayName' => $assistantLabels[$this->assistantUid] ?? $this->assistantUid,
            'createdByUid' => $this->createdByUid,
            'isSelf' => $this->assistantUid === $currentUid,
        ];
    }
}
