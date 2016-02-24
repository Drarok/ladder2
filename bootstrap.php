<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

use Zerifas\Ladder\Command\CreateCommand;
use Zerifas\Ladder\Command\MigrateCommand;
use Zerifas\Ladder\Command\ReapplyCommand;
use Zerifas\Ladder\Command\RemoveCommand;
use Zerifas\Ladder\Command\StatusCommand;
use Zerifas\Ladder\MigrationManager;
use Zerifas\Ladder\Path;
use Zerifas\Ladder\PDO\LoggingPDO;
use Zerifas\Ladder\Version;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Composer autoload for developing Ladder.
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../autoload.php')) {
    // Composer autoload for users of Ladder.
    require_once __DIR__ . '/../../autoload.php';
}

$container = new Pimple();

$container['rootPath'] = __DIR__;

$container['db'] = $container->share(function ($container) {
    $config = $container['config']['db'];

    return new LoggingPDO(
        $config['dsn'],
        $config['username'],
        $config['password'],
        [
            LoggingPDO::ATTR_DEFAULT_FETCH_MODE => LoggingPDO::FETCH_ASSOC,
            LoggingPDO::ATTR_EMULATE_PREPARES   => false,
            LoggingPDO::ATTR_ERRMODE            => LoggingPDO::ERRMODE_EXCEPTION,
            LoggingPDO::ATTR_STRINGIFY_FETCHES  => false,
            LoggingPDO::ATTR_STATEMENT_CLASS    => ['Zerifas\\Ladder\\PDO\\PDOStatement', [$container]],
        ]
    );
});

$container['migrationManager'] = $container->share(function ($container) {
    $manager = new MigrationManager($container);

    // Always add in the Ladder migrations path.
    $manager->addNamespace(
        'Zerifas\\Ladder\\Migration\\System',
        Path::join($container['rootPath'], 'src/Ladder/Migration/System')
    );

    // Loop over configured namespaces and add those.
    foreach ($container['config']['migrations'] as $migrations) {
        $manager->addNamespace($migrations['namespace'], $migrations['path']);
    }

    return $manager;
});

$container['app'] = $container->share(function ($container) {
    $app = new Application('Ladder', Version::getVersion());

    // Add global option for showing SQL.
    $input = $app->getDefinition();
    $input->addOption(new InputOption(
        'show-sql',
        's',
        InputOption::VALUE_NONE,
        'Output SQL statements before running them.'
    ));

    $app->addCommands([
        new CreateCommand($container),
        new MigrateCommand($container),
        new ReapplyCommand($container),
        new RemoveCommand($container),
        new StatusCommand($container),
    ]);

    return $app;
});

return $container;
