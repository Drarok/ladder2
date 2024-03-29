<?php

namespace Zerifas\Ladder\Migration\System;

use Zerifas\Ladder\Database\Table;

class Migration1 extends AbstractSystemMigration
{
    public function getName()
    {
        return 'Ladder internal tables';
    }

    public function apply()
    {
        $this->table('ladder:migrations')
            ->addColumn('id', 'integer', ['null' => false, 'unsigned' => true])
            ->addColumn('appliedAt', 'datetime', ['null' => false])
            ->addColumn('data', 'text')
            ->addIndex('PRIMARY', ['id'])
            ->create()
        ;
    }

    public function rollback(array $data = null)
    {
        $this->table('ladder:migrations')
            ->drop()
        ;
    }
}
