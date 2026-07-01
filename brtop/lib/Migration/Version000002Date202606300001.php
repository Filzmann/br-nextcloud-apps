<?php

declare(strict_types=1);

namespace OCA\BrTop\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date202606300001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('brtop_meetings')) {
            $table = $schema->getTable('brtop_meetings');

            if (!$table->hasColumn('meeting_type')) {
                $table->addColumn('meeting_type', Types::STRING, ['notnull' => true, 'length' => 64, 'default' => 'custom']);
            }

            if (!$table->hasColumn('invitation_date')) {
                $table->addColumn('invitation_date', Types::STRING, ['notnull' => false, 'length' => 32]);
            }

            if (!$table->hasColumn('invitation_status')) {
                $table->addColumn('invitation_status', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'not_created']);
            }
        }

        if ($schema->hasTable('brtop_tops')) {
            $table = $schema->getTable('brtop_tops');

            if (!$table->hasColumn('parent_id')) {
                $table->addColumn('parent_id', Types::BIGINT, ['notnull' => false]);
            }

            if (!$table->hasColumn('level')) {
                $table->addColumn('level', Types::INTEGER, ['notnull' => true, 'default' => 1]);
            }

            if (!$table->hasColumn('protocol_content')) {
                $table->addColumn('protocol_content', Types::TEXT, ['notnull' => false]);
            }
        }

        if (!$schema->hasTable('brtop_invitation_recipients')) {
            $table = $schema->createTable('brtop_invitation_recipients');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('meeting_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('user_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('display_name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('group_name', Types::STRING, ['notnull' => true, 'length' => 128, 'default' => 'Betriebsrat']);
            $table->addColumn('snapshot_position', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['meeting_id'], 'brtop_inv_rec_meeting');
        }

        if (!$schema->hasTable('brtop_documents')) {
            $table = $schema->createTable('brtop_documents');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('meeting_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('document_type', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('file_path', Types::STRING, ['notnull' => false, 'length' => 1024]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['meeting_id'], 'brtop_docs_meeting');
        }

        return $schema;
    }
}
