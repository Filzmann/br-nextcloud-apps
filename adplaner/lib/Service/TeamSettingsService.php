<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\TeamSettings;
use OCA\AdPlaner\Store\TeamSettingsStore;

class TeamSettingsService {
    public function __construct(
        private TeamSettingsStore $store
    ) {
    }

    public function settingsForTeam(string $teamCode): TeamSettings {
        return $this->store->forTeam($teamCode);
    }

    public function saveFromApi(string $teamCode, string $displayName, array $config): array {
        return $this->store->saveFromApi($teamCode, $displayName, $config)->toApiArray();
    }
}
