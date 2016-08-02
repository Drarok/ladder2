<?php

namespace Zerifas\LadderTests;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Version;

class VersionTest extends PHPUnit_Framework_TestCase
{
    public function testGetVersion()
    {
        $this->assertRegExp('/^\d+\.\d+\.\d+-?(alpha|beta|RC-\d+)?$/', Version::getVersion());
    }
}
