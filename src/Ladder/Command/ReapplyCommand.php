<?php

namespace Zerifas\Ladder\Command;

use InvalidArgumentException;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Zerifas\Ladder\Database\Table;
use Zerifas\Ladder\Migration\System\AbstractSystemMigration;

class ReapplyCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('reapply')
            ->setDescription('Rollback the given migration (if applied), then apply it.')
            ->addArgument(
                'migration',
                InputArgument::OPTIONAL,
                'Optional migration id to reapply.',
                'latest'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        Table::setDefaultDb($this->db);

        $manager = $this->migrationManager;

        $source = $manager->getCurrentMigrationId();
        $migrationId = $input->getArgument('migration');

        if ($migrationId === 'latest') {
            $migration = $manager->getLatestMigration();
        } else {
            $migration = $manager->getMigrationById($migrationId);
        }

        if ($migration instanceof AbstractSystemMigration) {
            throw new InvalidArgumentException('You cannot reapply a Ladder system migration');
        }

        if ($migration->isApplied()) {
            $output->write(sprintf(
                '<info>Rolling back %d - <comment>%s</comment>: </info>',
                $migration->getId(),
                $migration->getName()
            ));
            $manager->rollbackMigration($migration);
            $output->writeln('<info>OK</info>');
        }

        $output->write(sprintf(
            '<info>Applying %d - <comment>%s</comment>: </info>',
            $migration->getId(),
            $migration->getName()
        ));
        $manager->applyMigration($migration);
        $output->writeln('<info>OK</info>');
    }
}
