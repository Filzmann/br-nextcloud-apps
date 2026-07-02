<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Model;

class ShiftSlot {
    public function __construct(
        public int $id,
        public string $teamCode,
        public string $planMonth,
        public string $workDate,
        public string $segmentKey,
        public string $label,
        public string $startsAt,
        public string $endsAt,
        public bool $enabled,
        public array $candidates = []
    ) {
    }

    public static function fromRow(array $row, array $candidates = []): self {
        return new self(
            (int)$row['id'],
            (string)$row['team_code'],
            (string)$row['plan_month'],
            (string)$row['work_date'],
            (string)$row['segment_key'],
            (string)$row['label'],
            (string)$row['starts_at'],
            (string)$row['ends_at'],
            (int)$row['enabled'] === 1,
            $candidates
        );
    }

    public function toApiArray(): array {
        return [
            'id' => $this->id,
            'teamCode' => $this->teamCode,
            'planMonth' => $this->planMonth,
            'workDate' => $this->workDate,
            'segmentKey' => $this->segmentKey,
            'label' => $this->label,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'enabled' => $this->enabled,
            'candidates' => $this->candidates,
        ];
    }
}
