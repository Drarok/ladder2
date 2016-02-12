<?php

namespace Zerifas\Ladder\Database;

use Exception;
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
     * Index objects, keyed on action.
     *
     * @see $this->clear()
     *
     * @var array
     */
    protected $indexes;

    /**
     * Constraint objects, keyed on action.
     *
     * @see  $this->clear()
     *
     * @var array
     */
    protected $constraints;

    /**
     * Last insert id.
     *
     * @var integer
     */
    protected $lastInsertId;

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
     * Getter for the name of the table.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
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
     * @param array  $options Optional array of options.
     *
     * @return $this
     */
    public function addIndex($name, array $columns = null, array $options = [])
    {
        // If no columns passed in, default to a column matching the index name.
        if ($columns === null) {
            $columns = [$name];
        }

        $this->indexes['add'][$name] = new Index($name, $columns, $options);
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
     * Add a constraint.
     *
     * @param array  $columns          Columns in this table to constrain.
     * @param string $referenceTable   Name of the parent table.
     * @param array  $referenceColumns Columns in the parent table.
     * @param array  $options          Additional options.
     *
     * @return $this
     */
    public function addConstraint(array $columns, $referenceTable, array $referenceColumns, array $options = [])
    {
        $name = $this->generateConstraintName($columns, $referenceTable, $referenceColumns);

        $this->constraints['add'][$name] = new Constraint(
            $name,
            $columns,
            $referenceTable,
            $referenceColumns,
            $options
        );
        return $this;
    }

    /**
     * Drop a constraint.
     *
     * @param array  $columns          Columns in this table to constrain.
     * @param string $referenceTable   Name of the parent table.
     * @param array  $referenceColumns Columns in the parent table.
     *
     * @return $this
     */
    public function dropConstraint(array $columns, $referenceTable, array $referenceColumns)
    {
        $name = $this->generateConstraintName($columns, $referenceTable, $referenceColumns);
        $this->constraints['drop'][$name] = new Constraint($name, $columns, $referenceTable, $referenceColumns);
        return $this;
    }

    /**
     * Drop a constraint by its name.
     *
     * @param string $name Name of the constraint.
     *
     * @return $this
     */
    public function dropConstraintByName($name)
    {
        $this->constraints['drop'][$name] = new Constraint($name, [], '', []);
        return $this;
    }

    /**
     * Create the table.
     *
     * @return $this
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

        foreach ($this->constraints['add'] as $constraint) {
            $elements[] = $constraint->getCreateSQL();
        }

        $elements = array_map(
            function ($e) {
                return PHP_EOL . '    ' . $e;
            },
            $elements
        );

        $sql = sprintf(
            'CREATE TABLE `%s` (%s)',
            $this->name,
            implode(',', $elements) . PHP_EOL
        );

        $this->db->query($sql);

        $this->clear();

        return $this;
    }

    /**
     * Alter the table.
     *
     * @return $this
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

        foreach ($this->constraints['drop'] as $constraint) {
            $elements[] = $constraint->getDropSQL();
        }

        foreach ($this->constraints['add'] as $constraint) {
            $elements[] = $constraint->getAddSQL();
        }

        $this->db->query(sprintf(
            "ALTER TABLE `%s`\n    %s",
            $this->name,
            implode(',' . PHP_EOL . '    ', $elements)
        ));

        $this->clear();

        return $this;
    }

    /**
     * Drop the table.
     *
     * @return $this
     */
    public function drop()
    {
        $sql = sprintf(
            'DROP TABLE `%s`',
            $this->name
        );

        $this->db->query($sql);

        return $this;
    }

    /**
     * Insert a row to the table.
     *
     * @param array $data Table data, column => value.
     *
     * @return $this
     */
    public function insert(array $data)
    {
        $columns = array_map(
            function ($column) {
                return '`' . $column . '`';
            },
            array_keys($data)
        );

        $placeholders = array_map(
            function ($column) {
                return ':' . $column;
            },
            array_keys($data)
        );

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->getName(),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        if (!$this->db->prepare($sql)->execute($data)) {
            // TODO: Improve this.
            throw new Exception('Failed to insert?!');
        }

        $this->lastInsertId = (int) $this->db->lastInsertId();

        return $this;
    }

    /**
     * Update data in the table.
     *
     * @param array $data  Table data, column => value.
     * @param array $where Where clauses, column => value.
     *
     * @return $this
     */
    public function update(array $data, array $where)
    {
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->getName(),
            $this->generateClauses($data, ', ', 'data_'),
            $this->generateClauses($where, ' AND ', 'where_')
        );

        $params = [];

        foreach ($data as $key => $value) {
            $params['data_' . $key] = $value;
        }

        foreach ($where as $key => $value) {
            $params['where_' . $key] = $value;
        }

        if (!$this->db->prepare($sql)->execute($params)) {
            // TODO: Improve this.
            throw new Exception('Failed to update?!');
        }

        return $this;
    }

    /**
     * Delete data from the table.
     *
     * @param array $where Where clauses, column => value.
     *
     * @return $this
     */
    public function delete(array $where)
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $this->getName(),
            $this->generateClauses($where, ' AND ')
        );

        if (!$this->db->prepare($sql)->execute($where)) {
            // TODO: Improve this.
            throw new Exception('Failed to delete?!');
        }

        return $this;
    }

    /**
     * Get the last insert id.
     *
     * @return int
     */
    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }

    /**
     * Generate clauses.
     *
     * @param array  $where       Array of key => value pairs to use.
     * @param string $join        String to use between clauses.
     * @param string $paramPrefix Prefix for the parameters.
     *
     * @return string
     */
    protected function generateClauses(array $where, $join, $paramPrefix = '')
    {
        $clauses = array_map(
            function ($column) use ($paramPrefix) {
                return sprintf('`%s` = :%s', $column, $paramPrefix . $column);
            },
            array_keys($where)
        );

        return implode($join, $clauses);
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

        $this->constraints = [
            'drop' => [] ,
            'add'  => [] ,
        ];
    }

    /**
     * Generate a unique constraint name.
     *
     * @param array  $columns          Columns in this table to constrain.
     * @param string $referenceTable   Name of the parent table.
     * @param array  $referenceColumns Columns in the parent table.
     *
     * @return string
     */
    protected function generateConstraintName(array $columns, $referenceTable, array $referenceColumns)
    {
        return sprintf(
            '%s:%s::%s:%s',
            $this->getName(),
            implode(':', $columns),
            $referenceTable,
            implode(':', $referenceColumns)
        );
    }
}
