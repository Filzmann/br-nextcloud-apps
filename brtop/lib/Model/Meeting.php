<?php

declare(strict_types=1);

namespace OCA\BrTop\Model;

use OCA\BrTop\Store\MeetingStore;

class Meeting {
    private ?MeetingStore $store;
    private ?array $agendaItems = null;
    private array $documents = [];

    public ?int $id;
    public string $ownerUid;
    public string $title;
    public string $meetingDate;
    public string $meetingTime;
    public string $location;
    public string $meetingType;
    public string $committeeCode;
    public ?string $invitationDate;
    public string $invitationStatus;
    public string $status;
    public string $createdAt;

    public function __construct(array $data = [], ?MeetingStore $store = null) {
        $this->store = $store;
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->ownerUid = (string)($data['owner_uid'] ?? $data['ownerUid'] ?? '');
        $this->title = (string)($data['title'] ?? '');
        $this->meetingDate = (string)($data['meeting_date'] ?? $data['meetingDate'] ?? '');
        $this->meetingTime = (string)($data['meeting_time'] ?? $data['meetingTime'] ?? '');
        $this->location = (string)($data['location'] ?? '');
        $this->meetingType = (string)($data['meeting_type'] ?? $data['meetingType'] ?? 'custom');
        $this->committeeCode = (string)($data['committee_code'] ?? $data['committeeCode'] ?? '');
        $invitationDate = $data['invitation_date'] ?? $data['invitationDate'] ?? null;
        $this->invitationDate = $invitationDate === null || $invitationDate === '' ? null : (string)$invitationDate;
        $this->invitationStatus = (string)($data['invitation_status'] ?? $data['invitationStatus'] ?? 'not_created');
        $this->status = (string)($data['status'] ?? 'draft');
        $this->createdAt = (string)($data['created_at'] ?? $data['createdAt'] ?? '');

        if (is_array($data['tops'] ?? null)) {
            $this->setAgendaItems(array_map(
                static fn($item): AgendaItem => $item instanceof AgendaItem ? $item : new AgendaItem((array)$item),
                $data['tops']
            ));
        }

        if (is_array($data['documents'] ?? null)) {
            $this->setDocuments($data['documents']);
        }
    }

    public function setStore(MeetingStore $store): void {
        $this->store = $store;
    }

    public function save(): int {
        if ($this->store === null) {
            throw new \RuntimeException('Meeting kann ohne Store nicht gespeichert werden.');
        }

        return $this->store->save($this);
    }

    public function agendaItems(): array {
        if ($this->agendaItems !== null) {
            return $this->agendaItems;
        }

        if ($this->store === null || $this->id === null) {
            return [];
        }

        $this->agendaItems = $this->store->agendaItemsFor($this);

        return $this->agendaItems;
    }

    public function setAgendaItems(array $agendaItems): void {
        $this->agendaItems = array_values($agendaItems);
    }

    public function documents(): array {
        return $this->documents;
    }

    public function setDocuments(array $documents): void {
        $this->documents = array_values(array_map(
            static fn($document): GeneratedDocument => $document instanceof GeneratedDocument ? $document : new GeneratedDocument((array)$document),
            $documents
        ));
    }

    public function isRegularBrMeeting(): bool {
        return $this->meetingType === 'regular_br';
    }

    public function displayTitle(): string {
        return trim($this->title) !== '' ? $this->title : 'ohne Titel';
    }

    public function toRepositoryData(): array {
        return [
            'id' => $this->id,
            'owner_uid' => $this->ownerUid,
            'title' => $this->title,
            'meeting_date' => $this->meetingDate,
            'meeting_time' => $this->meetingTime,
            'location' => $this->location,
            'meeting_type' => $this->meetingType,
            'committee_code' => $this->committeeCode,
            'invitation_date' => $this->invitationDate,
            'invitation_status' => $this->invitationStatus,
            'status' => $this->status,
        ];
    }

    public function toApiArray(): array {
        return array_merge($this->toRepositoryData(), [
            'created_at' => $this->createdAt,
            'tops' => array_map(
                static fn(AgendaItem $item): array => $item->toApiArray(),
                $this->agendaItems()
            ),
            'documents' => array_map(
                static fn(GeneratedDocument $document): array => $document->toApiArray(),
                $this->documents()
            ),
        ]);
    }
}
