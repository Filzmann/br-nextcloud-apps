<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Repository;

use DateTimeImmutable;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TeamSettingsRepository {
    public function __construct(
        private IDBConnection $db
    ) {
    }

    public function findByCode(string $teamCode): ?array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from('adp_team_settings')
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)));

        $row = $qb->executeQuery()->fetch();

        return $row === false ? null : $row;
    }

    public function save(string $teamCode, string $displayName, array $settings): void {
        $existing = $this->findByCode($teamCode);
        $now = new DateTimeImmutable();
        $json = json_encode($settings, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \InvalidArgumentException('Team-Einstellungen konnten nicht serialisiert werden.');
        }

        if ($existing === null) {
            $qb = $this->db->getQueryBuilder();
            $qb->insert('adp_team_settings')
                ->values([
                    'team_code' => $qb->createNamedParameter($teamCode),
                    'display_name' => $qb->createNamedParameter($displayName),
                    'settings_json' => $qb->createNamedParameter($json),
                    'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE),
                    'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE),
                ]);
            $qb->executeStatement();
            return;
        }

        $qb = $this->db->getQueryBuilder();
        $qb->update('adp_team_settings')
            ->set('display_name', $qb->createNamedParameter($displayName))
            ->set('settings_json', $qb->createNamedParameter($json))
            ->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATE))
            ->where($qb->expr()->eq('team_code', $qb->createNamedParameter($teamCode)));
        $qb->executeStatement();
    }
}
