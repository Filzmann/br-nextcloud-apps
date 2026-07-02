<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\Model\AgendaItem;
use OCA\BrTop\Repository\AgendaItemRepository;
use OCA\BrTop\Store\AgendaItemStore;

class AgendaService {
    public function __construct(
        private AgendaItemRepository $agendaItemRepository,
        private AgendaItemStore $agendaItemStore,
        private AgendaTemplateService $agendaTemplateService,
        private AgendaTreeService $agendaTreeService,
        private AgendaAttachmentService $agendaAttachmentService
    ) {
    }

    public function itemsForMeeting(int $meetingId): array {
        return $this->agendaTreeService->numberedItems($this->agendaItemRepository->findForMeeting($meetingId));
    }

    public function itemForMeeting(int $meetingId, int $topId): ?AgendaItem {
        return $this->agendaItemStore->findOneForMeeting($meetingId, $topId);
    }

    public function deleteItemsForMeeting(int $meetingId): void {
        $this->agendaItemRepository->deleteForMeeting($meetingId);
    }

    public function addTemplateItems(int $meetingId, array $items): void {
        $byKey = [];

        foreach ($items as $position => $item) {
            $parentId = null;
            $parent = trim((string)($item['parent'] ?? ''));
            if ($parent !== '' && isset($byKey[$parent])) {
                $parentId = (int)$byKey[$parent]['id'];
            } elseif ($parent !== '') {
                throw new \InvalidArgumentException('TOP-Vorlage verweist auf einen unbekannten Parent: ' . $parent);
            }

            $level = $parentId === null
                ? (int)$item['level']
                : ((int)$byKey[$parent]['level'] + 1);

            if ($level < 1 || $level > 3) {
                throw new \InvalidArgumentException('TOP-Vorlage enthält eine ungültige Ebene.');
            }

            $agendaItem = new AgendaItem([
                'meeting_id' => $meetingId,
                'position' => $position + 1,
                'type' => (string)$item['type'],
                'subject' => (string)$item['subject'],
                'person_name' => (string)$item['personName'],
                'legal_basis' => (string)$item['legalBasis'],
                'resolution_text' => (string)$item['resolutionText'],
                'requires_resolution' => (bool)$item['requiresResolution'],
                'parent_id' => $parentId,
                'level' => $level,
                'agenda_item_kind' => (string)$item['agendaItemKind'],
                'protocol_content' => (string)$item['protocolContent'],
                'invitation_note' => $this->agendaAttachmentService->normalizeInvitationNote((string)($item['invitationNote'] ?? '')),
                'attachment_paths' => $this->agendaAttachmentService->normalizeAttachmentPaths((string)($item['attachmentPaths'] ?? '')),
                'resolution_count' => (bool)$item['requiresResolution'] ? max(1, (int)($item['resolutionCount'] ?? 1)) : 0,
            ], $this->agendaItemStore);
            $id = $agendaItem->save();

            $key = trim((string)($item['key'] ?? ''));
            if ($key !== '') {
                $byKey[$key] = [
                    'id' => $id,
                    'level' => $level,
                ];
            }
        }
    }

    public function addItem(
        int $meetingId,
        string $type,
        string $subject,
        string $personName,
        string $legalBasis,
        string $resolutionText,
        bool $requiresResolution,
        string $agendaItemKind,
        int $parentId,
        string $protocolContent,
        string $invitationNote = '',
        string $attachmentPaths = '',
        int $resolutionCount = 0
    ): int {
        $position = count($this->agendaItemRepository->findForMeeting($meetingId)) + 1;

        if ($legalBasis === '') {
            $legalBasis = $this->defaultLegalBasis($type);
        }

        $agendaItemKind = $this->agendaTemplateService->normalizeKind($agendaItemKind, $type, $requiresResolution);
        $requiresResolution = $requiresResolution || $agendaItemKind === 'resolution';
        $resolutionCount = $requiresResolution ? max(1, $resolutionCount) : 0;
        $invitationNote = $this->agendaAttachmentService->normalizeInvitationNote($invitationNote);
        $attachmentPaths = $this->agendaAttachmentService->normalizeAttachmentPaths($attachmentPaths);

        $item = new AgendaItem([
            'meeting_id' => $meetingId,
            'position' => $position,
            'type' => $type,
            'subject' => $subject,
            'person_name' => $personName,
            'legal_basis' => $legalBasis,
            'resolution_text' => $resolutionText,
            'requires_resolution' => $requiresResolution,
            'parent_id' => null,
            'level' => 1,
            'agenda_item_kind' => $agendaItemKind,
            'protocol_content' => $protocolContent,
            'invitation_note' => $invitationNote,
            'attachment_paths' => $attachmentPaths,
            'resolution_count' => $resolutionCount,
        ], $this->agendaItemStore);
        $id = $item->save();
        $this->normalizeHierarchy($meetingId);

        return $id;
    }

    public function moveItem(int $meetingId, int $topId, string $direction): void {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new \InvalidArgumentException('Unbekannte Verschieberichtung.');
        }

        $items = $this->agendaItemRepository->findForMeeting($meetingId);
        $target = $this->agendaTreeService->findItem($items, $topId);
        if ($target === null) {
            throw new \InvalidArgumentException('TOP nicht gefunden.');
        }

        $siblings = array_values(array_filter($items, static function (array $item) use ($target): bool {
            return (int)($item['parent_id'] ?? 0) === (int)($target['parent_id'] ?? 0);
        }));

