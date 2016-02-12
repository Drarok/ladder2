<?php

namespace Zerifas\Ladder\Command;

use Zerifas\Ladder\Path;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateCommand extends AbstractCommand
{
    protected function configure()
    {
        $this->setName('create')
            ->setDescription('Create a new Migration file.')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Migration name'
            )
            ->addArgument(
                'namespace',
                InputArgument::OPTIONAL,
                'Namespace to create the migration in (only required when you have more than one).'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $config = $this->config;

        if (count($config['migrations']) > 1) {
            $namespace = $input->getArgument('namespace');

            if (! $namespace) {
                throw new \InvalidArgumentException(
                    'You must specify the namespace when you have multiple configured.'
                );
            }

            $path = false;
            foreach ($config['migrations'] as $migrationsConfig) {
                if ($migrationsConfig['namespace'] == $namespace) {
                    $path = $migrationsConfig['path'];
                    break;
                }
            }

            if ($path === false) {
                throw new \InvalidArgumentException(sprintf(
                    'Failed to find namespace \'%s\' in config.',
                    $namespace
                ));
            }
        } else {
            $migrationsConfig = $config['migrations'][0];
            $namespace = $migrationsConfig['namespace'];
            $path = $migrationsConfig['path'];
        }

        $pathname = $this->createTemplateFile($input->getArgument('name'), $namespace, $path);

        $output->writeln(sprintf(
            '<info>%s</info>',
            $pathname
        ));
    }

    protected function createTemplateFile($name, $namespace, $path)
    {
        if (! is_dir($path) || ! is_writable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Path does not exist or is not writable: \'%s\'.',
                $path
            ));
        }

        $id = time();

        $template = str_replace(
            [
                '{{ namespace }}',
                '{{ id }}',
                '{{ name }}',
            ],
            [
                $namespace,
                $id,
                $name,
            ],
            file_get_contents(Path::join($this->rootPath, 'views', 'migration.template'))
        );

        $pathname = Path::join($path, 'Migration' . $id . '.php');
        file_put_contents($pathname, $template);

        return $pathname;
    }
}
