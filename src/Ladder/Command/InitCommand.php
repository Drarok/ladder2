<?php

namespace Zerifas\Ladder\Command;

use PDO;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('init')
            ->setDescription('Attempt to create the configured database.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $verbose = $input->getOption('verbose');

        $config = $this->container['config']['db'];
        $dsn = sprintf(
            'mysql:host=%s;charset=%s;',
            $config['hostname'],
            $config['charset']
        );

        $db = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );

        $stmt = $db->query(sprintf(
            'SHOW DATABASES LIKE \'%s\'',
            $config['dbname']
        ));

        if (! $stmt->fetch()) {
            $db->exec(sprintf('CREATE DATABASE `%s`', $config['dbname']));
        }
    }
}
