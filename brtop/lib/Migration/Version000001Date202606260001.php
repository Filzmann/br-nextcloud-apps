<?php

declare(strict_types=1);

namespace OCA\BrTop\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date202606260001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('brtop_meetings')) {
            $table = $schema->createTable('brtop_meetings');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('owner_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('meeting_date', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('meeting_time', Types::STRING, ['notnull' => false, 'length' => 16]);
            $table->addColumn('location', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'draft']);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['owner_uid'], 'brtop_meetings_owner');
        }

        if (!$schema->hasTable('brtop_tops')) {
            $table = $schema->createTable('brtop_tops');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('meeting_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('position', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('subject', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('person_name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('legal_basis', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('requires_resolution', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->addColumn('resolution_text', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['meeting_id'], 'brtop_tops_meeting');
        }

        if (!$schema->hasTable('brtop_members')) {
            $table = $schema->createTable('brtop_members');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('owner_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('list_name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('list_rank', Types::INTEGER, ['notnull' => false]);
            $table->addColumn('role', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'regular']);
            $table->addColumn('active', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['owner_uid'], 'brtop_members_owner');
        }

        if (!$schema->hasTable('brtop_absences')) {
            $table = $schema->createTable('brtop_absences');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('member_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('date_from', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('date_to', Types::STRING, ['notnull' => true, 'length' => 32]);
            $table->addColumn('reason', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['member_id'], 'brtop_absences_member');
        }

        if (!$schema->hasTable('brtop_invitations')) {
            $table = $schema->createTable('brtop_invitations');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('meeting_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('member_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('invitation_type', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'initial']);
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'planned']);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['meeting_id'], 'brtop_inv_meeting');
        }

        return $schema;
    }
}
