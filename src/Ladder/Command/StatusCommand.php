<?php

namespace Zerifas\Ladder\Command;

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
        parent::execute($input, $output);

        $verbose = $input->getOption('verbose');

        $output->writeln('<comment>Status</comment>');

        $manager = $this->migrationManager;

        if (! $verbose && ! $manager->hasAvailableMigrations()) {
            $output->writeln('    <fg=green>Database is up-to-date.</fg=green>');
            return;
        }

        if ($verbose) {
            $allMigrations = $manager->getAllMigrations();

            foreach ($allMigrations as $id => $migration) {
                $output->writeln(sprintf(
                    '    %2$d: <fg=%1$s>%3$s %4$s</fg=%1$s>',
                    $migration->isApplied() ? 'green' : 'red',
                    $migration->getId(),
                    $migration->getName(),
                    $migration->isApplied() ? '✔' : '✘'
                ));
            }
        } else {
            $output->writeln('    <comment>Missing Migrations</comment>');
            foreach ($manager->getAvailableMigrations() as $migration) {
                $output->writeln(sprintf(
                    '        %d: <fg=red>%s</fg=red>',
                    $migration->getId(),
                    $migration->getName()
                ));
            }
        }
    }
}
