<?php

namespace Ladder\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('status')
            ->setDescription('Show the current status of the database.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $verbose = $input->getOption('verbose');

        $output->writeln('<comment>Status</comment>');

        $manager = $this->migrationManager;

        $availableMigrations = $manager->getAvailableMigrations();

        if (! count($availableMigrations) && ! $verbose) {
            $output->writeln('    <fg=green>Database is up-to-date.</fg=green>');
            return;
        }

        if ($verbose) {
            $allMigrations = $manager->getAllMigrations();

            foreach ($allMigrations as $id => $migration) {
                $missing = array_key_exists($id, $availableMigrations);

                $output->writeln(sprintf(
                    '    %2$d: <fg=%1$s>%3$s</fg=%1$s>',
                    $missing ? 'red' : 'green',
                    $id,
                    $migration->getName()
                ));
            }
        } else {
            foreach ($availableMigrations as $id => $migration) {
                $output->writeln(sprintf(
                    '    %d: <fg=red>%s</fg=red>',
                    $id,
                    $migration->getName()
                ));
            }
        }
    }
}
