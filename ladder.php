#!/usr/bin/env php
<?php

$container = require_once __DIR__ . '/bootstrap.php';

$configPathname = Ladder\Path::join(getcwd(), 'ladder.json');

if (is_file($configPathname)) {
    $container['config'] = json_decode(file_get_contents($configPathname), true);
}

$container['app']->run();
