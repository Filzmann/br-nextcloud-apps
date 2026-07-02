<?php

declare(strict_types=1);

namespace OCA\BrTop\Model;

class GeneratedDocument {
    public ?int $id;
    public int $meetingId;
    public string $documentType;
    public string $title;
    public string $filePath;
    public string $createdAt;

    public function __construct(array $data = []) {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->meetingId = (int)($data['meeting_id'] ?? $data['meetingId'] ?? 0);
        $this->documentType = (string)($data['document_type'] ?? $data['documentType'] ?? '');
        $this->title = (string)($data['title'] ?? '');
        $this->filePath = (string)($data['file_path'] ?? $data['filePath'] ?? '');
        $this->createdAt = (string)($data['created_at'] ?? $data['createdAt'] ?? '');
    }

    public function displayTitle(): string {
        return trim($this->title) !== '' ? $this->title : $this->typeLabel();
    }

    public function typeLabel(): string {
        return match ($this->documentType) {
            'invitation_email' => 'Einladung E-Mail',
            'invitation_markdown' => 'Ladung',
            'invitation_recipients' => 'Ladungsliste',
            'protocol_markdown' => 'Protokollvorlage',
            'protocol_odt' => 'Protokollvorlage ODT',
            'resolution_markdown' => 'Beschlussdokument',
            default => 'Dokument',
        };
    }

    public function toRepositoryData(): array {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meetingId,
            'document_type' => $this->documentType,
            'title' => $this->title,
            'file_path' => $this->filePath,
            'created_at' => $this->createdAt,
        ];
    }

    public function toApiArray(): array {
        return $this->toRepositoryData();
    }
}
