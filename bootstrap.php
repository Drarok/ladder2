<?php

use Pimple\Container;

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

$container = new Container();

$container['rootPath'] = __DIR__;

$container['configValidator'] = function ($container) {
    $schema = new JSON\Obj([
        'db'         => new JSON\Obj([
            // Deprecated options.
            'dsn'      => new JSON\OptionalStr(),
            'hostname' => new JSON\OptionalStr(),

            // Current options - optional as we transition to them.
            'host'     => new JSON\OptionalStr(),
            'dbname'   => new JSON\OptionalStr(),
            'charset'  => new JSON\OptionalStr('utf8'),
            'username' => new JSON\Str(),
            'password' => new JSON\Str(),
        ]),
        'migrations' => new JSON\Arr(new JSON\Obj([
            'namespace' => new JSON\Str(),
            'path'      => new JSON\Str(),
        ])),
    ]);

    return new JSON\Validator($schema);
};

$container['config'] = function ($container) {
    $configPathname = $container['configPathname'];

    if (! is_file($configPathname)) {
        throw new Exception('No such file: ' . $configPathname);
    }

    $validator = $container['configValidator'];
    if (! $validator->isValid(file_get_contents($configPathname))) {
        throw new Exception('Invalid config: ' . implode(', ', $validator->getErrors()));
    }

    $config = $validator->getDocument();

    // Handle deprecated options, and put warnings into an array which will be displayed
    // to users using the event dispatcher (else we can't access the OutputInterface).
    $warnings = [];

    if ($config->db->dsn !== null) {
        $warnings[] = 'Warning: db.dsn config option is deprecated.';

        // Define defaults.
        $defaults = [
            'host'    => '',
            'dbname'  => '',
            'charset' => 'utf8',
        ];

        // Decompose the dsn option into its component parts.
        if (! preg_match_all('/(\w+)=(.*?)(?:;|$)/', $config->db->dsn, $matches, PREG_SET_ORDER)) {
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

        // Remove deprecated option, replace db config.
        unset($options['dsn']);
        $config->db = (object) $options;
    }

    if ($config->db->hostname !== null) {
        $warnings[] = 'Warning: db.hostname config option is deprecated.';
        if ($config->db->host === null) {
            $config->db->host = $config->db->hostname;
            unset($config->db->hostname);
        }
    }

    $container['warnings'] = $warnings;

    return $config;
};

$container['db'] = function ($container) {
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
};

$container['migrationManager'] = function ($container) {
    $manager = new MigrationManager($container);

    // Always add in the Ladder migrations path.
    $manager->addNamespace(
        'Zerifas\\Ladder\\Migration\\System',
        Path::join($container['rootPath'], 'src/Ladder/Migration/System')
    );

    // Loop over configured namespaces and add those.
    foreach ($container['config']->migrations as $migrations) {
        $manager->addNamespace($migrations->namespace, $migrations->path);
    }

    return $manager;
};

$container['dispatcher'] = function ($container) {
    $dispatcher = new EventDispatcher();

    $dispatcher->addListener(ConsoleEvents::COMMAND, function (ConsoleCommandEvent $event) use ($container) {
        // Make sure config is loaded.
        $config = $container['config'];

        $output = $event->getOutput();

        if (($warnings = $container['warnings'])) {
            foreach ($warnings as $warning) {
                $output->writeln(sprintf('<comment>%s</comment>', $warning));
            }
        }
    });

    return $dispatcher;
};

$container['app'] = function ($container) {
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
};

return $container;
