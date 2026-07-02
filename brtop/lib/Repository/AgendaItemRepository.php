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
        string $protocolContent,
        string $invitationNote,
        string $attachmentPaths,
        int $resolutionCount
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
                'invitation_note' => $qb->createNamedParameter($invitationNote),
                'attachment_paths' => $qb->createNamedParameter($attachmentPaths),
                'resolution_count' => $qb->createNamedParameter($resolutionCount, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();

        return (int)$this->db->lastInsertId('brtop_tops');
    }

    public function updateHierarchyState(
        int $meetingId,
        int $topId,
        ?int $parentId,
        int $level,
        int $position
    ): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('brtop_tops')
            ->set(
                'parent_id',
                $parentId === null
                    ? $qb->createNamedParameter(null)
                    : $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)
            )
            ->set('level', $qb->createNamedParameter($level, IQueryBuilder::PARAM_INT))
            ->set('position', $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function updateFromData(array $data): void {
        $meetingId = (int)($data['meeting_id'] ?? 0);
        $topId = (int)($data['id'] ?? 0);
        if ($meetingId <= 0 || $topId <= 0) {
            throw new \InvalidArgumentException('TOP-Update benötigt Meeting-ID und TOP-ID.');
        }

        $parentId = isset($data['parent_id']) && (int)$data['parent_id'] > 0 ? (int)$data['parent_id'] : null;
        $qb = $this->db->getQueryBuilder();
        $qb->update('brtop_tops')
            ->set('position', $qb->createNamedParameter((int)($data['position'] ?? 0), IQueryBuilder::PARAM_INT))
            ->set('type', $qb->createNamedParameter((string)($data['type'] ?? 'other')))
            ->set('subject', $qb->createNamedParameter((string)($data['subject'] ?? '')))
            ->set('person_name', $qb->createNamedParameter((string)($data['person_name'] ?? '')))
            ->set('legal_basis', $qb->createNamedParameter((string)($data['legal_basis'] ?? '')))
            ->set('requires_resolution', $qb->createNamedParameter((bool)($data['requires_resolution'] ?? false), IQueryBuilder::PARAM_BOOL))
            ->set('resolution_text', $qb->createNamedParameter((string)($data['resolution_text'] ?? '')))
            ->set(
                'parent_id',
                $parentId === null
                    ? $qb->createNamedParameter(null)
                    : $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)
            )
            ->set('level', $qb->createNamedParameter((int)($data['level'] ?? 1), IQueryBuilder::PARAM_INT))
            ->set('agenda_item_kind', $qb->createNamedParameter((string)($data['agenda_item_kind'] ?? '')))
            ->set('protocol_content', $qb->createNamedParameter((string)($data['protocol_content'] ?? '')))
            ->set('invitation_note', $qb->createNamedParameter((string)($data['invitation_note'] ?? '')))
            ->set('attachment_paths', $qb->createNamedParameter((string)($data['attachment_paths'] ?? '')))
            ->set('resolution_count', $qb->createNamedParameter((int)($data['resolution_count'] ?? 0), IQueryBuilder::PARAM_INT))
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function deleteOneForMeeting(int $meetingId, int $topId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('brtop_tops')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('id', $qb->createNamedParameter($topId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function deleteForMeeting(int $meetingId): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('brtop_tops')
            ->where($qb->expr()->eq('meeting_id', $qb->createNamedParameter($meetingId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }
}
