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
        if (! $this->hasMigrationsTable()) {
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

    /**
     * Get all migrations, keyed on the id, value is the file path.
     *
     * @return array
     */
    public function getAllMigrations()
    {
        $path = $this->config['migrations']['path'];

        if (! $path) {
            throw new \InvalidArgumentException('Invalid migrations path.');
        }

        // Convert relative paths into absolute.
        if ($path[0] != '/' && $path[0] != '\\') {
            $path = Path::join($this->rootPath, $path);
        }

        if (! is_dir($path)) {
            throw new \InvalidArgumentException('Invalid migrations path: ' . $path);
        }

        $result = [];

        $dir = new \DirectoryIterator($path);
        foreach ($dir as $fileInfo) {
            if ($dir->isDot()) {
                continue;
            }

            if ($fileInfo->getExtension() != 'php') {
                continue;
            }

            $id = intval(substr($fileInfo->getBasename('.php'), 9));

            $result[$id] = $fileInfo->getPathname();
        }

        return $result;
    }

    /**
     * Get all migrations except those already applied.
     *
     * @return array
     */
    public function getAvailableMigrations()
    {
        // Shortcut if there's no migrations table.
        if (! $this->hasMigrationsTable()) {
            return $this->getAllMigrations();
        }

        $stmt = $this->db->prepare(
            'SELECT
                id
            FROM
                `ladder:migrations`'
        );

        $stmt->execute();

        $appliedMigrations = [];
        while (($id = $stmt->fetchColumn()) !== false) {
            $appliedMigrations[$id] = true;
        }

        return Arr::filter(
            $this->getAllMigrations(),
            function ($id, $path) use ($appliedMigrations) {
                return ! array_key_exists($id, $appliedMigrations);
            }
        );
    }

    public function applyMigration($id)
    {
        if (! $this->hasMigrationsTable()) {
            $this->createMigrationsTable();
        }

        $instance = $this->createInstance($id);

        try {
            $instance->apply();
        } catch (\Exception $e) {
            throw $e;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO
                `ladder:migrations` (
                    `id`,
                    `data`
                )
            VALUES (
                :id,
                NULL
            )'
        );

        $stmt->execute([
            'id' => $id,
        ]);
    }

    protected function createInstance($id)
    {
        $pathname = $this->getAllMigrations()[$id];
        $class = $this->config['migrations']['namespace'] . '\\Migration' . $id;
        require_once $pathname;
        return new $class($this->container);
    }

    protected function hasMigrationsTable()
    {
        $stmt = $this->db->query(
            'SHOW TABLES LIKE \'ladder:migrations\''
        );

        return ($stmt->fetchColumn() !== false);
    }

    protected function createMigrationsTable()
    {
        // TODO: This might work better as a system migration or something?
        $this->db->query(
            'CREATE TABLE `ladder:migrations` (
                `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `applied` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `data` TEXT NULL
            )'
        );
    }
}
