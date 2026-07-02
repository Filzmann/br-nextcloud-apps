<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Service;

use OCA\AdPlaner\Model\Team;
use OCA\AdPlaner\Model\VacationRequest;
use OCA\AdPlaner\Store\VacationStore;

class VacationService {
    public function __construct(
        private VacationStore $store,
        private ShiftConfigService $dateService
    ) {
    }

    public function yearPlan(Team $team, int $year, string $currentUid): array {
        $days = $this->dateService->yearDays($year);
        $assistants = $team->vacationAssistants;
        $assistantUids = array_map(static fn(array $assistant): string => (string)$assistant['uid'], $assistants);
        $requests = $this->store->forUsersInYear($assistantUids, $year);

        $rows = [];
        foreach ($assistants as $assistant) {
            $uid = (string)$assistant['uid'];
            $rows[] = [
                'uid' => $uid,
                'displayName' => (string)$assistant['displayName'],
                'isSelf' => $uid === $currentUid,
                'days' => $this->cellsForAssistant($uid, $days, $requests),
            ];
        }

        return [
            'team' => $team->toApiArray(),
            'year' => $year,
            'days' => $days,
            'assistants' => $rows,
            'requests' => array_map(static fn(VacationRequest $request): array => $request->toApiArray(), $requests),
        ];
    }

    public function createVacationRequest(string $assistantUid, string $dateFrom, string $dateTo, string $note): int {
        $dateFrom = $this->dateService->normalizeDate($dateFrom);
        $dateTo = $this->dateService->normalizeDate($dateTo);
        if ($dateTo < $dateFrom) {
            throw new \InvalidArgumentException('Das Bis-Datum darf nicht vor dem Von-Datum liegen.');
        }

        return $this->store->create($assistantUid, $dateFrom, $dateTo, trim($note), 'planned', $assistantUid);
    }

    public function deleteOwnRequest(string $assistantUid, int $requestId): void {
        $request = $this->store->findById($requestId);
        if ($request === null) {
            throw new \DomainException('Urlaubswunsch nicht gefunden.');
        }

        if ($request->assistantUid !== $assistantUid) {
            throw new \DomainException('Nur eigene Urlaubswuensche koennen geloescht werden.');
        }

        if ($request->status !== 'planned') {
            throw new \DomainException('Genehmigter Urlaub kann im Prototyp nicht selbst geloescht werden.');
        }

        $this->store->delete($requestId);
    }

    public function setStatusForDate(Team $team, string $assistantUid, string $date, string $status, string $updatedByUid): void {
        if (!$team->isEb) {
            throw new \DomainException('Nur die Einsatzbegleitung darf Urlaub genehmigen.');
        }

        $this->assertAssistantInVacationPlan($team, $assistantUid);
        $date = $this->dateService->normalizeDate($date);
        $status = $this->normalizeStatus($status);
        $request = $this->store->findCoveringDate($assistantUid, $date);

        if ($request === null) {
            $this->store->create($assistantUid, $date, $date, '', $status, $updatedByUid);
            return;
        }

        $this->store->updateStatus($request->id, $status, $updatedByUid);
    }

    private function cellsForAssistant(string $assistantUid, array $days, array $requests): array {
        $cells = [];
        foreach ($days as $day) {
            $cells[$day['date']] = [
                'status' => '',
                'requestId' => null,
            ];
        }

        foreach ($requests as $request) {
            if ($request->assistantUid !== $assistantUid) {
                continue;
            }

            foreach ($cells as $date => $cell) {
                if ($date < $request->dateFrom || $date > $request->dateTo) {
                    continue;
                }

                if ($cell['status'] === 'approved' && $request->status !== 'approved') {
                    continue;
                }

                $cells[$date] = [
                    'status' => $request->status,
                    'requestId' => $request->id,
                ];
            }
        }

        return $cells;
    }

    private function normalizeStatus(string $status): string {
        $status = trim($status);
        if (!in_array($status, ['planned', 'approved'], true)) {
            throw new \InvalidArgumentException('Ungueltiger Urlaubsstatus.');
        }

        return $status;
    }

    private function assertAssistantInVacationPlan(Team $team, string $assistantUid): void {
        foreach ($team->vacationAssistants as $assistant) {
            if ($assistant['uid'] === $assistantUid) {
                return;
            }
        }

        throw new \DomainException('Diese Assistenz ist in der Urlaubssicht dieses Teams nicht enthalten.');
    }
}
