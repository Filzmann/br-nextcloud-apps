<?php

declare(strict_types=1);

namespace OCA\AdPlaner\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date202607020001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('adp_team_settings')) {
            $table = $schema->createTable('adp_team_settings');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('team_code', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('display_name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('settings_json', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['team_code'], 'adp_team_code_unique');
        }

        if (!$schema->hasTable('adp_shift_slots')) {
            $table = $schema->createTable('adp_shift_slots');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('team_code', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('plan_month', Types::STRING, ['notnull' => true, 'length' => 7]);
            $table->addColumn('work_date', Types::STRING, ['notnull' => true, 'length' => 10]);
            $table->addColumn('segment_key', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('label', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('starts_at', Types::STRING, ['notnull' => true, 'length' => 5]);
            $table->addColumn('ends_at', Types::STRING, ['notnull' => true, 'length' => 5]);
            $table->addColumn('enabled', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['team_code', 'work_date', 'segment_key'], 'adp_slot_unique');
            $table->addIndex(['team_code', 'plan_month'], 'adp_slot_month');
        }

        if (!$schema->hasTable('adp_shift_candidates')) {
            $table = $schema->createTable('adp_shift_candidates');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('slot_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('assistant_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_by_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['slot_id', 'assistant_uid'], 'adp_slot_user_unique');
            $table->addIndex(['assistant_uid'], 'adp_candidate_user');
        }

        if (!$schema->hasTable('adp_day_notes')) {
            $table = $schema->createTable('adp_day_notes');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('team_code', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('work_date', Types::STRING, ['notnull' => true, 'length' => 10]);
            $table->addColumn('note', Types::TEXT, ['notnull' => false]);
            $table->addColumn('updated_by_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['team_code', 'work_date'], 'adp_day_note_unique');
        }

        if (!$schema->hasTable('adp_vacation_requests')) {
            $table = $schema->createTable('adp_vacation_requests');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('assistant_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('date_from', Types::STRING, ['notnull' => true, 'length' => 10]);
            $table->addColumn('date_to', Types::STRING, ['notnull' => true, 'length' => 10]);
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'planned']);
            $table->addColumn('note', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_by_uid', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['assistant_uid', 'date_from', 'date_to'], 'adp_vac_user_dates');
        }

        return $schema;
    }
}
