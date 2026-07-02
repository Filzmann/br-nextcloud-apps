<?php

declare(strict_types=1);

namespace OCA\BrTop\Model;

class ProtocolBlock {
    public ?int $id;
    public int $meetingId;
    public int $topId;
    public int $blockPosition;
    public string $blockType;
    public string $content;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(array $data = []) {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->meetingId = (int)($data['meeting_id'] ?? $data['meetingId'] ?? 0);
        $this->topId = (int)($data['top_id'] ?? $data['topId'] ?? 0);
        $this->blockPosition = (int)($data['block_position'] ?? $data['blockPosition'] ?? 0);
        $this->blockType = (string)($data['block_type'] ?? $data['blockType'] ?? 'text');
        $this->content = (string)($data['content'] ?? '');
        $this->createdAt = (string)($data['created_at'] ?? $data['createdAt'] ?? '');
        $this->updatedAt = (string)($data['updated_at'] ?? $data['updatedAt'] ?? '');
    }

    public function isTextBlock(): bool {
        return $this->blockType === 'text';
    }

    public function toRepositoryData(): array {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meetingId,
            'top_id' => $this->topId,
            'block_position' => $this->blockPosition,
            'block_type' => $this->blockType,
            'content' => $this->content,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    public function toApiArray(): array {
        return $this->toRepositoryData();
    }
}
