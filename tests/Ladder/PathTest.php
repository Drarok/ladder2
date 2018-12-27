<?php

namespace Zerifas\LadderTests;

use PHPUnit\Framework\TestCase;

use Zerifas\Ladder\Path;

class PathTest extends TestCase
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
