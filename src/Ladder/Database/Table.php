<?php

namespace Ladder\Database;

use PDO;

class Table
{
    protected static $defaultDb;

    protected $db;

    protected $name;

    protected $columns = [
        'drop'  => [],
        'alter' => [],
        'add'   => [],
    ];

    protected $indexes = [
        'drop'  => [],
        'alter' => [],
        'add'   => [],
    ];

    public static function factory($name, PDO $db = null)
    {
        return new static($name, $db);
    }

    public static function setDefaultDb(PDO $defaultDb)
    {
        static::$defaultDb = $defaultDb;
    }

    public function __construct($name, PDO $db = null)
    {
        $this->name = $name;

        if ($db === null) {
            $db = static::$defaultDb;
        }

        $this->db = $db;
    }

    public function addColumn($name, $type, array $options = [])
    {
        $this->columns['add'][$name] = new Column($name, $type, $options);
        return $this;
    }

    public function addIndex($name, array $columns)
    {
        $this->indexes['add'][$name] = new Index($name, $columns);
        return $this;
    }

    public function create()
    {
        $elements = [];

        foreach ($this->columns['add'] as $name => $column) {
            $elements[] = trim(sprintf(
                '`%s` %s %s',
                $name,
                $column->getSQLType(),
                $column->getSQLOptions()
            ));
        }

        foreach ($this->indexes['add'] as $name => $index) {
            $elements[] = $index->getSQL();
        }

        $sql = sprintf(
            'CREATE TABLE `%s` (%s)',
            $this->name,
            PHP_EOL . implode(',' . PHP_EOL, $elements) . PHP_EOL
        );

        $this->db->query($sql);
    }

    public function drop()
    {
        $sql = sprintf(
            'DROP TABLE `%s`',
            $this->name
        );

        $this->db->query($sql);
    }
}
