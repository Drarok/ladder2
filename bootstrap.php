<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;

use Zerifas\JSON;
use Zerifas\Ladder\Command\CreateCommand;
use Zerifas\Ladder\Command\InitCommand;
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

$container['configValidator'] = $container->share(function ($container) {
    $schema = new JSON\Object([
        'db'         => new JSON\Object([
            // Deprecated options.
            'dsn'      => new JSON\OptionalString(),
            'hostname' => new JSON\OptionalString(),

            // Current options - optional as we transition to them.
            'host'     => new JSON\OptionalString(),
            'dbname'   => new JSON\OptionalString(),
            'charset'  => new JSON\OptionalString('utf8'),
            'username' => new JSON\String(),
            'password' => new JSON\String(),
        ]),
        'migrations' => new JSON\Arr(new JSON\Object([
            'namespace' => new JSON\String(),
            'path'      => new JSON\String(),
        ])),
    ]);

    return new JSON\Validator($schema);
});

$container['config'] = $container->share(function ($container) {
    $configPathname = $container['configPathname'];

    if (! is_file($configPathname)) {
        throw new Exception('No such file: ' . $configPathname);
    }

    $validator = $container['configValidator'];
    if (! $validator->isValid(file_get_contents($configPathname))) {
        throw new Exception('Invalid config: ' . implode(', ', $validator->getErrors()));
    }

    return $validator->getDocument();
});

$container['db'] = $container->share(function ($container) {
    $config = $container['config']->db;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s;',
        $config->host,
        $config->dbname,
        $config->charset
    );

    return new LoggingPDO(
        $dsn,
        $config->username,
        $config->password,
        [
            LoggingPDO::ATTR_ERRMODE            => LoggingPDO::ERRMODE_EXCEPTION,
            LoggingPDO::ATTR_DEFAULT_FETCH_MODE => LoggingPDO::FETCH_ASSOC,
            LoggingPDO::ATTR_EMULATE_PREPARES   => false,
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

$container['dispatcher'] = $container->share(function ($container) {
    $dispatcher = new EventDispatcher();

    $dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) use ($container) {
        $output = $event->getOutput();

        $config = $container['config'];
        if ($config->db->dsn !== null) {
            $output->writeln('<comment>Warning: the \'dsn\' config option is deprecated</comment>');

            // Define defaults.
            $defaults = [
                'host'    => '',
                'dbname'  => '',
                'charset' => 'utf8',
            ];

            // Decompose the dsn option into its component parts.
            if (! preg_match_all('/(\w+)=(.*?)(?:;|$)/', $config['db']['dsn'], $matches, PREG_SET_ORDER)) {
                throw new InvalidArgumentException('Failed to parse deprecated \'dsn\' config option');
            }

            // Set defaults from the parsed data.
            foreach ($matches as $match) {
                $key = $match[1];
                $value = $match[2];

                $defaults[$key] = $value;
            }

            // Merge the config in to allow users to "win" over our parsing.
            $options = array_merge($defaults, (array) $config->db);

            // Remove deprecated option, replace config.
            unset($options['dsn']);
            $config->db = (object) $options;

            // Assign the now-updated config back to the container.
            $container['config'] = $config;
        }

    });

    return $dispatcher;
});

$container['app'] = $container->share(function ($container) {
    $app = new Application('Ladder', Version::getVersion());

    $app->setDispatcher($container['dispatcher']);

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
        new InitCommand($container),
        new MigrateCommand($container),
        new ReapplyCommand($container),
        new RemoveCommand($container),
        new StatusCommand($container),
    ]);

    return $app;
});

return $container;
