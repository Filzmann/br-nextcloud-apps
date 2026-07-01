<?php

declare(strict_types=1);

namespace OCA\BrTop\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AgendaItemRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function findForMeeting(int $meetingId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_tops')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->orderBy('position', 'ASC')
            ->addOrderBy('id', 'ASC');

        return $qb->executeQuery()->fetchAll();
    }

    public function findOneForMeeting(int $meetingId, int $topId): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_tops')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)));

        $top = $qb->executeQuery()->fetch();

        return $top === false ? null : $top;
    }

    public function insert(
        int $meetingId,
        int $position,
        string $type,
        string $subject,
        string $personName,
        string $legalBasis,
        string $resolutionText,
        bool $requiresResolution,
        ?int $parentId,
        int $level,
        string $agendaItemKind,
        string $protocolContent
    ): int {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('brtop_tops')
            ->values([
                'meeting_id' => $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT),
                'position' => $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT),
                'type' => $qb->createNamedParameter($type),
                'subject' => $qb->createNamedParameter($subject),
                'person_name' => $qb->createNamedParameter($personName),
                'legal_basis' => $qb->createNamedParameter($legalBasis),
                'requires_resolution' => $qb->createNamedParameter($requiresResolution, IQueryBuilder::PARAM_BOOL),
                'resolution_text' => $qb->createNamedParameter($resolutionText),
                'parent_id' => $parentId === null
                    ? $qb->createNamedParameter(null)
                    : $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT),
                'level' => $qb->createNamedParameter($level, IQueryBuilder::PARAM_INT),
                'agenda_item_kind' => $qb->createNamedParameter($agendaItemKind),
                'protocol_content' => $qb->createNamedParameter($protocolContent),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();

        return (int)$this->db->lastInsertId('brtop_tops');
    }
}
