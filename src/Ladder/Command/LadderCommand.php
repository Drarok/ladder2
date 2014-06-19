<?php

namespace Ladder\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LadderCommand extends Command
{
    protected function configure()
    {
        $this->setName('migrate')
            ->setDescription('Migrate to the latest, or specified migration number.')
            ->addArgument(
                'migration',
                InputArgument::OPTIONAL,
                'Optional migration id to migrate to.',
                'latest'
            )
            ->addOption(
                'rollback',
                'r',
                InputOption::VALUE_NONE,
                'Allow downwards migrations.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $manager = $this->migrationManager;

        $source = $manager->getCurrentMigration();
        $destination = $input->getArgument('migration');

        $output->writeln(sprintf(
            '<info>Migrate from <comment>%s</comment> to <comment>%s</comment></info>',
            var_export($source, true),
            $destination
        ));

        $migrations = $manager->getAvailableMigrations();
        foreach ($migrations as $id => $pathname) {
            $manager->applyMigration($id);
        }
    }
}
