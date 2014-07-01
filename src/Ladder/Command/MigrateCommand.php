<?php

namespace Ladder\Command;

use Ladder\MigrationManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends AbstractCommand
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

        if ($destination < $source) {
            if (! $input->getOption('rollback')) {
                throw new \InvalidArgumentException(sprintf(
                    'Refusing to roll back from %d to %d without --rollback option for safety.',
                    $source,
                    $destination
                ));
            }

            $method = 'rollback';
        } else {
            $method = 'apply';
        }

        $this->$method($output, $manager, $source, $destination);
    }

    protected function apply(OutputInterface $output, MigrationManager $manager, $source, $destination)
    {

        $availableMigrations = $manager->getAvailableMigrations();

        if (! count($availableMigrations)) {
            $output->writeln('<info>Already up-to-date.</info>');
            return;
        }

        $output->writeln(sprintf(
            '<info>Migrate from <comment>%s</comment> to <comment>%s</comment></info>',
            var_export($source, true),
            $destination
        ));

        foreach ($availableMigrations as $id => $class) {
            $instance = $manager->createInstance($id);

            $output->write(sprintf(
                '<info>Applying %d: <comment>%s</comment>: </info>',
                $id,
                $instance->getName()
            ));

            try {
                $manager->applyMigration($instance);
                $output->writeln('<info>OK</info>');
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    '<error>Error: %s. Aborted.</error>',
                    $e->getMessage()
                ));
                break;
            }
        }
    }

    protected function rollback(OutputInterface $output, MigrationManager $manager, $source, $destination)
    {
        $appliedMigrations = $manager->getAppliedMigrations();
        krsort($appliedMigrations);

        if (! count($appliedMigrations)) {
            $output->writeln('<info>Already up-to-date.</info>');
            return;
        }

        $output->writeln(sprintf(
            '<info>Rollback from <comment>%s</comment> to <comment>%s</comment></info>',
            var_export($source, true),
            $destination
        ));

        foreach ($appliedMigrations as $id => $class) {
            $instance = $manager->createInstance($id);

            $output->write(sprintf(
                '<info>Rolling back %d: <comment>%s</comment>: </info>',
                $id,
                $instance->getName()
            ));

            try {
                $manager->rollbackMigration($instance);
                $output->writeln('<info>OK</info>');
            } catch (\Exception $e) {
                $output->writeln(sprintf(
                    '<error>Error: %s. Aborted.</error>',
                    $e->getMessage()
                ));
                break;
            }
        }
    }
}
