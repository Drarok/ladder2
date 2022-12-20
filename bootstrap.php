<?php

use Psr\Container\ContainerInterface;

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

$container = new \DI\Container();

$container->set('rootPath', __DIR__);

$container->set('configValidator', function (ContainerInterface $container) {
    $schema = new JSON\Obj([
        'db' => new JSON\Obj([
            'host'     => new JSON\Str(),
            'dbname'   => new JSON\Str(),
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
});

$container->set('config', function (ContainerInterface $container) {
    $configPathname = $container->get('configPathname');

    if (! is_file($configPathname)) {
        throw new Exception('No such file: ' . $configPathname);
    }

    $validator = $container->get('configValidator');
    if (! $validator->isValid(file_get_contents($configPathname))) {
        throw new Exception('Invalid config: ' . implode(', ', $validator->getErrors()));
    }

    return $validator->getDocument();
});

$container->set(PDO::class, function (ContainerInterface $container) {
    $config = $container->get('config')->db;

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

$container->set(MigrationManager::class, function (ContainerInterface $container) {
    $manager = new MigrationManager($container->get(PDO::class));

    // Always add in the Ladder migrations path.
    $manager->addNamespace(
        'Zerifas\\Ladder\\Migration\\System',
        Path::join($container->get('rootPath'), 'src/Ladder/Migration/System')
    );

    // Loop over configured namespaces and add those.
    foreach ($container->get('config')->migrations as $migrations) {
        $manager->addNamespace($migrations->namespace, $migrations->path);
    }

    return $manager;
});

$container->set(Application::class, function (ContainerInterface $container) {
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
        new InitCommand($container),
        new MigrateCommand($container),
        new ReapplyCommand($container),
        new RemoveCommand($container),
        new StatusCommand($container),
    ]);

    return $app;
});

return $container;
