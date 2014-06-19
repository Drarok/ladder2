<?php

namespace Ladder;

use PDO;
use Pimple;
use Symfony\Component\Console\Application;

require_once __DIR__ . '/vendor/autoload.php';

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
    return new Ladder\MigrationManager($container);
};

$container['app'] = function ($container) {
    $app = new Symfony\Component\Console\Application('Ladder2', Ladder\Version::getVersion());

    $app->addCommands([
        new Ladder\Command\LadderCommand($container),
    ]);

    return $app;
};

return $container;
