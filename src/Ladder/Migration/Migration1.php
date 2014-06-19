<?php

namespace Ladder\Migration;

use Ladder\Migration\AbstractMigration;

class Migration1 extends AbstractMigration
{
    public function apply()
    {
        $this->db->query(
            'CREATE TABLE `ladder:migrations` (
                `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `applied` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `data` TEXT NULL
            )'
        );
    }

    public function rollback()
    {
        $this->db->query(
            'DROP TABLE `ladder:migrations`'
        );
    }
}
