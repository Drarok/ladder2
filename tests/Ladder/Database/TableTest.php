<?php

namespace Zerifas\LadderTests\Database;

use PHPUnit_Framework_TestCase;

use Zerifas\Ladder\Database\Table;

class TableTest extends PHPUnit_Framework_TestCase
{
    protected $db;

    protected function setUp()
    {
        parent::setUp();

        $this->db = $this->getMockBuilder('PDO')
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    protected function getMockTableForImport()
    {
        $table = $this->getMockBuilder('Zerifas\\Ladder\\Database\\Table')
            ->setConstructorArgs(['users', $this->db])
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
            '    `name` VARCHAR(30) NOT NULL,',
            '    `occupation` VARCHAR(30),',
            '    PRIMARY KEY (`id`),',
            '    KEY `name` (`name`)',
            ')',
        ]);

        $this->db
            ->expects($this->once())
            ->method('query')
            ->with($sql)
        ;

        Table::factory('users', $this->db)
            ->addColumn('id', 'autoincrement', ['unsigned' => true])
            ->addColumn('name', 'varchar', ['limit' => 30, 'null' => false])
            ->addColumn('occupation', 'varchar', ['limit' => 30])
            ->addIndex('PRIMARY', ['id'])
            ->addIndex('name')
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
            '    ADD KEY `age` (`age`)',
        ]);

        $this->db
            ->expects($this->once())
            ->method('query')
            ->with($sql)
        ;

        Table::factory('users', $this->db)
            ->dropColumn('name', 'varchar', ['limit' => 30, 'null' => false])
            ->alterColumn('occupation', 'varchar', ['limit' => 30])
            ->alterColumn('created', 'datetime', ['name' => 'createdAt'])
            ->dropIndex('name')
            ->addColumn('age', 'integer', ['unsigned' => true])
            ->addIndex('age')
            ->alter()
        ;
    }

    public function testDrop()
    {
        $sql = 'DROP TABLE `users`';

        $this->db
            ->expects($this->once())
            ->method('query')
            ->with($sql)
        ;

        Table::factory('users', $this->db)
            ->drop()
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

        $this->db
            ->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt)
        ;

        Table::factory('users', $this->db)
            ->insert([
                'id'   => 1,
                'name' => 'Alice',
            ])
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

        $this->db
            ->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt)
        ;

        Table::factory('users', $this->db)
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

        $this->db
            ->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmt)
        ;

        Table::factory('users', $this->db)
            ->delete([
                'id'   => 1,
            ])
        ;
    }

}
