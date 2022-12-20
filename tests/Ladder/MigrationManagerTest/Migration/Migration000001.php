<?php

namespace App\Migration;

use Zerifas\Ladder\Database\Table;
use Zerifas\Ladder\Migration\AbstractMigration;

class Migration000001 extends AbstractMigration
{
    public function getName()
    {
        return 'Example Migration 1';
    }

    public function apply()
    {
        $this->table('example')
            ->create()
        ;

        return [
            'userId' => 151,
        ];
    }

    public function rollback(array $data = null)
    {
        if (!isset($data['userId'])) {
            throw new \InvalidArgumentException('Missing data key: userId');
        }

        $this->table('example')
            ->drop()
        ;
    }
}
