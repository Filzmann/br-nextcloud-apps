<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ShiftPlanRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function findSlotsForMonth(string $teamCode, string $month): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_shift_slots')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)))
            ->orderBy('work_date', 'ASC')
            ->addOrderBy('id', 'ASC');

        return $qb->executeQuery()->fetchAll();
    }

    public function findSlot(int $slotId, string $teamCode, string $month): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_shift_slots')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('plan_month', $qb->createNamedParameter($month)));

        $row = $qb->executeQuery()->fetch();

        return $row === false ? null : $row;
    }

    public function insertSlot(
        string $teamCode,
        string $month,
        string $workDate,
        string $segmentKey,
        string $label,
        string $startsAt,
        string $endsAt,
        bool $enabled
    ): int {
        $now = new DateTimeImmutable();
        $qb = $this->db->getQueryBuilder();
        $qb->insert('adp_shift_slots')
            ->values([
                'team_code' => $qb->createNamedParameter($teamCode),
                'plan_month' => $qb->createNamedParameter($month),
                'work_date' => $qb->createNamedParameter($workDate),
                'segment_key' => $qb->createNamedParameter($segmentKey),
                'label' => $qb->createNamedParameter($label),
                'starts_at' => $qb->createNamedParameter($startsAt),
                'ends_at' => $qb->createNamedParameter($endsAt),
                'enabled' => $qb->createNamedParameter($enabled ? 1 : 0, IQueryBuilder::PARAM_INT),
                'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE),
                'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();

        return (int)$this->db->lastInsertId('adp_shift_slots');
    }

    public function updateSlotDefinition(int $slotId, string $label, string $startsAt, string $endsAt, bool $enabled): void {
        $qb = $this->db->getQueryBuilder();
        $qb->update('adp_shift_slots')
            ->set('label', $qb->createNamedParameter($label))
            ->set('starts_at', $qb->createNamedParameter($startsAt))
            ->set('ends_at', $qb->createNamedParameter($endsAt))
            ->set('enabled', $qb->createNamedParameter($enabled ? 1 : 0, IQueryBuilder::PARAM_INT))
            ->set('updated_at', $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE))
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)));
        $qb->executeStatement();
    }

    public function candidatesForSlotIds(array $slotIds): array {
        $slotIds = array_values(array_filter(array_map('intval', $slotIds), static fn(int $id): bool => $id > 0));
        if ($slotIds === []) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_shift_candidates')
            ->where($qb->expr()->in('slot_id', $qb->createNamedParameter($slotIds, IQueryBuilder::PARAM_INT_ARRAY)))
            ->orderBy('created_at', 'ASC')
            ->addOrderBy('assistant_uid', 'ASC');

        $rows = $qb->executeQuery()->fetchAll();
        $bySlot = [];
        foreach ($rows as $row) {
            $slotId = (int)$row['slot_id'];
            $bySlot[$slotId][] = $row;
        }

        return $bySlot;
    }

    public function addCandidate(int $slotId, string $assistantUid, string $createdByUid): void {
        if ($this->candidateExists($slotId, $assistantUid)) {
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->insert('adp_shift_candidates')
            ->values([
                'slot_id' => $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT),
                'assistant_uid' => $qb->createNamedParameter($assistantUid),
                'created_by_uid' => $qb->createNamedParameter($createdByUid),
                'created_at' => $qb->createNamedParameter(new DateTimeImmutable(), IQueryBuilder::PARAM_DATE),
            ]);
        $qb->executeStatement();
    }

    public function removeCandidate(int $slotId, string $assistantUid): void {
        $qb = $this->db->getQueryBuilder();
        $qb->delete('adp_shift_candidates')
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)));
        $qb->executeStatement();
    }

    public function dayNotes(string $teamCode, string $month): array {
        $from = $month . '-01';
        $to = (new \DateTimeImmutable($from))->modify('last day of this month')->format('Y-m-d');

        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_day_notes')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->gte('work_date', $qb->createNamedParameter($from)))
            ->andWhere($qb->expr()->lte('work_date', $qb->createNamedParameter($to)));

        $notes = [];
        foreach ($qb->executeQuery()->fetchAll() as $row) {
            $notes[(string)$row['work_date']] = (string)($row['note'] ?? '');
        }

        return $notes;
    }

    public function saveDayNote(string $teamCode, string $workDate, string $note, string $updatedByUid): void {
        $existing = $this->findDayNote($teamCode, $workDate);
        $now = new DateTimeImmutable();

        if ($existing === null) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('adp_day_notes')
                ->values([
                    'team_code' => $qb->createNamedParameter($teamCode),
                    'work_date' => $qb->createNamedParameter($workDate),
                    'note' => $qb->createNamedParameter($note),
                    'updated_by_uid' => $qb->createNamedParameter($updatedByUid),
                    'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE),
                ]);
            $qb->executeStatement();
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update('adp_day_notes')
            ->set('note', $qb->createNamedParameter($note))
            ->set('updated_by_uid', $qb->createNamedParameter($updatedByUid))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE))
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));
        $qb->executeStatement();
    }

    private function candidateExists(int $slotId, string $assistantUid): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from('adp_shift_candidates')
            ->where($qb->expr()->eq('slot_id', $qb->createNamedParameter($slotId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('assistant_uid', $qb->createNamedParameter($assistantUid)))
            ->setMaxResults(1);

        return $qb->executeQuery()->fetch() !== false;
    }

    private function findDayNote(string $teamCode, string $workDate): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_day_notes')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)))
            ->andWhere($qb->expr()->eq('work_date', $qb->createNamedParameter($workDate)));

        $row = $qb->executeQuery()->fetch();

        return $row === false ? null : $row;
    }
}
