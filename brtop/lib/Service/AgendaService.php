<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

use OCA\BrTop\Repository\AgendaItemRepository;

class AgendaService {
    public function __construct(
        private AgendaItemRepository $agendaItemRepository,
        private AgendaTemplateService $agendaTemplateService
    ) {
    }

    public function itemsForMeeting(int $meetingId): array {
        return $this->numberedItems($this->agendaItemRepository->findForMeeting($meetingId));
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

            $id = $this->agendaItemRepository->insert(
                $meetingId,
                $position + 1,
                (string)$item['type'],
                (string)$item['subject'],
                (string)$item['personName'],
                (string)$item['legalBasis'],
                (string)$item['resolutionText'],
                (bool)$item['requiresResolution'],
                $parentId,
                $level,
                (string)$item['agendaItemKind'],
                (string)$item['protocolContent']
            );

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
        string $protocolContent
    ): int {
        $position = count($this->agendaItemRepository->findForMeeting($meetingId)) + 1;
        $parentTop = $parentId > 0 ? $this->agendaItemRepository->findOneForMeeting($meetingId, $parentId) : null;
        if ($parentId > 0 && $parentTop === null) {
            throw new \InvalidArgumentException('Parent-TOP nicht gefunden.');
        }

        $level = $parentTop === null ? 1 : ((int)$parentTop['level'] + 1);
        if ($level > 3) {
            throw new \InvalidArgumentException('Sub-TOPs sind nur bis Ebene 3 möglich.');
        }

        if ($legalBasis === '') {
            $legalBasis = $this->defaultLegalBasis($type);
        }

        $agendaItemKind = $this->agendaTemplateService->normalizeKind($agendaItemKind, $type, $requiresResolution);
        $requiresResolution = $agendaItemKind === 'resolution';

        return $this->agendaItemRepository->insert(
            $meetingId,
            $position,
            $type,
            $subject,
            $personName,
            $legalBasis,
            $resolutionText,
            $requiresResolution,
            $parentTop === null ? null : (int)$parentTop['id'],
            $level,
            $agendaItemKind,
            $protocolContent
        );
    }

    public function numberForItem(array $top): string {
        if (!empty($top['agenda_number'])) {
            return (string)$top['agenda_number'];
        }

        $type = (string)($top['type'] ?? '');
        $position = (int)($top['position'] ?? 0);

        return match ($type) {
            'protocol', 'protokolle' => '1.' . $position,
            'personnel_99', 'personelle_einzelmassnahme', 'pe_einstellung', 'pe_sonstige' => '2.1.' . $position,
            'personnel_100' => '2.2.' . $position,
            'personnel_102', 'kuendigung', 'pe_kuendigung' => '2.3.' . $position,
            'organisation' => '3.' . $position,
            'consultation_report', 'sprechstunden' => '4.' . $position,
            default => '5.' . $position,
        };
    }

    public function typeLabel(string $type): string {
        return match ($type) {
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

    public function itemKind(array $top): string {
        $kind = (string)($top['agenda_item_kind'] ?? '');
        if (in_array($kind, ['section', 'report', 'discussion', 'resolution'], true)) {
            return $kind;
        }

        return (int)($top['requires_resolution'] ?? 0) === 1 ? 'resolution' : 'discussion';
    }

    public function kindLabel(string $kind): string {
        return match ($kind) {
            'section' => 'Gliederungspunkt',
            'report' => 'Bericht',
            'resolution' => 'Beschluss',
            default => 'Beratung',
        };
    }

    public function isResolutionItem(array $top): bool {
        return $this->itemKind($top) === 'resolution' || (int)($top['requires_resolution'] ?? 0) === 1;
    }

    public function defaultResolutionText(array $top): string {
        $type = (string)($top['type'] ?? '');
        $subject = trim((string)($top['subject'] ?? ''));
        $person = trim((string)($top['person_name'] ?? ''));

        $measure = $subject !== '' ? $subject : ($person !== '' ? $person : 'die Maßnahme');

        return match ($type) {
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

    private function defaultLegalBasis(string $type): string {
        return match ($type) {
            'personnel_99' => '§ 99 BetrVG',
            'personnel_100' => '§ 100 BetrVG',
            'personnel_102' => '§ 102 BetrVG',
            default => '',
        };
    }

    private function numberedItems(array $tops): array {
        $nodes = [];
        $rootIds = [];

        foreach ($tops as $top) {
            $id = (int)$top['id'];
            $top['children'] = [];
            $nodes[$id] = $top;
        }

        foreach ($nodes as $id => &$node) {
            $parentId = (int)($node['parent_id'] ?? 0);
            if ($parentId > 0 && isset($nodes[$parentId]) && $parentId !== $id) {
                $nodes[$parentId]['children'][] = $id;
            } else {
                $rootIds[] = $id;
            }
        }
        unset($node);

        $result = [];
        $visited = [];

        $walk = function (array $ids, string $prefix) use (&$walk, &$nodes, &$result, &$visited): void {
            foreach ($ids as $index => $id) {
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;

                $number = $prefix === '' ? (string)($index + 1) : $prefix . '.' . ($index + 1);
                $node = $nodes[$id];
                $children = $node['children'];
                unset($node['children']);
                $node['agenda_number'] = $number;
                $result[] = $node;

                if (count($children) > 0) {
                    $walk($children, $number);
                }
            }
        };

        $walk($rootIds, '');

        foreach ($nodes as $id => $node) {
            if (!isset($visited[$id])) {
                unset($node['children']);
                $node['agenda_number'] = (string)(count($result) + 1);
                $result[] = $node;
            }
        }

        return $result;
    }
}
