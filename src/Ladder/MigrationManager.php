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
     * Constructor.
     *
     * @param Pimple $container Container.
     */
    public function __construct(\Pimple $container)
    {
        $this->container = $container;
    }

    /**
     * Magic getter, passes control to the container.
     *
     * @param string $key Dependency key.
     *
     * @return mixed
     */
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
     * Get all migrations, regardless of whether or not they are applied.
     *
     * @return Generator
     */
    public function getAllMigrations()
    {
        // Grab all the appliedAt dates in one go for efficiency's sake.
        $stmt = $this->db->query(
            'SELECT
                `id`,
                `appliedAt`
            FROM
                `ladder:migrations`
            ORDER BY
                `id`'
        );

        $appliedMigrations = [];
        while ($row = $stmt->fetch()) {
            $appliedMigrations[$row['id']] = $row['appliedAt'];
        }

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

                if (array_key_exists($migration->getId(), $appliedMigrations)) {
                    $migration->setAppliedAt($appliedMigrations[$migration->getId()]);
                }

                yield $migration;
            }
        }
    }

    /**
     * Get all migrations except those already applied.
     *
     * @return Generator
     */
    public function getAvailableMigrations()
    {
        foreach ($this->getAllMigrations() as $migration) {
            if (! $migration->isApplied()) {
                yield $migration;
            }
        }
    }

    /**
     * Quick way to check if there are any migrations available to apply.
     *
     * @return bool
     */
    public function hasAvailableMigrations()
    {
        foreach ($this->getAllMigrations() as $migration) {
            if (! $migration->isApplied()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all the migrations that are applied to the database.
     *
     * @return Generator
     */
    public function getAppliedMigrations()
    {
        foreach ($this->getAllMigrations() as $migration) {
            if ($migration->isApplied()) {
                yield $migration;
            }
        }
    }

    /**
     * Quick way to check if there are any migrations applied.
     *
     * @return bool
     */
    public function hasAppliedMigrations()
    {
        foreach ($this->getAllMigrations() as $migration) {
            if ($migration->isApplied()) {
                return true;
            }
        }

        return false;
    }

    public function applyMigration(AbstractMigration $migration)
    {
        $appliedAt = date('Y-m-d H:i:s');

        try {
            $data = $migration->apply();
            $migration->setAppliedAt($appliedAt);
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
            'appliedAt' => $appliedAt,
            'data'      => json_encode($data),
        ]);
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
            $migration->setAppliedAt(null);
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
    }

    /**
     * Does the migrations table exist?
     *
     * @return bool
     */
    public function hasMigrationsTable()
    {
        $stmt = $this->db->query(
            'SHOW TABLES LIKE \'ladder:migrations\''
        );

        return ($stmt->fetchColumn() !== false);
    }
}
