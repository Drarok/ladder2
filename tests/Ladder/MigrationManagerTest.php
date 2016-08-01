<?php

namespace Zerifas\LadderTests;

use PHPUnit_Framework_TestCase;
use Pimple;

use Zerifas\Ladder\MigrationManager;

class MigrationManagerTest extends PHPUnit_Framework_TestCase
{
    protected $db;
    protected $stmt;

    protected $manager;

    protected function setUp()
    {
        parent::setUp();

        $this->stmt = $this->getMockBuilder('PDOStatement')
            ->getMock()
        ;

        $this->db = $this->getMockBuilder('PDO')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->db->expects($this->any())
            ->method('prepare')
            ->willReturn($this->stmt)
        ;

        $this->db->expects($this->any())
            ->method('query')
            ->willReturn($this->stmt)
        ;

        $container = new Pimple();
        $container['db'] = $this->db;

        $this->manager = new MigrationManager($container);
        $this->manager->addNamespace('App\\Migration', __DIR__ . '/MigrationManagerTest/Migration');
    }

    public function testAddNamespaceFailsOnDuplicate()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->manager->addNamespace('App\\Migration', __DIR__ . '/MigrationManagerTest/Migration');
    }

    public function testGetCurrentMigrationIdWithoutTable()
    {
        $this->stmt
            ->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(false)
        ;

        $this->assertSame(0, $this->manager->getCurrentMigrationId());
    }

    public function testGetCurrentMigrationIdWithEmptyTable()
    {
        $this->stmt
            ->method('fetchColumn')
            ->willReturnOnConsecutiveCalls(
                'ladder:migrations',
                false
            )
        ;

        $this->assertSame(0, $this->manager->getCurrentMigrationId());
    }

    public function testGetCurrentMigrationIdWithTable()
    {
        $this->stmt
            ->method('fetchColumn')
            ->willReturnOnConsecutiveCalls(
                'ladder:migrations',
                11
            )
        ;

        $this->assertSame(11, $this->manager->getCurrentMigrationId());
    }

    public function testGetAllMigrations()
    {
        $migrations = $this->manager->getAllMigrations();
        $this->assertCount(2, $migrations);
        $this->assertEquals(['000001', '000002'], array_keys($migrations));
    }

    public function testGetMigrationByIdFailsWithInvalidId()
    {
        $this->setExpectedException('InvalidArgumentException');
        $migration = $this->manager->getMigrationById('555555');
    }

    public function testGetMigrationById()
    {
        $migration = $this->manager->getMigrationById('000001');
        $this->assertInstanceOf('Zerifas\\Ladder\\Migration\\AbstractMigration', $migration);
    }

    public function testGetLatestMigration()
    {
        $migration = $this->manager->getLatestMigration();
        $this->assertInstanceOf('Zerifas\\Ladder\\Migration\\AbstractMigration', $migration);
    }

    public function testHasAvailableMigrations()
    {
        $this->db
            ->method('query')
            ->will($this->returnCallback(function ($sql) {
                if ($sql == 'SHOW TABLES LIKE \'ladder:migrations\'') {
                    $this->stmt
                        ->method('fetchColumn')
                        ->willReturn('ladder:migrations')
                    ;
                } else {
                    $this->stmt
                        ->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            ['id' => '000001', 'appliedAt' => '2016-08-01 16:39:00'],
                            false
                        )
                    ;
                }

                return $this->stmt;
            }))
        ;

        $this->assertTrue($this->manager->hasAvailableMigrations());
    }

    public function testHasAvailableMigrationsWithNoTable()
    {
        $this->db
            ->method('query')
            ->will($this->returnCallback(function ($sql) {
                if ($sql == 'SHOW TABLES LIKE \'ladder:migrations\'') {
                    $this->stmt
                        ->method('fetchColumn')
                        ->willReturn('ladder:migrations')
                    ;
                } else {
                    $this->stmt
                        ->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            ['id' => '000001', 'appliedAt' => '2016-08-01 16:39:00'],
                            ['id' => '000002', 'appliedAt' => '2016-08-01 16:39:00'],
                            false
                        )
                    ;
                }

                return $this->stmt;
            }))
        ;

        $this->assertFalse($this->manager->hasAvailableMigrations());
    }

    public function testGetAvailableMigrations()
    {
        $this->db
            ->method('query')
            ->will($this->returnCallback(function ($sql) {
                if ($sql == 'SHOW TABLES LIKE \'ladder:migrations\'') {
                    $this->stmt
                        ->method('fetchColumn')
                        ->willReturn('ladder:migrations')
                    ;
                } else {
                    $this->stmt
                        ->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            ['id' => '000001', 'appliedAt' => '2016-08-01 16:39:00'],
                            false
                        )
                    ;
                }

                return $this->stmt;
            }))
        ;

        $available = $this->manager->getAvailableMigrations();
        $this->assertCount(1, $available);
    }

    public function testGetAppliedMigrations()
    {
        $this->db
            ->method('query')
            ->will($this->returnCallback(function ($sql) {
                if ($sql == 'SHOW TABLES LIKE \'ladder:migrations\'') {
                    $this->stmt
                        ->method('fetchColumn')
                        ->willReturn('ladder:migrations')
                    ;
                } else {
                    $this->stmt
                        ->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            ['id' => '000001', 'appliedAt' => '2016-08-01 16:39:00'],
                            false
                        )
                    ;
                }

                return $this->stmt;
            }))
        ;

        $available = $this->manager->getAppliedMigrations();
        $this->assertCount(1, $available);
        $this->assertEquals('2016-08-01 16:39:00', $available['000001']->getAppliedAt());
    }

    public function testHasAppliedMigrationsWithNoTable()
    {
        $this->stmt
            ->method('fetchColumn')
            ->willReturn(false)
        ;

        $this->assertFalse($this->manager->hasAppliedMigrations());
    }

    public function testHasAppliedMigrations()
    {
        $this->db
            ->method('query')
            ->will($this->returnCallback(function ($sql) {
                if ($sql == 'SHOW TABLES LIKE \'ladder:migrations\'') {
                    $this->stmt
                        ->method('fetchColumn')
                        ->willReturn('ladder:migrations')
                    ;
                } else {
                    $this->stmt
                        ->method('fetch')
                        ->willReturnOnConsecutiveCalls(
                            ['id' => '000001', 'appliedAt' => '2016-08-01 16:39:00'],
                            false
                        )
                    ;
                }

                return $this->stmt;
            }))
        ;

        $this->assertTrue($this->manager->hasAppliedMigrations());
    }

    public function testHasMigrationsTable()
    {
        $this->stmt
            ->expects($this->exactly(2))
            ->method('fetchColumn')
            ->willReturnOnConsecutiveCalls(
                false,
                'ladder:migrations'
            )
        ;

        $this->assertFalse($this->manager->hasMigrationsTable());
        $this->assertTrue($this->manager->hasMigrationsTable());
    }

    public function testEmptyPathsAreReported()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Missing migrations path for namespace \'App\\Plugin\\Migration\'.'
        );
        $this->manager->addNamespace('App\\Plugin\\Migration', '');
        $this->manager->getAllMigrations();
    }

    public function testInvalidPathsAreReported()
    {
        $this->setExpectedException(
            'InvalidArgumentException',
            'Invalid migrations path for namespace \'App\\Plugin\\Migration\': ' // Path deliberately omitted here
        );
        $this->manager->addNamespace('App\\Plugin\\Migration', 'no/such/path');
        $this->manager->getAllMigrations();
    }

    public function testApplyMigrationWithValidMigration()
    {
        $this->stmt
            ->method('fetch')
            ->willReturn(false)
        ;
        $migration = $this->manager->getMigrationById('000001');
        $this->manager->applyMigration($migration);
        $this->assertRegExp('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $migration->getAppliedAt());
    }

    public function testApplyMigrationWithInvalidMigration()
    {
        $migration = $this->manager->getMigrationById('000002');
        try {
            $this->manager->applyMigration($migration);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() !== 'Deliberate failure') {
                throw $e;
            }
        }
        $this->assertNull($migration->getAppliedAt());
    }

    public function testRollbackMigrationWithValidMigration()
    {
        $this->stmt
            ->method('fetchColumn')
            ->willReturn(json_encode(['userId' => 151]))
        ;
        $migration = $this->manager->getMigrationById('000001');
        $migration->setAppliedAt(date('Y-m-d H:i:s'));
        $this->manager->rollbackMigration($migration);
        $this->assertNull($migration->getAppliedAt());
    }

    public function testRollbackMigrationWithInvalidMigration()
    {
        $migration = $this->manager->getMigrationById('000002');
        $migration->setAppliedAt(date('Y-m-d H:i:s'));
        try {
            $this->manager->rollbackMigration($migration);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() !== 'Deliberate failure') {
                throw $e;
            }
        }
        $this->assertRegExp('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $migration->getAppliedAt());
    }
}
