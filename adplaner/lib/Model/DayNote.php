<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Model;

class DayNote {
    public function __construct(
        public string $teamCode,
        public string $workDate,
        public string $note,
        public string $updatedByUid = '',
        public string $updatedAt = ''
    ) {
    }

    public static function fromRow(array $row): self {
        return new self(
            (string)($row['team_code'] ?? ''),
            (string)($row['work_date'] ?? ''),
            (string)($row['note'] ?? ''),
            (string)($row['updated_by_uid'] ?? ''),
            (string)($row['updated_at'] ?? '')
        );
    }

    public function toApiArray(): array {
        return [
            'teamCode' => $this->teamCode,
            'workDate' => $this->workDate,
            'note' => $this->note,
        ];
    }
}
