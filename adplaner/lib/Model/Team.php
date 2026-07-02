<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Model;

class Team {
    public function __construct(
        public string $code,
        public string $groupName,
        public string $vacationGroupName,
        public string $displayName,
        public array $assistants,
        public array $vacationAssistants,
        public bool $isEb,
        public array $settings
    ) {
    }

    public function toApiArray(): array {
        return [
            'code' => $this->code,
            'groupName' => $this->groupName,
            'vacationGroupName' => $this->vacationGroupName,
            'displayName' => $this->displayName,
            'assistants' => $this->assistants,
            'vacationAssistants' => $this->vacationAssistants,
            'isEb' => $this->isEb,
            'canCoordinate' => $this->isEb,
            'settings' => $this->settings,
        ];
    }
}
