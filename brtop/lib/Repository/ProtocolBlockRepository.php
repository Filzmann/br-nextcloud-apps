<?php

declare(strict_types=1);

namespace OCA\BrTop\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProtocolBlockRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function findForMeeting(int $meetingId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_protocol_blocks')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->orderBy('top_id', 'ASC')
            ->addOrderBy('block_position', 'ASC')
            ->addOrderBy('id', 'ASC');

        return $qb->executeQuery()->fetchAll();
    }

    public function findForMeetingGrouped(int $meetingId): array {
        $grouped = [];

        foreach ($this->findForMeeting($meetingId) as $block) {
            $topId = (int)$block['top_id'];
            $grouped[$topId][] = $block;
        }

        return $grouped;
    }

    public function findOneForMeeting(int $meetingId, int $topId, int $blockId): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_protocol_blocks')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($blockId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('top_id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)));

        $block = $qb->executeQuery()->fetch();

        return $block === false ? null : $block;
    }

    public function insert(int $meetingId, int $topId, string $blockType, string $content): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('brtop_protocol_blocks')
            ->values([
                'meeting_id' => $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT),
                'top_id' => $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT),
                'block_position' => $qb->createNamedParameter($this->nextPosition($meetingId, $topId), IQueryBuilder::PARAM_INT),
                'block_type' => $qb->createNamedParameter($blockType),
                'content' => $qb->createNamedParameter($content),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
                'updated_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();

        return (int)$this->db->lastInsertId('brtop_protocol_blocks');
    }

    public function updateContent(int $meetingId, int $topId, int $blockId, string $content): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->update('brtop_protocol_blocks')
            ->set('content', $qb->createNamedParameter($content))
            ->set('updated_at', $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($blockId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('top_id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)));

        return $qb->executeStatement() > 0;
    }

    public function deleteForMeeting(int $meetingId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('brtop_protocol_blocks')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function deleteForTop(int $meetingId, int $topId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('brtop_protocol_blocks')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('top_id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    private function nextPosition(int $meetingId, int $topId): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select('block_position')
            ->from('brtop_protocol_blocks')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('top_id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)))
            ->orderBy('block_position', 'DESC')
            ->setMaxResults(1);

        $max = $qb->executeQuery()->fetchOne();

        return ((int)$max) + 1;
    }
}
