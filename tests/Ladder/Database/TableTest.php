<?php

namespace Zerifas\LadderTests\Database;

use PDO;

use PHPUnit\Framework\TestCase;

use Zerifas\Ladder\Database\Table;

class TableTest extends TestCase
{
    public function testImportInvalidFile()
    {
        $this->expectException('InvalidArgumentException', 'Invalid file extension: invalid');
        $this->table('users')
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

        $this->table('users', $db)
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
            '    ADD CONSTRAINT `users::groups` FOREIGN KEY (`groupUUID`) REFERENCES `groups` (`uuid`)',
        ]);

        $db = $this->getMockDb();

        $db
            ->expects($this->once())
            ->method('query')
            ->with($sql)
        ;

        $this->table('users', $db)
            ->dropColumn('name', 'varchar', ['limit' => 30, 'null' => false])
            ->alterColumn('occupation', 'varchar', ['limit' => 30])
            ->alterColumn('created', 'datetime', ['name' => 'createdAt'])
            ->dropIndex('name')
            ->addColumn('age', 'integer', ['unsigned' => true])
            ->addIndex('age')
            ->dropConstraint(['groupId'], 'groups', ['id'])
            ->dropConstraintByName('customFK')
            ->addConstraint(['groupUUID'], 'groups', ['uuid'], ['name' => 'users::groups'])
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

        $this->table('users', $db)
            ->drop()
        ;
    }

    public function testPrepareFailure()
    {
        $this->expectException('Exception', 'Failed to prepare SQL:');

        $db = $this->getMockDb();
        $db
            ->expects($this->once())
            ->method('prepare')
            ->willReturn(false)
        ;

        $this->table('users', $db)
            ->insert([
                'id'   => 1,
                'name' => 'Alice',
            ])
        ;
    }

    public function testInsertFailure()
    {
        $this->expectException('Exception', 'Failed to insert?!');

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

        $this->table('users', $db)
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
            ->willReturn('42')
        ;

        $db
            ->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt)
        ;

        $lastId = $this->table('users', $db)
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
        $this->expectException('Exception', 'Failed to update?!');

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

        $this->table('users', $db)
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

        $this->table('users', $db)
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
        $this->expectException('Exception', 'Failed to delete?!');

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

        $this->table('users', $db)
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

        $this->table('users', $db)
            ->delete([
                'id' => 1,
            ])
        ;
    }

    protected function getMockDb()
    {
        $db = $this->getMockBuilder('PDO')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        return $db;
    }

    protected function table(string $name, ?PDO $db = null)
    {
        return new Table($db ?? $this->getMockDb(), $name);
    }

    protected function getMockTableForImport()
    {
        $table = $this->getMockBuilder('Zerifas\\Ladder\\Database\\Table')
            ->setConstructorArgs([$this->getMockDb(), 'users'])
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
}
