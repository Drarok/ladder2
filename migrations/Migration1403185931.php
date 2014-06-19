<?php

namespace Application\Migration;

use Ladder\Migration\AbstractMigration;

class Migration1403185931 extends AbstractMigration
{
    public function apply()
    {
        $this->db->query(
            'CREATE TABLE `ladder:users` (
                `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(32) NOT NULL
            )'
        );
    }

    public function rollback()
    {
        $this->db->query(
            'DROP TABLE `ladder:users`'
        );
    }
}
