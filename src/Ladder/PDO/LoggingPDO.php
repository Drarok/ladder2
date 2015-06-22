<?php

namespace Ladder\PDO;

use PDO;

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

    public function query($sql)
    {
        if ($this->outputQueries) {
            $this->outputQueries->writeln('query: ' . $sql);
        }
        return parent::query($sql);
    }

    public function prepare($sql)
    {
        if ($this->outputQueries) {
            $this->outputQueries->writeln('prepare: ' . $sql);
        }
        return parent::prepare($sql);
    }
}
