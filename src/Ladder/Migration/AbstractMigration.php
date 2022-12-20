<?php

namespace Zerifas\Ladder\Migration;

use PDO;

use Zerifas\Ladder\Database\Table;

abstract class AbstractMigration
{
    /**
     * Cached appliedAt value.
     *
     * @var mixed
     */
    protected $appliedAt;

    abstract public function getName();

    abstract public function apply();

    abstract public function rollback(array $data = null);

    public function __construct(protected PDO $db)
    {
    }

    public function getId()
    {
        $class = get_class($this);
        $migrationPos = strrpos($class, 'Migration');
        return substr($class, $migrationPos + 9);
    }

    public function setAppliedAt($appliedAt)
    {
        $this->appliedAt = $appliedAt;
        return $this;
    }

    public function getAppliedAt()
    {
        return $this->appliedAt;
    }

    public function isApplied()
    {
        return (bool) $this->appliedAt;
    }

    protected function table(string $name): Table
    {
        return new Table($this->db, $name);
    }
}
