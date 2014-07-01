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
}
