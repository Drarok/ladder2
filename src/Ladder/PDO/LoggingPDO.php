<?php

namespace Zerifas\Ladder\PDO;

use PDO;
use PDOStatement;

use Symfony\Component\Console\Output\OutputInterface;

class LoggingPDO extends PDO
{
    protected $outputQueries = false;

    public function setOutputQueries(OutputInterface $outputQueries)
    {
        $this->outputQueries = $outputQueries;
        return $this;
    }

    public function getOutputQueries()
    {
        return $this->outputQueries;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($this->outputQueries) {
            $this->outputQueries->writeln(PHP_EOL . '<info>query: ' . $query . '</info>');
        }

        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}
