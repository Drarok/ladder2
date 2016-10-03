<?php

namespace Zerifas\LadderTests\Database;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Database\Column;

class ColumnTest extends PHPUnit_Framework_TestCase
{
    public function testAutoIncrement()
    {
        $column = new Column('id', 'autoincrement', ['unsigned' => true]);

        $expected = '`id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT';
        $this->assertEquals($expected, $column->getCreateSQL());
    }

    public function testUnsigned()
    {
        $column = new Column('userId', 'integer', ['unsigned' => true]);

        $expected = '`userId` INTEGER UNSIGNED';
        $this->assertEquals($expected, $column->getCreateSQL());
    }

    public function testLimit()
    {
        $column = new Column('username', 'varchar', ['limit' => 30, 'null' => false]);

        $expected = '`username` VARCHAR(30) NOT NULL';
        $this->assertEquals($expected, $column->getCreateSQL());
    }

    public function testOptions()
    {
        $column = new Column('usertype', 'enum', ['options' => ['standard', 'admin', 'quote\'string']]);

        $expected = '`usertype` ENUM(\'standard\', \'admin\', \'quote\\\'string\')';
        $this->assertEquals($expected, $column->getCreateSQL());
    }

    public function testDefault()
    {
        $column = new Column('usertype', 'varchar', ['limit' => 30, 'default' => 'quote\'string']);

        $expected = '`usertype` VARCHAR(30) DEFAULT \'quote\\\'string\'';
        $this->assertEquals($expected, $column->getCreateSQL());
    }

    public function testFalsyDefault()
    {
        $column = new Column('admin', 'tinyint', ['null' => false, 'unsigned' => true, 'default' => 0]);

        $expected = '`admin` TINYINT UNSIGNED NOT NULL DEFAULT 0';
        $this->assertEquals($expected, $column->getCreateSQL());
    }

    public function testAfter()
    {
        $column = new Column('notes', 'text', ['after' => 'usertype']);

        $expected = '`notes` TEXT AFTER `usertype`';
        $this->assertEquals($expected, $column->getCreateSQL());
    }

    public function testAlter()
    {
        $column = new Column('notes', 'varchar', ['limit' => 30]);

        $expected = 'MODIFY COLUMN `notes` VARCHAR(30)';
        $this->assertEquals($expected, $column->getAlterSQL());
    }

    public function testAlterRename()
    {
        $column = new Column('notes', 'varchar', ['limit' => 30, 'name' => 'shortnotes']);

        $expected = 'CHANGE COLUMN `notes` `shortnotes` VARCHAR(30)';
        $this->assertEquals($expected, $column->getAlterSQL());
    }

    public function testDrop()
    {
        // public function __construct($name, $type, array $options = [])
        $column = new Column('notes', '');

        $expected = 'DROP COLUMN `notes`';
        $this->assertEquals($expected, $column->getDropSQL());
    }
}
