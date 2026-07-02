<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\ShiftCandidate;
use OCA\AdPlaner\Model\ShiftSlot;
use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Store\ShiftPlanStore;

class ScheduleService {
    public function __construct(
        private ShiftPlanStore $store,
        private ShiftConfigService $shiftConfig,
        private TeamAccessService $teamAccess
    ) {
    }

    public function monthPlan(Team $team, string $month, string $currentUid): array {
        $month = $this->shiftConfig->normalizeMonth($month);
        $this->ensureMonthSlots($team, $month);

        $slots = $this->store->slotsForMonth($team->code, $month);
        $enabledSlots = array_values(array_filter($slots, static fn(ShiftSlot $slot): bool => $slot->enabled));
        $candidatesBySlot = $this->store->candidatesForSlotIds(array_map(static fn(ShiftSlot $slot): int => $slot->id, $enabledSlots));
        $assistantLabels = $this->teamAccess->assistantLabelMap($team->assistants);
        $assignableUids = $this->assignableAssistantUidMap($team);
        $notes = $this->store->dayNotesForMonth($team->code, $month);
        $slotsByDate = [];

        foreach ($enabledSlots as $slot) {
            $slotCandidates = array_values(array_filter(
                $candidatesBySlot[$slot->id] ?? [],
                static fn(ShiftCandidate $candidate): bool => isset($assignableUids[$candidate->assistantUid])
            ));
            $slot->candidates = $this->candidatePayload($slotCandidates, $assistantLabels, $currentUid);
            $slotsByDate[$slot->workDate][] = $slot->toApiArray();
        }

        $days = [];
        foreach ($this->shiftConfig->monthDays($month) as $day) {
            $date = $day['date'];
            $days[] = [
                'date' => $date,
                'dayOfMonth' => $day['dayOfMonth'],
                'weekday' => $day['weekday'],
                'slots' => $slotsByDate[$date] ?? [],
                'note' => isset($notes[$date]) ? $notes[$date]->note : '',
            ];
        }

        return [
            'team' => $team->toApiArray(),
            'month' => $month,
            'segments' => array_values(array_filter($this->shiftConfig->segments($team->settings), static fn(array $segment): bool => $segment['enabled'])),
            'days' => $days,
        ];
    }

    public function addCandidate(Team $team, string $month, int $slotId, string $targetUid, string $currentUid): void {
        $month = $this->shiftConfig->normalizeMonth($month);
        $slot = $this->requireSlot($slotId, $team->code, $month);
        if ($targetUid === '') {
            if ($team->isEb) {
                throw new \DomainException('Bitte eine Assistenzkraft auswaehlen.');
            }

            $targetUid = $currentUid;
        }

        $this->assertCandidateMutationAllowed($team, $targetUid, $currentUid);
        $this->assertAssignableAssistantInTeam($team, $targetUid);

        $this->store->addCandidate($slot->id, $targetUid, $currentUid);
    }

    public function removeCandidate(Team $team, string $month, int $slotId, string $targetUid, string $currentUid): void {
        $month = $this->shiftConfig->normalizeMonth($month);
        $slot = $this->requireSlot($slotId, $team->code, $month);
        $targetUid = $targetUid === '' ? $currentUid : $targetUid;

        $this->assertCandidateMutationAllowed($team, $targetUid, $currentUid);
        $this->assertAssignableAssistantInTeam($team, $targetUid);

        $this->store->removeCandidate($slot->id, $targetUid);
    }

    public function saveDayNote(Team $team, string $workDate, string $note, string $currentUid): void {
        if (!$team->isEb) {
            throw new \DomainException('Nur die Einsatzbegleitung darf Bemerkungen bearbeiten.');
        }

        $workDate = $this->shiftConfig->normalizeDate($workDate);
        $this->store->saveDayNote($team->code, $workDate, trim($note), $currentUid);
    }

    private function ensureMonthSlots(Team $team, string $month): void {
        $existingSlots = $this->store->slotsForMonth($team->code, $month);
        $existing = [];
        foreach ($existingSlots as $slot) {
            $existing[$slot->workDate . '|' . $slot->segmentKey] = $slot;
        }

        $segments = $this->shiftConfig->segments($team->settings);
        $segmentKeys = array_flip(array_map(static fn(array $segment): string => (string)$segment['key'], $segments));
        foreach ($existingSlots as $slot) {
            if (isset($segmentKeys[$slot->segmentKey])) {
                continue;
            }

            $this->store->updateSlotDefinition(
                $slot->id,
                $slot->label,
                $slot->startsAt,
                $slot->endsAt,
                false
            );
        }

        foreach ($this->shiftConfig->monthDays($month) as $day) {
            foreach ($segments as $segment) {
                $key = $day['date'] . '|' . $segment['key'];
                if (isset($existing[$key])) {
                    $this->store->updateSlotDefinition(
                        $existing[$key]->id,
                        (string)$segment['label'],
                        (string)$segment['startsAt'],
                        (string)$segment['endsAt'],
                        (bool)$segment['enabled']
                    );
                    continue;
                }

                if (!$segment['enabled']) {
                    continue;
                }

                $this->store->insertSlot(
                    $team->code,
                    $month,
                    (string)$day['date'],
                    (string)$segment['key'],
                    (string)$segment['label'],
                    (string)$segment['startsAt'],
                    (string)$segment['endsAt'],
                    true
                );
            }
        }
    }

    private function requireSlot(int $slotId, string $teamCode, string $month): ShiftSlot {
        $slot = $this->store->slotForMonth($slotId, $teamCode, $month);
        if ($slot === null || !$slot->enabled) {
            throw new \DomainException('Diese Schicht wurde nicht gefunden.');
        }

        return $slot;
    }

    private function candidatePayload(array $candidates, array $assistantLabels, string $currentUid): array {
        return array_map(
            static fn(ShiftCandidate $candidate): array => $candidate->toApiArray($assistantLabels, $currentUid),
            $candidates
        );
    }

    private function assertCandidateMutationAllowed(Team $team, string $targetUid, string $currentUid): void {
        if ($targetUid === $currentUid) {
            return;
        }

        if ($team->isEb) {
            return;
        }

        throw new \DomainException('Assistenzkraefte duerfen nur eigene Eintraege bearbeiten.');
    }

    private function assertAssignableAssistantInTeam(Team $team, string $assistantUid): void {
        foreach ($team->assistants as $assistant) {
            if ($assistant['uid'] === $assistantUid) {
                if (($assistant['canReceiveShifts'] ?? true) === false) {
                    throw new \DomainException('Einsatzbegleitungen koennen keiner Schicht zugeteilt werden.');
                }

                return;
            }
        }

        throw new \DomainException('Diese Assistenz gehoert nicht zum Team.');
    }

    private function assignableAssistantUidMap(Team $team): array {
        $map = [];
        foreach ($team->assistants as $assistant) {
            if (($assistant['canReceiveShifts'] ?? true) === false) {
                continue;
            }

            $map[(string)$assistant['uid']] = true;
        }

        return $map;
    }
}
