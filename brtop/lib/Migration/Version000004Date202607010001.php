<?php

declare(strict_types=1);

namespace OCA\BrTop\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000004Date202607010001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('brtop_protocol_blocks')) {
            $table = $schema->createTable('brtop_protocol_blocks');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
            $table->addColumn('meeting_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('top_id', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('block_position', Types::INTEGER, ['notnull' => true]);
            $table->addColumn('block_type', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'text']);
            $table->addColumn('content', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
            $table->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['meeting_id'], 'brtop_proto_blocks_meeting');
            $table->addIndex(['top_id'], 'brtop_proto_blocks_top');
        }

        return $schema;
    }
}
