<?php

namespace Ladder;

use PDO;
use Pimple;
use Symfony\Component\Console\Application;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    // Composer autoload for developing Ladder.
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/../../autoload.php')) {
    // Composer autoload for users of Ladder.
    require_once __DIR__ . '/../../autoload.php';
}

$container = new Pimple();

$container['rootPath'] = __DIR__;

$container['db'] = function ($container) {
    $config = $container['config']['db'];

    return new PDO(
        $config['dsn'],
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]
    );
};

$container['migrationManager'] = function ($container) {
    $manager = new MigrationManager($container);

    // Always add in the Ladder migrations path.
    $manager->addNamespace(
        'Ladder\\Migration',
        Path::join($container['rootPath'], 'src/Ladder/Migration')
    );

    // Loop over configured namespaces and add those.
    foreach ($container['config']['migrations'] as $migrations) {
        $manager->addNamespace($migrations['namespace'], $migrations['path']);
    }

    return $manager;
};

$container['app'] = function ($container) {
    $app = new Application('Ladder2', Version::getVersion());

    $app->addCommands([
        new Command\CreateCommand($container),
        new Command\StatusCommand($container),
        new Command\MigrateCommand($container),
    ]);

    return $app;
};

return $container;
