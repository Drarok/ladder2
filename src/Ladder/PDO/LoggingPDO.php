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
            $this->outputQueries->writeln(PHP_EOL . '<info>query: ' . $sql . '</info>');
        }
        return parent::query($sql);
    }
}
