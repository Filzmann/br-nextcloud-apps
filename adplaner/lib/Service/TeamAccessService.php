<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\Team;
use OCP\IGroupManager;
use OCP\IUserSession;

class TeamAccessService {
    public function __construct(
        private IGroupManager $groupManager,
        private IUserSession $userSession,
        private TeamSettingsService $settingsService
    ) {
    }

    public function currentUserId(): string {
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new \RuntimeException('Nicht angemeldet.');
        }

        return $user->getUID();
    }

    public function teamsForCurrentUser(): array {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return [];
        }

        $groupIds = $this->groupManager->getUserGroupIds($user);
        $teamCodes = [];
        foreach ($groupIds as $groupId) {
            if (preg_match('/^ad-ASN-([\p{L}\p{N}]{1,16})$/u', (string)$groupId, $matches)) {
                $teamCodes[] = $matches[1];
            }
        }

        $teamCodes = array_values(array_unique($teamCodes));
        usort($teamCodes, 'strcasecmp');

        return array_values(array_filter(array_map(
            fn(string $teamCode): ?Team => $this->teamForCode($teamCode),
            $teamCodes
        )));
    }

    public function teamForCode(string $teamCode): ?Team {
        $teamCode = $this->normalizeTeamCode($teamCode);
        $groupName = $this->teamGroupName($teamCode);
        $group = $this->groupManager->get($groupName);
        if ($group === null) {
            return null;
        }

        $settings = $this->settingsService->settingsForTeam($teamCode);
        $assistants = $this->assistantsForGroup($group);

        return new Team(
            $teamCode,
            $groupName,
            $this->vacationGroupName($teamCode),
            $settings->displayName,
            $assistants,
            $this->vacationAssistantsForTeam($teamCode, $assistants),
            $this->currentUserIsEbForTeam($teamCode),
            $settings->config
        );
    }

    public function assertTeamAccess(string $teamCode): Team {
        $team = $this->teamForCode($teamCode);
        if ($team === null || !$this->currentUserInGroup($team->groupName)) {
            throw new \DomainException('Kein Zugriff auf dieses Assistenzteam.');
        }

        return $team;
    }

    public function assertCanCoordinate(string $teamCode): Team {
        $team = $this->assertTeamAccess($teamCode);
        if (!$team->isEb) {
            throw new \DomainException('Nur die Einsatzbegleitung darf diese Aktion ausfuehren.');
        }

        return $team;
    }

    public function assertAssistantInTeam(string $teamCode, string $assistantUid): void {
        $team = $this->assertTeamAccess($teamCode);
        foreach ($team->assistants as $assistant) {
            if ($assistant['uid'] === $assistantUid) {
                return;
            }
        }

        throw new \DomainException('Diese Assistenz gehoert nicht zum Team.');
    }

    public function normalizeTeamCode(string $teamCode): string {
        $teamCode = trim($teamCode);
        if (!preg_match('/^[\p{L}\p{N}]{1,16}$/u', $teamCode)) {
            throw new \InvalidArgumentException('ASN-Kuerzel duerfen hoechstens 16 Buchstaben oder Ziffern enthalten; Umlaute sind erlaubt.');
        }

        return $teamCode;
    }

    public function currentUserIsEbForTeam(string $teamCode): bool {
        $teamCode = $this->normalizeTeamCode($teamCode);

        return $this->currentUserInGroup($this->teamGroupName($teamCode)) && $this->currentUserHasEbGroup();
    }

    public function assistantLabelMap(array $assistants): array {
        $map = [];
        foreach ($assistants as $assistant) {
            $map[(string)$assistant['uid']] = (string)$assistant['displayName'];
        }

        return $map;
    }

    private function assistantsForGroup($group): array {
        $assistants = [];
        foreach ($group->getUsers() as $user) {
            $uid = $user->getUID();
            $isEb = $this->userHasEbGroup($user);
            $assistants[] = [
                'uid' => $uid,
                'displayName' => $user->getDisplayName() ?: $uid,
                'isEb' => $isEb,
                'canReceiveShifts' => !$isEb,
            ];
        }

        usort($assistants, static fn(array $a, array $b): int => strcasecmp($a['displayName'], $b['displayName']));

        return $assistants;
    }

    private function vacationAssistantsForTeam(string $teamCode, array $fallbackAssistants): array {
        $group = $this->groupManager->get($this->vacationGroupName($teamCode));
        if ($group === null) {
            return $fallbackAssistants;
        }

        return $this->assistantsForGroup($group);
    }

    private function currentUserInGroup(string $groupName): bool {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }

        return in_array($groupName, $this->groupManager->getUserGroupIds($user), true);
    }

    private function currentUserHasEbGroup(): bool {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return false;
        }

        return $this->userHasEbGroup($user);
    }

    private function userHasEbGroup($user): bool {
        if ($user === null) {
            return false;
        }

        foreach ($this->groupManager->getUserGroupIds($user) as $groupId) {
            if (preg_match('/^ad-EB-.+$/', (string)$groupId)) {
                return true;
            }
        }

        return false;
    }

    private function teamGroupName(string $teamCode): string {
        return 'ad-ASN-' . $teamCode;
    }

    private function vacationGroupName(string $teamCode): string {
        return 'ad-ASN-' . $teamCode . '-Urlaub';
    }
}
