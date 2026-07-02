<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Store;

use OCA\AdPlaner\Model\TeamSettings;
use OCA\AdPlaner\Repository\TeamSettingsRepository;
use OCA\AdPlaner\Service\ShiftConfigService;

class TeamSettingsStore {
    public function __construct(
        private TeamSettingsRepository $repository,
        private ShiftConfigService $shiftConfig
    ) {
    }

    public function forTeam(string $teamCode): TeamSettings {
        $row = $this->repository->findByCode($teamCode);
        if ($row === null) {
            return new TeamSettings($teamCode, $teamCode, $this->shiftConfig->defaults());
        }

        $decoded = json_decode((string)($row['settings_json'] ?? ''), true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        return new TeamSettings(
            $teamCode,
            (string)($row['display_name'] ?: $teamCode),
            $this->shiftConfig->normalize($decoded)
        );
    }

    public function saveFromApi(string $teamCode, string $displayName, array $config): TeamSettings {
        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $teamCode;
        }

        $normalized = $this->shiftConfig->normalize($config);
        $this->repository->save($teamCode, $displayName, $normalized);

        return new TeamSettings($teamCode, $displayName, $normalized);
    }
}
