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
     * Array of paths to get migrations from.
     *
     * @var array
     */
    protected $paths = [];

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
     * Add a namespace/path pair.
     *
     * @param string $namespace Namespace of the migrations contained in $path.
     * @param string $path      Path to the files.
     *
     * @return $this
     */
    public function addNamespace($namespace, $path)
    {
        if (array_key_exists($namespace, $this->paths)) {
            throw new \InvalidArgumentException(sprintf(
                'Namespace \'%s\' is already registered.',
                $namespace
            ));
        }

        $this->paths[$namespace] = $path;

        return $this;
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
     * Get all migrations, keyed on the id, value is full class name including namespace.
     *
     * @return array
     */
    public function getAllMigrations()
    {
        $result = [];

        foreach ($this->paths as $namespace => $path) {

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

            $dir = new \DirectoryIterator($path);
            foreach ($dir as $fileInfo) {
                if ($dir->isDot()) {
                    continue;
                }

                if (! fnmatch('Migration*.php', $fileInfo->getFilename())) {
                    continue;
                }

                $class = $fileInfo->getBasename('.php');

                $id = intval(substr($class, 9));

                $result[$id] = $namespace . '\\' . $class;
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * Get all migrations except those already applied.
     *
     * @return array
     */
    public function getAvailableMigrations()
    {
        $allMigrations = $this->getAllMigrations();

        // Shortcut if there's no migrations table.
        if (! $this->hasMigrationsTable()) {
            return $allMigrations;
        }

        $appliedMigrations = $this->getAppliedMigrations();
        return array_diff($allMigrations, $appliedMigrations);
    }

    public function getAppliedMigrations()
    {
        if (! $this->hasMigrationsTable()) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT
                id
            FROM
                `ladder:migrations`'
        );

        $stmt->execute();

        $allMigrations = $this->getAllMigrations();

        while (($id = $stmt->fetchColumn()) !== false) {
            $appliedMigrations[$id] = $allMigrations[$id];
        }

        return $appliedMigrations;
    }

    public function applyMigration($id)
    {
        $instance = $this->createInstance($id);

        try {
            $data = $instance->apply();
        } catch (\Exception $e) {
            // TODO: Tidy up.
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
                :data
            )'
        );

        $stmt->execute([
            'id'   => $id,
            'data' => json_encode($data),
        ]);
    }

    public function rollbackMigration($id)
    {
        $stmt = $this->db->prepare(
            'SELECT
                `data`
            FROM
                `ladder:migrations`
            WHERE
                `id` = :id
            LIMIT
                1'
        );

        $stmt->execute([
            'id' => $id,
        ]);

        if ($data = $stmt->fetchColumn()) {
            $data = json_decode($data, true);
        }

        try {
            $instance = $this->createInstance($id);
            $instance->rollback($data);
        } catch (\Exception $e) {
            // TODO: Tidy up.
            throw $e;
        }

        // Only attempt to record the change if the table exists.
        if ($this->hasMigrationsTable()) {
            $stmt = $this->db->prepare(
                'DELETE FROM
                    `ladder:migrations`
                WHERE
                    `id` = :id'
            );

            $stmt->execute([
                'id' => $id,
            ]);
        }
    }

    protected function createInstance($id)
    {
        $class = $this->getAllMigrations()[$id];
        return new $class($this->container);
    }

    protected function hasMigrationsTable()
    {
        $stmt = $this->db->query(
            'SHOW TABLES LIKE \'ladder:migrations\''
        );

        return ($stmt->fetchColumn() !== false);
    }
}
