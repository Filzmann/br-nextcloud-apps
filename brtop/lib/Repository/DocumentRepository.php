<?php

declare(strict_types=1);

namespace OCA\BrTop\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DocumentRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function findForMeeting(int $meetingId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('brtop_documents')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->orderBy('created_at', 'DESC')
            ->addOrderBy('id', 'DESC');

        return $qb->executeQuery()->fetchAll();
    }

    public function insert(int $meetingId, string $type, string $title, string $path): void {
        $qb = $this->db->getQueryBuilder();
        $qb->insert('brtop_documents')
            ->values([
                'meeting_id' => $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT),
                'document_type' => $qb->createNamedParameter($type),
                'title' => $qb->createNamedParameter($title),
                'file_path' => $qb->createNamedParameter($path),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();
    }
}
