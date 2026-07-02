<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Store;

use OCA\AdPlaner\Model\VacationRequest;
use OCA\AdPlaner\Repository\VacationRepository;

class VacationStore {
    public function __construct(
        private VacationRepository $repository
    ) {
    }

    public function forUsersInYear(array $assistantUids, int $year): array {
        return array_map(
            static fn(array $row): VacationRequest => VacationRequest::fromRow($row),
            $this->repository->findForUsersInYear($assistantUids, $year)
        );
    }

    public function findById(int $requestId): ?VacationRequest {
        $row = $this->repository->findById($requestId);

        return $row === null ? null : VacationRequest::fromRow($row);
    }

    public function findCoveringDate(string $assistantUid, string $date): ?VacationRequest {
        $row = $this->repository->findCoveringDate($assistantUid, $date);

        return $row === null ? null : VacationRequest::fromRow($row);
    }

    public function create(string $assistantUid, string $dateFrom, string $dateTo, string $note, string $status = 'planned', ?string $updatedByUid = null): int {
        return $this->repository->create($assistantUid, $dateFrom, $dateTo, $note, $status, $updatedByUid);
    }

    public function updateStatus(int $requestId, string $status, string $updatedByUid): void {
        $this->repository->updateStatus($requestId, $status, $updatedByUid);
    }

    public function delete(int $requestId): void {
        $this->repository->delete($requestId);
    }
}
