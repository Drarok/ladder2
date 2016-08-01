<?php

namespace Zerifas\LadderTests\Database;

use PHPUnit_Framework_TestCase;

class TableTest extends PHPUnit_Framework_TestCase
{
    protected $table;

    protected function setUp()
    {
        parent::setUp();

        $this->table = $this->getMockBuilder('Zerifas\\Ladder\\Database\\Table')
            ->setConstructorArgs(['users'])
            ->setMethods(['insert'])
            ->getMock()
        ;

        $this->table
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
                        'occupation' => 'Junior Developer',
                    ]),
                ]
            )
        ;
    }

    public function testImportCSV()
    {
        $this->table->import(__DIR__ . '/fixtures/TableTest_testImportCSV.csv');
    }

    public function testImportPlainJSON()
    {
        $this->table->import(__DIR__ . '/fixtures/TableTest_testImportPlainJSON.json');
    }

    public function testImportStructuredJSON()
    {
        $this->table->import(__DIR__ . '/fixtures/TableTest_testImportStructuredJSON.json');
    }
}
