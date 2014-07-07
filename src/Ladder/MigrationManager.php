<?php

namespace Ladder;

use Ladder\Migration\AbstractMigration;

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
     * Get an array of migrations, keyed on the id.
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
                $path = Path::join(getcwd(), $path);
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

                $class = $namespace . '\\' . $fileInfo->getBasename('.php');
                $migration = new $class($this->container);
                $result[$migration->getId()] = $migration;
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

        return array_filter(
            $allMigrations,
            function ($migration) {
                return ! $migration->isApplied();
            }
        );
    }

    public function getAppliedMigrations()
    {
        if (! $this->hasMigrationsTable()) {
            return [];
        }

        $allMigrations = $this->getAllMigrations();

        return array_filter(
            $allMigrations,
            function ($migration) {
                return $migration->isApplied();
            }
        );
    }

    public function applyMigration(AbstractMigration $migration)
    {
        try {
            $data = $migration->apply();
        } catch (\Exception $e) {
            // TODO: Tidy up.
            throw new \Exception(__FILE__ . ':' . __LINE__, 0, $e);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO
                `ladder:migrations` (
                    `id`,
                    `appliedAt`,
                    `data`
                )
            VALUES (
                :id,
                :appliedAt,
                :data
            )'
        );

        $stmt->execute([
            'id'        => $migration->getId(),
            'appliedAt' => date('Y-m-d H:i:s'),
            'data'      => json_encode($data),
        ]);

        // Update the cached value.
        $migration->getAppliedAt(true);
    }

    public function rollbackMigration(AbstractMigration $migration)
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
            'id' => $migration->getId(),
        ]);

        if ($data = $stmt->fetchColumn()) {
            $data = json_decode($data, true);
        }

        try {
            $migration->rollback($data);
        } catch (\Exception $e) {
            // TODO: Tidy up.
            throw new \Exception(__FILE__ . ':' . __LINE__, 0, $e);
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
                'id' => $migration->getId(),
            ]);
        }

        // Update the cached value.
        $migration->getAppliedAt(true);
    }

    public function hasMigrationsTable()
    {
        $stmt = $this->db->query(
            'SHOW TABLES LIKE \'ladder:migrations\''
        );

        return ($stmt->fetchColumn() !== false);
    }
}
