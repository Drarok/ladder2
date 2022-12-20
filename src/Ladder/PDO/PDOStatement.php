<?php

namespace Zerifas\Ladder\PDO;

use PDOStatement as BasePDOStatement;

use Psr\Container\ContainerInterface;

class PDOStatement extends BasePDOStatement
{
    private function __construct(private ContainerInterface $container)
    {
    }

    public function execute(?array $params = null): bool
    {
        if ($this->container->has('output')) {
            $this->container->get('output')->writeln(sprintf(
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
