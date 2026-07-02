<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Model;

class TeamSettings {
    public function __construct(
        public string $teamCode,
        public string $displayName,
        public array $config
    ) {
    }

    public function toApiArray(): array {
        return [
            'displayName' => $this->displayName,
            'config' => $this->config,
        ];
    }
}