        $index = $this->agendaTreeService->indexOfItem($siblings, $topId);
        if ($index === null) {
            throw new \InvalidArgumentException('TOP nicht gefunden.');
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($siblings[$swapIndex])) {
            return;
        }

        $this->agendaItemRepository->updateHierarchyState(
            $meetingId,
            (int)$target['id'],
            $this->agendaTreeService->parentId($target),
            (int)$target['level'],
            (int)$siblings[$swapIndex]['position']
        );
        $this->agendaItemRepository->updateHierarchyState(
            $meetingId,
            (int)$siblings[$swapIndex]['id'],
            $this->agendaTreeService->parentId($siblings[$swapIndex]),
            (int)$siblings[$swapIndex]['level'],
            (int)$target['position']
        );

        $this->normalizeHierarchy($meetingId);
    }

    public function changeItemDepth(int $meetingId, int $topId, string $direction): void {
        if (!in_array($direction, ['indent', 'outdent'], true)) {
            throw new \InvalidArgumentException('Unbekannte Ebenenrichtung.');
        }

        $items = $this->agendaItemRepository->findForMeeting($meetingId);
        $target = $this->agendaTreeService->findItem($items, $topId);
        if ($target === null) {
            throw new \InvalidArgumentException('TOP nicht gefunden.');
        }

        if ($direction === 'indent') {
            $previousSibling = $this->agendaTreeService->previousSibling($items, $target);
            if ($previousSibling === null) {
                throw new \InvalidArgumentException('Dieser TOP kann nicht weiter eingerückt werden.');
            }

            if ($this->agendaTreeService->maxSubtreeLevel($items, $topId) >= 3) {
                throw new \InvalidArgumentException('Sub-TOPs sind nur bis Ebene 3 möglich.');
            }

            $this->agendaItemRepository->updateHierarchyState(
                $meetingId,
                $topId,
                (int)$previousSibling['id'],
                (int)$target['level'] + 1,
                (int)$target['position']
            );
            $this->normalizeHierarchy($meetingId);

            return;
        }

        $parentId = $this->agendaTreeService->parentId($target);
        if ($parentId === null) {
            throw new \InvalidArgumentException('Dieser TOP ist bereits auf der obersten Ebene.');
        }

        $parent = $this->agendaTreeService->findItem($items, $parentId);
        $this->agendaItemRepository->updateHierarchyState(
            $meetingId,
            $topId,
            $parent === null ? null : $this->agendaTreeService->parentId($parent),
            max(1, (int)$target['level'] - 1),
            (int)$target['position']
        );
        $this->normalizeHierarchy($meetingId);
    }

    public function deleteItem(int $meetingId, int $topId): array {
        $items = $this->agendaItemRepository->findForMeeting($meetingId);
        if ($this->agendaTreeService->findItem($items, $topId) === null) {
            throw new \InvalidArgumentException('TOP nicht gefunden.');
        }

        $ids = $this->agendaTreeService->subtreeIds($items, $topId);
        foreach ($ids as $id) {
            $this->agendaItemRepository->deleteOneForMeeting($meetingId, $id);
        }

        $this->normalizeHierarchy($meetingId);

        return $ids;
    }

    public function updateItemSubject(int $meetingId, int $topId, string $subject): void {
        $subject = trim($subject);
        if ($subject === '') {
            throw new \InvalidArgumentException('Der TOP-Betreff darf nicht leer sein.');
        }

        $item = $this->agendaItemStore->findOneForMeeting($meetingId, $topId);
        if ($item === null) {
            throw new \InvalidArgumentException('TOP nicht gefunden.');
        }

        $item->subject = $subject;
        $item->save();
    }

    public function numberForItem(array|AgendaItem $top): string {
        return $this->asAgendaItem($top)->number();
    }

    public function typeLabel(string $type): string {
        return (new AgendaItem(['type' => $type]))->typeLabel();
    }

    public function itemKind(array|AgendaItem $top): string {
        return $this->asAgendaItem($top)->kind();
    }

    public function kindLabel(string $kind): string {
        return (new AgendaItem(['agenda_item_kind' => $kind]))->kindLabel();
    }

    public function isResolutionItem(array|AgendaItem $top): bool {
        return $this->asAgendaItem($top)->isResolutionItem();
    }

    public function resolutionCount(array|AgendaItem $top): int {
        return $this->asAgendaItem($top)->resolutionCount();
    }

    public function defaultResolutionText(array|AgendaItem $top): string {
        return $this->asAgendaItem($top)->defaultResolutionText();
    }

    private function defaultLegalBasis(string $type): string {
        return match ($type) {
            'personnel_99' => '§ 99 BetrVG',
            'personnel_100' => '§ 100 BetrVG',
            'personnel_102' => '§ 102 BetrVG',
            default => '',
        };
    }

    private function normalizeHierarchy(int $meetingId): void {
        $items = $this->agendaItemRepository->findForMeeting($meetingId);
        $ordered = $this->agendaTreeService->orderedTreeItems($items);

        foreach ($ordered as $index => $item) {
            $this->agendaItemRepository->updateHierarchyState(
                $meetingId,
                (int)$item['id'],
                $this->agendaTreeService->parentId($item),
                (int)$item['level'],
                $index + 1
            );
        }
    }

    private function asAgendaItem(array|AgendaItem $top): AgendaItem {
        return $top instanceof AgendaItem ? $top : new AgendaItem($top);
    }
}
