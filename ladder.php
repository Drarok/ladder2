#!/usr/bin/env php
<?php

$container = require_once __DIR__ . '/bootstrap.php';

$configPathname = Ladder\Path::join(getcwd(), 'ladder.json');

if (! is_file($configPathname)) {
    echo 'No such file: ' . $configPathname, PHP_EOL;
    exit(1);
}

$container['config'] = json_decode(file_get_contents($configPathname), true);

exit($container['app']->run());
