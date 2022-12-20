<?php

namespace Zerifas\Ladder;

use PDO;

use DirectoryIterator;
use Exception;
use InvalidArgumentException;

use Zerifas\Ladder\Migration\AbstractMigration;

class MigrationManager
{
    /**
     * Array of paths to get migrations from.
     *
     * @var array
     */
    protected $paths = [];

    public function __construct(protected readonly PDO $db)
    {
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
            throw new InvalidArgumentException(sprintf(
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
    public function getCurrentMigrationId()
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
     * Get all migrations, keyed and sorted on id.
     *
     * @return array
     */
    public function getAllMigrations()
    {
        $result = [];
        foreach ($this->findAllMigrations() as $migration) {
            $result[$migration->getId()] = $migration;
        }
        ksort($result);
        return $result;
    }

    /**
     * Get a migration by its id.
     *
     * @param int $id Migration id.
     *
     * @return AbstractMigration
     */
    public function getMigrationById($id)
    {
        $allMigrations = $this->getAllMigrations();

        if (! array_key_exists($id, $allMigrations)) {
            throw new InvalidArgumentException(sprintf(
                'No such migration: %d',
                $id
            ));
        }

        return $allMigrations[$id];
    }

    /**
     * Get the latest migration.
     *
     * @return AbstractMigration
     */
    public function getLatestMigration()
    {
        $migrations = $this->getAllMigrations();
        return array_pop($migrations);
    }

    /**
     * Get all migrations except those already applied.
     *
     * @return array
     */
    public function getAvailableMigrations()
    {
        $result = [];
        foreach ($this->findAllMigrations() as $migration) {
            if (! $migration->isApplied()) {
                $result[$migration->getId()] = $migration;
            }
        }
        ksort($result);
        return $result;
    }

    /**
     * Quick way to check if there are any migrations available to apply.
     *
     * @return bool
     */
    public function hasAvailableMigrations()
    {
        foreach ($this->findAllMigrations() as $migration) {
            if (! $migration->isApplied()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all the migrations that are applied to the database (in reverse order).
     *
     * @return array
     */
    public function getAppliedMigrations()
    {
        $result = [];
        foreach ($this->findAllMigrations() as $migration) {
            if ($migration->isApplied()) {
                $result[$migration->getId()] = $migration;
            }
        }
        krsort($result);
        return $result;
    }

    /**
     * Quick way to check if there are any migrations applied.
     *
     * @return bool
     */
    public function hasAppliedMigrations()
    {
        foreach ($this->findAllMigrations() as $migration) {
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
        } catch (Exception $e) {
            // TODO: Tidy up.
            throw $e;
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
        } catch (Exception $e) {
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

    /**
     * Find all migration files, regardless of whether or not they are applied.
     *
     * Note that these are *not* guaranteed to be in any order!
     *
     * @return array
     */
    protected function findAllMigrations()
    {
        // Grab all the appliedAt dates in one go for efficiency's sake.
        $appliedMigrations = [];

        if ($this->hasMigrationsTable()) {
            $stmt = $this->db->query(
                'SELECT
                    `id`,
                    `appliedAt`
                FROM
                    `ladder:migrations`
                ORDER BY
                    `id`'
            );

            while ($row = $stmt->fetch()) {
                $appliedMigrations[$row['id']] = $row['appliedAt'];
            }
        }

        $migrations = [];

        foreach ($this->paths as $namespace => $path) {
            if (! $path) {
                throw new InvalidArgumentException(sprintf(
                    'Missing migrations path for namespace \'%s\'.',
                    $namespace
                ));
            }

            // Convert relative paths into absolute.
            if ($path[0] != '/' && $path[0] != '\\') {
                $path = Path::join(getcwd(), $path);
            }

            if (! is_dir($path)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid migrations path for namespace \'%s\': \'%s\'',
                    $namespace,
                    $path
                ));
            }

            $dir = new DirectoryIterator($path);
            foreach ($dir as $fileInfo) {
                if ($dir->isDot()) {
                    continue;
                }

                if (! fnmatch('Migration*.php', $fileInfo->getFilename())) {
                    continue;
                }

                require_once $fileInfo->getPathname();

                $class = $namespace . '\\' . $fileInfo->getBasename('.php');
                $migration = new $class($this->db);

                if (array_key_exists($migration->getId(), $appliedMigrations)) {
                    $migration->setAppliedAt($appliedMigrations[$migration->getId()]);
                }

                $migrations[] = $migration;
            }
        }

        return $migrations;
    }
}
