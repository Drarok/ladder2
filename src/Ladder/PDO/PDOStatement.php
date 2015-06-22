<?php

namespace Ladder\PDO;

use Pimple;

class PDOStatement extends \PDOStatement
{
    /**
     * Constructor is required, but must not be public.
     */
    private function __construct(Pimple $container)
    {
        $this->container = $container;
    }

    public function execute($params = null)
    {
        if ($this->container->offsetExists('output')) {
            $this->container['output']->writeln(sprintf(
                PHP_EOL . '<info>stmt: %s</info>',
                $this->queryString
            ));

            if ($params !== null) {
                $this->container['output']->writeln(sprintf(
                    '<comment>%s</comment>',
                    var_export($params, true)
                ));
            }
        }

        if ($params === null) {
            return parent::execute();
        } else {
            return parent::execute($params);
        }
    }
}