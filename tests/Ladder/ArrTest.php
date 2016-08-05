<?php

namespace Zerifas\LadderTests;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Arr;

class ArrTest extends PHPUnit_Framework_TestCase
{
    private static $data = [
        'key1' => 'value1',
    ];

    public function testGetReturnsNullWhenNoSuchKey()
    {
        $this->assertNull(Arr::get(self::$data, 'no such key'));
    }

    public function testGetReturnsGivenDefaultWhenNoSuchKey()
    {
        $default = 'default value';
        $this->assertEquals($default, Arr::get(self::$data, 'no such key', $default));
    }

    public function testGetReturnsValueWhenKeyExists()
    {
        $this->assertEquals('value1', Arr::get(self::$data, 'key1'));
    }
}