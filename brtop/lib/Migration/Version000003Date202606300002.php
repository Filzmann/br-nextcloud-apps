<?php

declare(strict_types=1);

namespace OCA\BrTop\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date202606300002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('brtop_meetings')) {
            $table = $schema->getTable('brtop_meetings');

            if (!$table->hasColumn('committee_code')) {
                $table->addColumn('committee_code', Types::STRING, ['notnull' => false, 'length' => 32]);
            }
        }

        if ($schema->hasTable('brtop_tops')) {
            $table = $schema->getTable('brtop_tops');

            if (!$table->hasColumn('agenda_item_kind')) {
                $table->addColumn('agenda_item_kind', Types::STRING, ['notnull' => true, 'length' => 32, 'default' => 'discussion']);
            }
        }

        return $schema;
    }
}
