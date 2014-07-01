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

    public function alterColumn($name, $type, array $options = [])
    {
        $this->columns['alter'][$name] = new Column($name, $type, $options);
        return $this;
    }

    public function dropColumn($name)
    {
        $this->columns['drop'][$name] = new Column($name, 'DROP_COLUMN');
        return $this;
    }

    public function addIndex($name, array $columns)
    {
        $this->indexes['add'][$name] = new Index($name, $columns);
        return $this;
    }

    public function dropIndex($name)
    {
        $this->indexes['drop'][$name] = new Index($name, []);
        return $this;
    }

    public function create()
    {
        $elements = [];

        foreach ($this->columns['add'] as $column) {
            $elements[] = $column->getCreateSQL();
        }

        foreach ($this->indexes['add'] as $index) {
            $elements[] = $index->getCreateSQL();
        }

        $this->db->query(sprintf(
            'CREATE TABLE `%s` (%s)',
            $this->name,
            PHP_EOL . implode(',' . PHP_EOL, $elements) . PHP_EOL
        ));
    }

    public function alter()
    {
        $elements = [];

        foreach ($this->columns['drop'] as $column) {
            $elements[] = $column->getDropSQL();
        }

        foreach ($this->columns['alter'] as $column) {
            $elements[] = $column->getAlterSQL();
        }

        foreach ($this->columns['add'] as $column) {
            $elements[] = $column->getAddSQL();
        }

        try {
            $this->db->query($sql = sprintf(
                "ALTER TABLE `%s`\n    %s",
                $this->name,
                implode(',' . PHP_EOL . '    ', $elements)
            ));
        } catch (\Exception $e) {
            echo $sql, PHP_EOL;
            throw $e;
        }
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
