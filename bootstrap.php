<?php

namespace Ladder;

use Pimple;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

use Ladder\PDO\LoggingPDO;

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
        array(
            LoggingPDO::ATTR_DEFAULT_FETCH_MODE => LoggingPDO::FETCH_ASSOC,
            LoggingPDO::ATTR_EMULATE_PREPARES   => false,
            LoggingPDO::ATTR_ERRMODE            => LoggingPDO::ERRMODE_EXCEPTION,
            LoggingPDO::ATTR_STRINGIFY_FETCHES  => false,
            LoggingPDO::ATTR_STATEMENT_CLASS    => array('Ladder\\PDO\\PDOStatement', array($container)),
        )
    );
});

$container['migrationManager'] = $container->share(function ($container) {
    $manager = new MigrationManager($container);

    // Always add in the Ladder migrations path.
    $manager->addNamespace(
        'Ladder\\Migration\\System',
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

    $app->addCommands(array(
        new Command\CreateCommand($container),
        new Command\StatusCommand($container),
        new Command\MigrateCommand($container),
    ));

    return $app;
});

return $container;
