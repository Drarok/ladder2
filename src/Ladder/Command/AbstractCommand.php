<?php

namespace Zerifas\Ladder\Command;

use Psr\Container\ContainerInterface;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractCommand extends Command
{
    public function __construct(protected ContainerInterface $container, $name = null)
    {
        parent::__construct($name);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('show-sql')) {
            $this->container->set('output', $output);
            $this->container->get('db')->setOutputQueries($output);
        }
    }
}
