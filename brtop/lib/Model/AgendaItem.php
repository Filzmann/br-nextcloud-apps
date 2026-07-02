<?php

declare(strict_types=1);

namespace OCA\BrTop\Model;

use OCA\BrTop\Store\AgendaItemStore;

class AgendaItem {
    private ?AgendaItemStore $store;

    public ?int $id;
    public int $meetingId;
    public int $position;
    public string $type;
    public string $subject;
    public string $personName;
    public string $legalBasis;
    public string $resolutionText;
    public bool $requiresResolution;
    public ?int $parentId;
    public int $level;
    public string $agendaItemKind;
    public string $protocolContent;
    public string $invitationNote;
    public string $attachmentPaths;
    public int $resolutionCount;
    public string $createdAt;
    public string $agendaNumber;
    public array $protocolBlocks;

    public function __construct(array $data = [], ?AgendaItemStore $store = null) {
        $this->store = $store;
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->meetingId = (int)($data['meeting_id'] ?? $data['meetingId'] ?? 0);
        $this->position = (int)($data['position'] ?? 0);
        $this->type = (string)($data['type'] ?? 'other');
        $this->subject = (string)($data['subject'] ?? '');
        $this->personName = (string)($data['person_name'] ?? $data['personName'] ?? '');
        $this->legalBasis = (string)($data['legal_basis'] ?? $data['legalBasis'] ?? '');
        $this->resolutionText = (string)($data['resolution_text'] ?? $data['resolutionText'] ?? '');
        $this->requiresResolution = (bool)($data['requires_resolution'] ?? $data['requiresResolution'] ?? false);
        $parentId = $data['parent_id'] ?? $data['parentId'] ?? null;
        $this->parentId = $parentId === null || (int)$parentId <= 0 ? null : (int)$parentId;
        $this->level = max(1, min(3, (int)($data['level'] ?? 1)));
        $this->agendaItemKind = (string)($data['agenda_item_kind'] ?? $data['agendaItemKind'] ?? '');
        $this->protocolContent = (string)($data['protocol_content'] ?? $data['protocolContent'] ?? '');
        $this->invitationNote = (string)($data['invitation_note'] ?? $data['invitationNote'] ?? '');
        $this->attachmentPaths = (string)($data['attachment_paths'] ?? $data['attachmentPaths'] ?? '');
        $this->resolutionCount = max(0, (int)($data['resolution_count'] ?? $data['resolutionCount'] ?? 0));
        $this->createdAt = (string)($data['created_at'] ?? $data['createdAt'] ?? '');
        $this->agendaNumber = (string)($data['agenda_number'] ?? $data['agendaNumber'] ?? '');
        $this->protocolBlocks = is_array($data['protocol_blocks'] ?? null)
            ? array_values(array_map(
                static fn($block): ProtocolBlock => $block instanceof ProtocolBlock ? $block : new ProtocolBlock((array)$block),
                $data['protocol_blocks']
            ))
            : [];
    }

    public function setStore(AgendaItemStore $store): void {
        $this->store = $store;
    }

    public function save(): int {
        if ($this->store === null) {
            throw new \RuntimeException('AgendaItem kann ohne Store nicht gespeichert werden.');
        }

        return $this->store->save($this);
    }

    public function number(): string {
        if ($this->agendaNumber !== '') {
            return $this->agendaNumber;
        }

        return match ($this->type) {
            'protocol', 'protokolle' => '1.' . $this->position,
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige' => '2.1.' . $this->position,
            'personnel_100' => '2.2.' . $this->position,
            'personnel_102', 'kuendigung', 'pe_kuendigung' => '2.3.' . $this->position,
            'organisation' => '3.' . $this->position,
            'consultation_report', 'sprechstunden' => '4.' . $this->position,
            default => '5.' . $this->position,
        };
    }

    public function typeLabel(): string {
        return match ($this->type) {
            'protocol', 'protokolle' => 'Protokolle',
            'personnel' => 'Personelle Angelegenheiten',
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige' => 'Personelle Einzelmaßnahme nach § 99 BetrVG',
            'personnel_100' => 'Vorläufige personelle Maßnahme nach § 100 BetrVG',
            'personnel_102', 'kuendigung', 'pe_kuendigung' => 'Anhörung zu Kündigung nach § 102 BetrVG',
            'organisation' => 'Arbeitsorganisatorisches',
            'consultation_report', 'sprechstunden' => 'Bericht aus den Sprechstunden',
            default => 'Weiterer Tagesordnungspunkt',
        };
    }

    public function kind(): string {
        if (in_array($this->agendaItemKind, ['section', 'report', 'discussion', 'resolution'], true)) {
            return $this->agendaItemKind;
        }

        return $this->requiresResolution ? 'resolution' : 'discussion';
    }

    public function kindLabel(): string {
        return match ($this->kind()) {
            'section' => 'Gliederungspunkt',
            'report' => 'Bericht',
            'resolution' => 'Beschluss',
            default => 'Beratung',
        };
    }

    public function isResolutionItem(): bool {
        return $this->resolutionCount() > 0
            || $this->kind() === 'resolution'
            || $this->requiresResolution;
    }

    public function resolutionCount(): int {
        if ($this->resolutionCount > 0) {
            return $this->resolutionCount;
        }

        return $this->requiresResolution ? 1 : 0;
    }

    public function defaultResolutionText(): string {
        $measure = trim($this->subject) !== ''
            ? trim($this->subject)
            : (trim($this->personName) !== '' ? trim($this->personName) : 'die Maßnahme');

        return match ($this->type) {
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige'
                => 'Wer verweigert die Zustimmung zu ' . $measure . ' und widerspricht ihr damit?',

            'personnel_100'
                => 'Wer bestreitet, dass die vorläufige Durchführung der personellen Maßnahme ' . $measure . ' aus sachlichen Gründen dringend erforderlich ist?',

            'personnel_102', 'kuendigung', 'pe_kuendigung'
                => 'Wer widerspricht der beabsichtigten Kündigung ' . $measure . ' gemäß § 102 BetrVG?',

            default
                => 'Wer stimmt ' . $measure . ' zu?',
        };
    }

    public function toRepositoryData(): array {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meetingId,
            'position' => $this->position,
            'type' => $this->type,
            'subject' => $this->subject,
            'person_name' => $this->personName,
            'legal_basis' => $this->legalBasis,
            'resolution_text' => $this->resolutionText,
            'requires_resolution' => $this->requiresResolution,
            'parent_id' => $this->parentId,
            'level' => $this->level,
            'agenda_item_kind' => $this->agendaItemKind,
            'protocol_content' => $this->protocolContent,
            'invitation_note' => $this->invitationNote,
            'attachment_paths' => $this->attachmentPaths,
            'resolution_count' => $this->resolutionCount,
        ];
    }

    public function toApiArray(): array {
        return array_merge($this->toRepositoryData(), [
            'requires_resolution' => $this->requiresResolution ? 1 : 0,
            'created_at' => $this->createdAt,
            'agenda_number' => $this->agendaNumber,
            'protocol_blocks' => array_map(
                static fn(ProtocolBlock $block): array => $block->toApiArray(),
                $this->protocolBlocks
            ),
        ]);
    }
}
