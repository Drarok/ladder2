<?php

namespace Zerifas\LadderTests\Database;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Database\Table;

/**
 * @requires PHP 5.6
 */
class TableTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();
    }

    protected function getMockDb()
    {
        $db = $this->getMockBuilder('PDO')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        Table::setDefaultDb($db);

        return $db;
    }

    protected function getMockTableForImport()
    {
        $table = $this->getMockBuilder('Zerifas\\Ladder\\Database\\Table')
            ->setConstructorArgs(['users'])
            ->setMethods(['insert'])
            ->getMock()
        ;

        $table
            ->expects($this->exactly(2))
            ->method('insert')
            ->withConsecutive(
                [
                    $this->equalTo([
                        'id'         => 1,
                        'name'       => 'Alice',
                        'occupation' => 'Developer',
                    ]),
                ],
                [
                    $this->equalTo([
                        'id'         => 2,
                        'name'       => 'Bob',
                        'occupation' => null,
                    ]),
                ]
            )
        ;

        return $table;
    }

    public function testImportInvalidFile()
    {
        $this->setExpectedException('InvalidArgumentException', 'Invalid file extension: invalid');
        Table::factory('users')
            ->import(__DIR__ . '/fixtures/TableTest_testImportNO_SUCH_FILE.invalid')
        ;
    }

    public function testImportCSV()
    {
        $this->getMockTableForImport()
            ->import(__DIR__ . '/fixtures/TableTest_testImportCSV.csv')
        ;
    }

    public function testImportPlainJSON()
    {
        $this->getMockTableForImport()
            ->import(__DIR__ . '/fixtures/TableTest_testImportPlainJSON.json')
        ;
    }

    public function testImportStructuredJSON()
    {
        $this->getMockTableForImport()
            ->import(__DIR__ . '/fixtures/TableTest_testImportStructuredJSON.json')
        ;
    }

    public function testCreate()
    {
        $sql = implode(PHP_EOL, [
            'CREATE TABLE `users` (',
            '    `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,',
            '    `groupId` INTEGER UNSIGNED,',
            '    `name` VARCHAR(30) NOT NULL,',
            '    `occupation` VARCHAR(30),',
            '    PRIMARY KEY (`id`),',
            '    KEY `name` (`name`),',
            '    CONSTRAINT `users:groupId::groups:id` FOREIGN KEY (`groupId`) REFERENCES `groups` (`id`)',
            ')',
        ]);

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('query')
            ->with($sql)
        ;

        Table::factory('users', $db)
            ->addColumn('id', 'autoincrement', ['unsigned' => true])
            ->addColumn('groupId', 'integer', ['unsigned' => true])
            ->addColumn('name', 'varchar', ['limit' => 30, 'null' => false])
            ->addColumn('occupation', 'varchar', ['limit' => 30])
            ->addIndex('PRIMARY', ['id'])
            ->addIndex('name')
            ->addConstraint(['groupId'], 'groups', ['id'])
            ->create()
        ;
    }

    public function testAlter()
    {
        $sql = implode(PHP_EOL, [
            'ALTER TABLE `users`',
            '    DROP COLUMN `name`,',
            '    MODIFY COLUMN `occupation` VARCHAR(30),',
            '    CHANGE COLUMN `created` `createdAt` DATETIME,',
            '    ADD COLUMN `age` INTEGER UNSIGNED,',
            '    DROP KEY `name`,',
            '    ADD KEY `age` (`age`),',
            '    DROP FOREIGN KEY `users:groupId::groups:id`,',
            '    DROP FOREIGN KEY `customFK`,',
            '    ADD CONSTRAINT `users:groupUUID::groups:uuid` FOREIGN KEY (`groupUUID`) REFERENCES `groups` (`uuid`)',
        ]);

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('query')
            ->with($sql)
        ;

        Table::factory('users', $db)
            ->dropColumn('name', 'varchar', ['limit' => 30, 'null' => false])
            ->alterColumn('occupation', 'varchar', ['limit' => 30])
            ->alterColumn('created', 'datetime', ['name' => 'createdAt'])
            ->dropIndex('name')
            ->addColumn('age', 'integer', ['unsigned' => true])
            ->addIndex('age')
            ->dropConstraint(['groupId'], 'groups', ['id'])
            ->dropConstraintByName('customFK')
            ->addConstraint(['groupUUID'], 'groups', ['uuid'])
            ->alter()
        ;
    }

    public function testDrop()
    {
        $sql = 'DROP TABLE `users`';

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('query')
            ->with($sql)
        ;

        Table::factory('users', $db)
            ->drop()
        ;
    }

    public function testInsertFailure()
    {
        $this->setExpectedException('Exception', 'Failed to insert?!');

        $stmt = $this->getMockBuilder('PDOStatement')
            ->getMock()
        ;

        $stmt
            ->expects($this->once())
            ->method('execute')
            ->willReturn(false)
        ;

        $db = $this->getMockDb();
        $db
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt)
        ;

        Table::factory('users', $db)
            ->insert([
                'id'   => 1,
                'name' => 'Alice',
            ])
        ;
    }

    public function testInsert()
    {
        $sql = 'INSERT INTO `users` (`id`, `name`) VALUES (:id, :name)';

        $stmt = $this->getMockBuilder('PDOStatement')
            ->getMock()
        ;
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->with([
                'id'   => 1,
                'name' => 'Alice',
            ])
            ->willReturn(true)
        ;

        $db = $this->getMockDb();

        $db->method('lastInsertId')
            ->willReturn(42)
        ;

        $db
            ->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt)
        ;

        $lastId = Table::factory('users', $db)
            ->insert([
                'id'   => 1,
                'name' => 'Alice',
            ])
            ->getLastInsertId()
        ;

        $this->assertSame(42, $lastId);
    }

    public function testUpdateFailure()
    {
        $this->setExpectedException('Exception', 'Failed to update?!');

        $stmt = $this->getMockBuilder('PDOStatement')
            ->getMock()
        ;
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->willReturn(false)
        ;

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt)
        ;

        Table::factory('users', $db)
            ->update(
                [
                    'id'   => 2,
                    'name' => 'Eva',
                ],
                [
                    'id'   => 1,
                ]
            )
        ;
    }

    public function testUpdate()
    {
        $sql = 'UPDATE `users` SET `id` = :data_id, `name` = :data_name WHERE `id` = :where_id';

        $stmt = $this->getMockBuilder('PDOStatement')
            ->getMock()
        ;
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->with([
                'where_id'  => 1,
                'data_id'   => 2,
                'data_name' => 'Eva',
            ])
            ->willReturn(true)
        ;

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt)
        ;

        Table::factory('users', $db)
            ->update(
                [
                    'id'   => 2,
                    'name' => 'Eva',
                ],
                [
                    'id'   => 1,
                ]
            )
        ;
    }

    public function testDeleteFailure()
    {
        $this->setExpectedException('Exception', 'Failed to delete?!');

        $stmt = $this->getMockBuilder('PDOStatement')
            ->getMock()
        ;
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->willReturn(false)
        ;

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt)
        ;

        Table::factory('users', $db)
            ->delete([
                'id' => 1,
            ])
        ;
    }

    public function testDelete()
    {
        $sql = 'DELETE FROM `users` WHERE `id` = :id';

        $stmt = $this->getMockBuilder('PDOStatement')
            ->getMock()
        ;
        $stmt
            ->expects($this->once())
            ->method('execute')
            ->with([
                'id' => 1,
            ])
            ->willReturn(true)
        ;

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt)
        ;

        Table::factory('users', $db)
            ->delete([
                'id' => 1,
            ])
        ;
    }
}
