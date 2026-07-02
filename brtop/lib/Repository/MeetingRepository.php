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

    public function findRecentByOwner(string $uid, ?int $limit = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_meetings')
            ->where($qb->expr()->eq('owner_uid', $qb->createNamedParameter($uid)))
            ->orderBy('meeting_date', 'DESC')
            ->addOrderBy('id', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

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
        string $invitationStatus,
        string $status = 'draft'
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
                'status' => $qb->createNamedParameter($status),
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

    public function updateFromData(array $data): void {
        $meetingId = (int)($data['id'] ?? 0);
        if ($meetingId <= 0) {
            throw new \InvalidArgumentException('Meeting-Update benötigt eine positive ID.');
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update('brtop_meetings')
            ->set('title', $qb->createNamedParameter((string)($data['title'] ?? '')))
            ->set('meeting_date', $qb->createNamedParameter((string)($data['meeting_date'] ?? '')))
            ->set('meeting_time', $qb->createNamedParameter((string)($data['meeting_time'] ?? '')))
            ->set('location', $qb->createNamedParameter((string)($data['location'] ?? '')))
            ->set('meeting_type', $qb->createNamedParameter((string)($data['meeting_type'] ?? 'custom')))
            ->set('committee_code', $qb->createNamedParameter((string)($data['committee_code'] ?? '')))
            ->set('invitation_date', $qb->createNamedParameter($data['invitation_date'] ?? null))
            ->set('invitation_status', $qb->createNamedParameter((string)($data['invitation_status'] ?? 'not_created')))
            ->set('status', $qb->createNamedParameter((string)($data['status'] ?? 'draft')))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function deleteInvitationRecipients(int $meetingId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('brtop_invitation_recipients')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function deleteById(int $meetingId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('brtop_meetings')
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
