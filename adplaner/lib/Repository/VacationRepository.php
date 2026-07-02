<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class VacationRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function findForUsersInYear(array $assistantUids, int $year): array {
        $assistantUids = array_values(array_unique(array_filter(array_map('strval', $assistantUids))));
        if ($assistantUids === []) {
            return [];
        }

        $from = sprintf('%04d-01-01', $year);
        $to = sprintf('%04d-12-31', $year);

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_vacation_requests')
            ->where($qb->expr()->in('assistant_uid', $qb->createNamedParameter($assistantUids, IQueryBuilder::PARAM_STR_ARRAY)))
            ->andWhere($qb->expr()->lte('date_from', $qb->createNamedParameter($to)))
            ->andWhere($qb->expr()->gte('date_to', $qb->createNamedParameter($from)))
            ->orderBy('date_from', 'ASC')
            ->addOrderBy('assistant_uid', 'ASC');

        return $qb->executeQuery()->fetchAll();
    }

    public function findById(int $requestId): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_vacation_requests')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)));

        $row = $qb->executeQuery()->fetch();

        return $row === false ? null : $row;
    }

    public function findCoveringDate(string $assistantUid, string $date): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_vacation_requests')
            ->where($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)))
            ->andWhere($qb->expr()->lte('date_from', $qb->createNamedParameter($date)))
            ->andWhere($qb->expr()->gte('date_to', $qb->createNamedParameter($date)))
            ->orderBy('id', 'DESC')
            ->setMaxResults(1);

        $row = $qb->executeQuery()->fetch();

        return $row === false ? null : $row;
    }

    public function create(string $assistantUid, string $dateFrom, string $dateTo, string $note, string $status = 'planned', ?string $updatedByUid = null): int {
        $now = new DateTimeImmutable();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('adp_vacation_requests')
            ->values([
                'assistant_uid' => $qb->createNamedParameter($assistantUid),
                'date_from' => $qb->createNamedParameter($dateFrom),
                'date_to' => $qb->createNamedParameter($dateTo),
                'status' => $qb->createNamedParameter($status),
                'note' => $qb->createNamedParameter($note),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE),
                'updated_by_uid' => $qb->createNamedParameter($updatedByUid),
            ]);
        $qb->executeStatement();

        return (int)$this->db->lastInsertId('adp_vacation_requests');
    }

    public function updateStatus(int $requestId, string $status, string $updatedByUid): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('adp_vacation_requests')
            ->set('status', $qb->createNamedParameter($status))
            ->set('updated_by_uid', $qb->createNamedParameter($updatedByUid))
            ->set('updated_at', $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function delete(int $requestId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('adp_vacation_requests')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($requestId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
