<?php

namespace Zerifas\LadderTests;

use PHPUnit\Framework\TestCase;

use Zerifas\Ladder\Version;

class VersionTest extends TestCase
{
    public function testGetVersion()
    {
        $this->assertRegExp('/^\d+\.\d+\.\d+-?(alpha|beta|RC-\d+)?$/', Version::getVersion());
    }
}
