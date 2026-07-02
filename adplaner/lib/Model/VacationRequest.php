<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Model;

class VacationRequest {
    public function __construct(
        public int $id,
        public string $assistantUid,
        public string $dateFrom,
        public string $dateTo,
        public string $status,
        public string $note
    ) {
    }

    public static function fromRow(array $row): self {
        return new self(
            (int)$row['id'],
            (string)$row['assistant_uid'],
            (string)$row['date_from'],
            (string)$row['date_to'],
            (string)$row['status'],
            (string)($row['note'] ?? '')
        );
    }

    public function toApiArray(): array {
        return [
            'id' => $this->id,
            'assistantUid' => $this->assistantUid,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'status' => $this->status,
            'note' => $this->note,
        ];
    }
}
