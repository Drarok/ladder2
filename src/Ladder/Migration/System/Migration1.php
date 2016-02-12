<?php

namespace Ladder\Migration\System;

use Ladder\Database\Table;

class Migration1 extends AbstractSystemMigration
{
    public function getName()
    {
        return 'Ladder internal tables';
    }

    public function apply()
    {
        Table::factory('ladder:migrations')
            ->addColumn('id', 'integer', ['null' => false, 'unsigned' => true])
            ->addColumn('appliedAt', 'datetime', ['null' => false])
            ->addColumn('data', 'text')
            ->addIndex('PRIMARY', ['id'])
            ->create()
        ;
    }

    public function rollback(array $data = null)
    {
        Table::factory('ladder:migrations')
            ->drop()
        ;
    }
}
