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
