<?php

namespace Ladder;

class MigrationManager
{
    /**
     * Container.
     *
     * @var \Pimple
     */
    protected $container;

    /**
     * Migration groups handled by this instance.
     *
     * @var array
     */
    protected $groups = array();

    public function __construct(\Pimple $container)
    {
        $this->container = $container;
    }

    public function __get($key)
    {
        return $this->container[$key];
    }

    /**
     * Get the latest applied migration id.
     *
     * @return int
     */
    public function getCurrentMigration()
    {
        // If the migrations table doesn't exist, return immediately.
        $stmt = $this->db->query(
            'SHOW TABLES LIKE \'ladder:migrations\''
        );

        if ($stmt->fetchColumn() === false) {
            return 0;
        }

        // Table exists, so fetch the latest migration id.
        $stmt = $this->db->prepare(
            'SELECT
                MAX(id)
            FROM
                `ladder:migrations`
            LIMIT
                1'
        );

        $stmt->execute();

        if (! ($id = $stmt->fetchColumn())) {
            return 0;
        } else {
            return $id;
        }
    }
}
