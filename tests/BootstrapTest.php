<?php

namespace Zerifas\LadderTests;

use PHPUnit\Framework\TestCase;

use Zerifas\Ladder\Path;

class BootstrapTest extends TestCase
{
    private static $container = null;

    public static function setUpBeforeClass(): void
    {
        static::$container = require_once(Path::join(__DIR__, '..', 'bootstrap.php'));
    }

    public function testConfigValidator()
    {
        $validator = static::$container->get('configValidator');
        $this->assertInstanceOf(\Zerifas\JSON\Validator::class, $validator);
    }
}
