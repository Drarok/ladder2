<?php

namespace Zerifas\LadderTests;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Path;

class PathTest extends PHPUnit_Framework_TestCase
{
    public function testRelative()
    {
        $this->assertEquals('tmp/ladder/filename.json', Path::join('tmp/', 'ladder/', '/filename.json'));
    }

    public function testAbsolute()
    {
        $this->assertEquals('/tmp/ladder/filename.json', Path::join('/tmp', 'ladder/', '/filename.json'));
    }
}
