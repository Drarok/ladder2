<?php

namespace Ladder\Database;

use PDO;

class Table
{
    /**
     * Default database to use if none specified.
     *
     * @var PDO
     */
    protected static $defaultDb;

    /**
     * Database to use for this instance.
     *
     * @var PDO
     */
    protected $db;

    /**
     * Name of the table.
     *
     * @var string
     */
    protected $name;

    /**
     * Column objects, keyed on action.
     *
     * @see $this->clear()
     *
     * @var array
     */
    protected $columns;

    /**
     * Index object, keyed on action.
     *
     * @see $this->clear()
     *
     * @var array
     */
    protected $indexes;

    /**
     * Factory.
     *
     * @param string $name Name of the table.
     * @param PDO    $db   Optional database to use.
     *
     * @return Table
     */
    public static function factory($name, PDO $db = null)
    {
        return new static($name, $db);
    }

    /**
     * Setter for the default database.
     *
     * @param PDO $defaultDb Default database.
     *
     * @return void
     */
    public static function setDefaultDb(PDO $defaultDb)
    {
        static::$defaultDb = $defaultDb;
    }

    /**
     * Constructor
     *
     * @param string $name Name of the table.
     * @param PDO    $db   Optional database to use.
     */
    public function __construct($name, PDO $db = null)
    {
        $this->name = $name;

        if ($db === null) {
            $db = static::$defaultDb;
        }

        $this->db = $db;

        $this->clear();
    }

    /**
     * Add a column.
     *
     * @param string $name    Name of the column.
     * @param string $type    Data type of the column.
     * @param array  $options Optional array of options.
     *
     * @return $this
     */
    public function addColumn($name, $type, array $options = [])
    {
        $this->columns['add'][$name] = new Column($name, $type, $options);
        return $this;
    }

    /**
     * Alter a column.
     *
     * @param string $name    Name of the column.
     * @param string $type    Data type of the column.
     * @param array  $options Optional array of options.
     *
     * @return $this
     */
    public function alterColumn($name, $type, array $options = [])
    {
        $this->columns['alter'][$name] = new Column($name, $type, $options);
        return $this;
    }

    /**
     * Drop a column.
     *
     * @param string $name Name of the column.
     *
     * @return $this
     */
    public function dropColumn($name)
    {
        $this->columns['drop'][$name] = new Column($name, 'DROP_COLUMN');
        return $this;
    }

    /**
     * Add an index.
     *
     * @param string $name    Name of the index.
     * @param array  $columns Optional array of column names.
     *
     * @return $this
     */
    public function addIndex($name, array $columns = null)
    {
        // If no columns passed in, default to a column matching the index name.
        if ($columns === null) {
            $columns = [$name];
        }

        $this->indexes['add'][$name] = new Index($name, $columns);
        return $this;
    }

    /**
     * Drop an index.
     *
     * @param string $name Name of the index.
     *
     * @return $this
     */
    public function dropIndex($name)
    {
        $this->indexes['drop'][$name] = new Index($name, []);
        return $this;
    }

    /**
     * Create the table.
     *
     * @return void
     */
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

        $this->clear();
    }

    /**
     * Alter the table.
     *
     * @return void
     */
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

        foreach ($this->indexes['drop'] as $index) {
            $elements[] = $index->getDropSQL();
        }

        foreach ($this->indexes['add'] as $index) {
            $elements[] = $index->getAddSQL();
        }

        $this->db->query(sprintf(
            "ALTER TABLE `%s`\n    %s",
            $this->name,
            implode(',' . PHP_EOL . '    ', $elements)
        ));

        $this->clear();
    }

    /**
     * Drop the table.
     *
     * @return void
     */
    public function drop()
    {
        $sql = sprintf(
            'DROP TABLE `%s`',
            $this->name
        );

        $this->db->query($sql);
    }

    /**
     * Reset the action arrays.
     *
     * @return void
     */
    protected function clear()
    {
        $this->columns = [
            'drop'  => [],
            'alter' => [],
            'add'   => [],
        ];

        $this->indexes = [
            'drop'  => [],
            'add'   => [],
        ];
    }
}
