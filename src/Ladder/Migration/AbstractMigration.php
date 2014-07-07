<?php

namespace Ladder\Migration;

use Pimple;

abstract class AbstractMigration
{
    /**
     * Dependency container;
     *
     * @var Pimple
     */
    protected $container;

    /**
     * Cached appliedAt value.
     *
     * @var mixed
     */
    protected $appliedAt;

    abstract public function getName();

    abstract public function apply();

    abstract public function rollback(array $data = null);

    public function __construct(Pimple $container)
    {
        $this->container = $container;
    }

    public function getId()
    {
        $class = get_class($this);
        $migrationPos = strrpos($class, 'Migration');
        return substr($class, $migrationPos + 9);
    }

    public function __get($key)
    {
        return $this->container[$key];
    }

    public function getAppliedAt($flushCache = false)
    {
        if ($this->appliedAt === null || $flushCache) {
            if (! $this->container['migrationManager']->hasMigrationsTable()) {
                return $this->appliedAt = false;
            }

            $stmt = $this->db->prepare(
                'SELECT
                    appliedAt
                FROM
                    `ladder:migrations`
                WHERE
                    `id` = :id
                LIMIT
                    1'
            );
            $stmt->execute([
                'id' => $this->getId(),
            ]);
            $this->appliedAt = $stmt->fetchColumn();
        }

        return $this->appliedAt;
    }

    public function isApplied()
    {
        return ($this->getAppliedAt() !== false);
    }
}
