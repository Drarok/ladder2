<?php

namespace Zerifas\LadderTests\Database;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Database\Constraint;

class ConstraintTest extends PHPUnit_Framework_TestCase
{
    public function testConstraint()
    {
        $constraint = new Constraint('users::groups', ['groupId'], 'groups', ['id']);

        $expected = 'CONSTRAINT `users::groups` FOREIGN KEY (`groupId`) REFERENCES `groups` (`id`)';
        $this->assertEquals($expected, $constraint->getCreateSQL());
    }

    public function testOptions()
    {
        $options = [
            'delete' => 'RESTRICT',
            'update' => 'CASCADE',
        ];
        $constraint = new Constraint('users::groups', ['groupId'], 'groups', ['id'], $options);

        $expected = 'CONSTRAINT `users::groups` FOREIGN KEY (`groupId`) REFERENCES `groups` (`id`) ' .
            'ON DELETE RESTRICT ON UPDATE CASCADE';
        $this->assertEquals($expected, $constraint->getCreateSQL());
        $this->assertEquals('ADD ' . $expected, $constraint->getAddSQL());
    }

    public function testDrop()
    {
        $constraint = new Constraint('users::groups', [], '', []);

        $expected = 'DROP FOREIGN KEY `users::groups`';
        $this->assertEquals($expected, $constraint->getDropSQL());
    }
}
