<?php

declare(strict_types=1);

namespace OCA\BrTop\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class MeetingRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function findRecentByOwner(string $uid, int $limit = 50): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_meetings')
            ->where($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)))
            ->orderBy('meeting_date', 'DESC')
            ->addOrderBy('id', 'DESC')
            ->setMaxResults($limit);

        return $qb->executeQuery()->fetchAll();
    }

    public function findByIdAndOwner(int $meetingId, string $uid): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_meetings')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)));

        $meeting = $qb->executeQuery()->fetch();

        return $meeting === false ? null : $meeting;
    }

    public function insert(
        string $uid,
        string $title,
        string $meetingDate,
        string $meetingTime,
        string $location,
        string $meetingType,
        string $committeeCode,
        ?string $invitationDate,
        string $invitationStatus
    ): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('brtop_meetings')
            ->values([
                'owner_uid' => $qb->createNamedParameter($uid),
                'title' => $qb->createNamedParameter($title),
                'meeting_date' => $qb->createNamedParameter($meetingDate),
                'meeting_time' => $qb->createNamedParameter($meetingTime),
                'location' => $qb->createNamedParameter($location),
                'meeting_type' => $qb->createNamedParameter($meetingType),
                'committee_code' => $qb->createNamedParameter($committeeCode),
                'invitation_date' => $qb->createNamedParameter($invitationDate),
                'invitation_status' => $qb->createNamedParameter($invitationStatus),
                'status' => $qb->createNamedParameter('draft'),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();

        return (int)$this->db->lastInsertId('brtop_meetings');
    }

    public function markInvitationCreated(int $meetingId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('brtop_meetings')
            ->set('invitation_status', $qb->createNamedParameter('created'))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function transactional(callable $callback) {
        $this->db->beginTransaction();

        try {
            $result = $callback();
            $this->db->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
