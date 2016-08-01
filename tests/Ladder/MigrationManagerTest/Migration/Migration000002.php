<?php

namespace App\Migration;

use Zerifas\Ladder\Database\Table;
use Zerifas\Ladder\Migration\AbstractMigration;

class Migration000002 extends AbstractMigration
{
    public function getName()
    {
        return 'Example Migration 2';
    }

    public function apply()
    {
        throw new \InvalidArgumentException('Deliberate failure');
    }

    public function rollback(array $data = null)
    {
        throw new \InvalidArgumentException('Deliberate failure');
    }
}
