<?php

namespace Zerifas\LadderTests;

use PHPUnit\Framework\TestCase;

use Zerifas\Ladder\Path;

class BootstrapTest extends TestCase
{
    static $container = null;

    public static function setUpBeforeClass()
    {
        static::$container = require_once(Path::join(__DIR__, '..', 'bootstrap.php'));
    }

    public function testConfigValidator()
    {
        $validator = static::$container['configValidator'];
        $this->assertInstanceOf(\Zerifas\JSON\Validator::class, $validator);
    }
}
