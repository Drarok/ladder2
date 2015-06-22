<?php

namespace Ladder\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Pimple;

abstract class AbstractCommand extends Command
{
    protected $container;

    public function __construct(Pimple $container, $name = null)
    {
        parent::__construct($name);
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    public function __get($key)
    {
        return $this->container[$key];
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('show-sql')) {
            $this->container['output'] = $output;
            $this->db->setOutputQueries($output);
        }
    }
}
