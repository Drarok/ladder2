<?php

namespace Zerifas\LadderTests\Database;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Database\Index;

class IndexTest extends PHPUnit_Framework_TestCase
{
    public function testPrimary()
    {
        $index = new Index('PRIMARY', ['id']);

        $expected = 'PRIMARY KEY (`id`)';
        $this->assertEquals($expected, $index->getCreateSQL());
    }

    public function testUnique()
    {
        $index = new Index('id', ['id'], ['unique' => true]);

        $expected = 'UNIQUE KEY `id` (`id`)';
        $this->assertEquals($expected, $index->getCreateSQL());
    }

    public function testIndex()
    {
        $index = new Index('id', ['id']);

        $expected = 'KEY `id` (`id`)';
        $this->assertEquals($expected, $index->getCreateSQL());
    }

    public function testDropPrimary()
    {
        $index = new Index('PRIMARY', []);

        $expected = 'DROP PRIMARY KEY';
        $this->assertEquals($expected, $index->getDropSQL());
    }

    public function testDropIndex()
    {
        $index = new Index('id', []);

        $expected = 'DROP KEY `id`';
        $this->assertEquals($expected, $index->getDropSQL());
    }
}
