#!/usr/bin/env php
<?php

$container = require_once __DIR__ . '/bootstrap.php';

$container['app']->run();
