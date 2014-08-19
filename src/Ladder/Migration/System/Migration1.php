<?php

namespace Ladder\Migration\System;

use Ladder\Database\Table;
use Ladder\Migration\AbstractMigration;

class Migration1 extends AbstractMigration
{
    public function getName()
    {
        return 'Ladder internal tables';
    }

    public function apply()
    {
        Table::factory('ladder:migrations')
            ->addColumn('id', 'integer', array('null' => false, 'unsigned' => true))
            ->addColumn('appliedAt', 'datetime', array('null' => false))
            ->addColumn('data', 'text')
            ->addIndex('PRIMARY', array('id'))
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
