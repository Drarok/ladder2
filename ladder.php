#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new Symfony\Component\Console\Application('Ladder2', Ladder\Version::getVersion());

$app->addCommands([
    new Ladder\Command\LadderCommand(),
]);

$app->run();
