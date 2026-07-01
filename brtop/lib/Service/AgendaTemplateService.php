<?php

declare(strict_types=1);

namespace OCA\BrTop\Service;

class AgendaTemplateService {
    private const ALLOWED_KINDS = ['section', 'report', 'discussion', 'resolution'];

    public function __construct(
        private BrtopSettingsService $settings
    ) {
    }

    public function regularBrMeetingItems(): array {
        $json = $this->settings->regularAgendaTemplateJson();
        if ($json !== '') {
            return $this->decodeTemplateJson($json);
        }

        return $this->defaultRegularBrMeetingItems();
    }

    public function jsonForSettings(): string {
        return $this->encodeTemplateJson($this->regularBrMeetingItems());
    }

    public function normalizeJsonForStorage(string $json): string {
        $json = trim($json);
        if ($json === '') {
            return '';
        }

        return $this->encodeTemplateJson($this->decodeTemplateJson($json));
    }

    public function normalizeKind(string $kind, string $type, bool $requiresResolution): string {
        $kind = trim($kind);
        if ($requiresResolution) {
            return 'resolution';
        }

        if (in_array($kind, self::ALLOWED_KINDS, true)) {
            return $kind;
        }

        return $this->defaultKindForType($type);
    }

    private function defaultRegularBrMeetingItems(): array {
        return [
            [
                'key' => 'protocols',
                'type' => 'protocol',
                'subject' => 'Protokolle',
                'personName' => '',
                'legalBasis' => '',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 1,
                'parent' => '',
                'agendaItemKind' => 'section',
                'protocolContent' => '',
            ],
            [
                'type' => 'protocol',
                'subject' => 'Protokoll der letzten Sitzung',
                'personName' => '',
                'legalBasis' => '',
                'requiresResolution' => true,
                'resolutionText' => 'Wer stimmt dem Protokoll der letzten Sitzung zu?',
                'level' => 2,
                'parent' => 'protocols',
                'agendaItemKind' => 'resolution',
                'protocolContent' => '',
            ],
            [
                'key' => 'personnel',
                'type' => 'personnel',
                'subject' => 'Personelle Angelegenheiten',
                'personName' => '',
                'legalBasis' => '',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 1,
                'parent' => '',
                'agendaItemKind' => 'section',
                'protocolContent' => '',
            ],
            [
                'key' => 'personnel_99',
                'type' => 'personnel_99',
                'subject' => 'Personelle Einzelmaßnahmen nach § 99 BetrVG',
                'personName' => '',
                'legalBasis' => '§ 99 BetrVG',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 2,
                'parent' => 'personnel',
                'agendaItemKind' => 'section',
                'protocolContent' => '',
            ],
            [
                'key' => 'personnel_100',
                'type' => 'personnel_100',
                'subject' => 'Vorläufige personelle Maßnahmen nach § 100 BetrVG',
                'personName' => '',
                'legalBasis' => '§ 100 BetrVG',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 2,
                'parent' => 'personnel',
                'agendaItemKind' => 'section',
                'protocolContent' => '',
            ],
            [
                'key' => 'personnel_102',
                'type' => 'personnel_102',
                'subject' => 'Anhörungen zu Kündigungen nach § 102 BetrVG',
                'personName' => '',
                'legalBasis' => '§ 102 BetrVG',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 2,
                'parent' => 'personnel',
                'agendaItemKind' => 'section',
                'protocolContent' => '',
            ],
            [
                'key' => 'organisation',
                'type' => 'organisation',
                'subject' => 'Arbeitsorganisatorisches',
                'personName' => '',
                'legalBasis' => '',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 1,
                'parent' => '',
                'agendaItemKind' => 'discussion',
                'protocolContent' => '',
            ],
            [
                'type' => 'organisation',
                'subject' => 'Nächste Sitzung',
                'personName' => '',
                'legalBasis' => '',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 2,
                'parent' => 'organisation',
                'agendaItemKind' => 'discussion',
                'protocolContent' => '',
            ],
            [
                'type' => 'consultation_report',
                'subject' => 'Bericht aus den Sprechstunden seit der letzten Sitzung',
                'personName' => '',
                'legalBasis' => '',
                'requiresResolution' => false,
                'resolutionText' => '',
                'level' => 1,
                'parent' => '',
                'agendaItemKind' => 'report',
                'protocolContent' => '',
            ],
        ];
    }

    private function decodeTemplateJson(string $json): array {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Die Standard-TOP-Vorlage muss ein JSON-Array sein.');
        }

        return $this->normalizeItems($decoded);
    }

    private function normalizeItems(array $items): array {
        $normalized = [];
        $knownKeys = [];

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('TOP-Vorlage Eintrag ' . ($index + 1) . ' ist kein Objekt.');
            }

            $subject = trim((string)($item['subject'] ?? ''));
            if ($subject === '') {
                throw new \InvalidArgumentException('TOP-Vorlage Eintrag ' . ($index + 1) . ' hat keine Überschrift.');
            }

            $type = trim((string)($item['type'] ?? 'other'));
            if ($type === '') {
                $type = 'other';
            }

            $requiresResolution = (bool)($item['requiresResolution'] ?? false);
            $kind = $this->normalizeKind((string)($item['agendaItemKind'] ?? ''), $type, $requiresResolution);
            $requiresResolution = $kind === 'resolution';

            $level = (int)($item['level'] ?? 1);
            if ($level < 1 || $level > 3) {
                throw new \InvalidArgumentException('TOP-Vorlage Eintrag ' . ($index + 1) . ' muss Ebene 1, 2 oder 3 haben.');
            }

            $parent = trim((string)($item['parent'] ?? ''));
            if ($parent !== '' && $level === 1) {
                throw new \InvalidArgumentException('TOP-Vorlage Eintrag ' . ($index + 1) . ' hat einen Parent, aber Ebene 1.');
            }
            if ($parent !== '' && !isset($knownKeys[$parent])) {
                throw new \InvalidArgumentException('TOP-Vorlage Eintrag ' . ($index + 1) . ' verweist auf einen unbekannten Parent.');
            }

            $key = trim((string)($item['key'] ?? ''));
            if ($key !== '' && isset($knownKeys[$key])) {
                throw new \InvalidArgumentException('TOP-Vorlage Eintrag ' . ($index + 1) . ' verwendet einen doppelten Key.');
            }

            $normalized[] = [
                'key' => $key,
                'type' => substr($type, 0, 64),
                'subject' => substr($subject, 0, 255),
                'personName' => substr(trim((string)($item['personName'] ?? '')), 0, 255),
                'legalBasis' => substr(trim((string)($item['legalBasis'] ?? '')), 0, 255),
                'requiresResolution' => $requiresResolution,
                'resolutionText' => trim((string)($item['resolutionText'] ?? '')),
                'level' => $level,
                'parent' => $parent,
                'agendaItemKind' => $kind,
                'protocolContent' => trim((string)($item['protocolContent'] ?? '')),
            ];

            if ($key !== '') {
                $knownKeys[$key] = true;
            }
        }

        return $normalized;
    }

    private function encodeTemplateJson(array $items): string {
        $json = json_encode($this->normalizeItems($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \InvalidArgumentException('Die Standard-TOP-Vorlage konnte nicht als JSON gespeichert werden.');
        }

        return $json;
    }

    private function defaultKindForType(string $type): string {
        return match ($type) {
            'consultation_report' => 'report',
            default => 'discussion',
        };
    }
}
