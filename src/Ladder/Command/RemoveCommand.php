<?php

namespace Zerifas\Ladder\Command;

use InvalidArgumentException;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Zerifas\Ladder\Database\Table;
use Zerifas\Ladder\Migration\System\AbstractSystemMigration;

class RemoveCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('remove')
            ->setDescription('Rollback a single migration.')
            ->addArgument(
                'migration',
                InputArgument::REQUIRED,
                'The migration id to rollback.'
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

        $migration = $manager->getMigrationById($migrationId);

        if ($migration instanceof AbstractSystemMigration) {
            throw new InvalidArgumentException('You cannot remove a Ladder system migration');
        }

        if (! $migration->isApplied()) {
            throw new InvalidArgumentException('You cannot remote a migration that is not applied.');
        }

        $output->write(sprintf(
            '<info>Rolling back %d - <comment>%s</comment>: </info>',
            $migration->getId(),
            $migration->getName()
        ));
        $manager->rollbackMigration($migration);
        $output->writeln('<info>OK</info>');
    }
}
