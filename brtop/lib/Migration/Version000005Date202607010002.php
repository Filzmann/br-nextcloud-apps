<?php

declare(strict_types=1);

namespace OCA\BrTop\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000005Date202607010002 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if ($schema->hasTable('brtop_tops')) {
            $table = $schema->getTable('brtop_tops');

            if (!$table->hasColumn('invitation_note')) {
                $table->addColumn('invitation_note', Types::TEXT, ['notnull' => false]);
            }

            if (!$table->hasColumn('attachment_paths')) {
                $table->addColumn('attachment_paths', Types::TEXT, ['notnull' => false]);
            }

            if (!$table->hasColumn('resolution_count')) {
                $table->addColumn('resolution_count', Types::INTEGER, ['notnull' => true, 'default' => 0]);
            }
        }

        return $schema;
    }
}
