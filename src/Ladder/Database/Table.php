<?php

namespace Zerifas\Ladder\Database;

use PDO;

class Table
{
    /**
     * Column objects, keyed on action.
     *
     * @see $this->clear()
     */
    protected array $columns;

    /**
     * Index objects, keyed on action.
     *
     * @see $this->clear()
     */
    protected array $indexes;

    /**
     * Constraint objects, keyed on action.
     *
     * @see  $this->clear()
     */
    protected array $constraints;

    /**
     * Last row's id from Table->insert().
     *
     * @see $this->insert()
     */
    protected ?int $lastInsertId;

    public function __construct(private readonly PDO $db, private readonly string $name)
    {
        $this->clear();
    }

    public function getName()
    {
        return $this->name;
    }

    public function addColumn(string $name, string $type, array $options = [])
    {
        $this->columns['add'][$name] = new Column($name, $type, $options);
        return $this;
    }

    public function alterColumn(string $name, string $type, array $options = [])
    {
        $this->columns['alter'][$name] = new Column($name, $type, $options);
        return $this;
    }

    public function dropColumn(string $name)
    {
        $this->columns['drop'][$name] = new Column($name, 'DROP_COLUMN');
        return $this;
    }

    public function addIndex(string $name, ?array $columns = null, ?array $options = [])
    {
        // If no columns passed in, default to a column matching the index name.
        if ($columns === null) {
            $columns = [$name];
        }

        $this->indexes['add'][$name] = new Index($name, $columns, $options);
        return $this;
    }

    public function dropIndex(string $name)
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
    public function addConstraint(array $columns, string $referenceTable, array $referenceColumns, array $options = [])
    {
        if (array_key_exists('name', $options)) {
            $name = $options['name'];
        } else {
            $name = $this->generateConstraintName($columns, $referenceTable, $referenceColumns);
        }

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
    public function dropConstraint(array $columns, string $referenceTable, array $referenceColumns)
    {
        $name = $this->generateConstraintName($columns, $referenceTable, $referenceColumns);
        $this->constraints['drop'][$name] = new Constraint($name, $columns, $referenceTable, $referenceColumns);
        return $this;
    }

    public function dropConstraintByName(string $name)
    {
        $this->constraints['drop'][$name] = new Constraint($name, [], '', []);
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

        if (!($stmt = $this->db->prepare($sql))) {
            var_dump($stmt);
            throw new \Exception("Failed to prepare SQL: $sql");
        }

        if (!$stmt->execute($data)) {
            // TODO: Improve this.
            throw new \Exception('Failed to insert?!');
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
            throw new \Exception('Failed to update?!');
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
            throw new \Exception('Failed to delete?!');
        }

        return $this;
    }

    /**
     * Get the last insert id.
     *
     * @return int
     */
    public function getLastInsertId(): int
    {
        return $this->lastInsertId;
    }

    /**
     * Import a file to the table.
     *
     * @param string $pathname Pathname of the file to import. Supports CSV and JSON.
     *
     * @return $this
     */
    public function import(string $pathname)
    {
        $fileInfo = new \SplFileInfo($pathname);

        switch (strtolower($fileInfo->getExtension())) {
            case 'csv':
                $this->importCSV($pathname);
                break;

            case 'json':
                $this->importJSON($pathname);
                break;

            default:
                throw new \InvalidArgumentException(sprintf(
                    'Invalid file extension: %s',
                    $fileInfo->getExtension()
                ));
        }

        return $this;
    }

    /**
     * Generate clauses.
     *
     * @param $where       Array of key => value pairs to use.
     * @param $join        String to use between clauses.
     * @param $paramPrefix Prefix for the parameters.
     */
    protected function generateClauses(array $where, string $join, string $paramPrefix = ''): string
    {
        $clauses = array_map(
            function ($column) use ($paramPrefix) {
                return sprintf('`%s` = :%s', $column, $paramPrefix . $column);
            },
            array_keys($where)
        );

        return implode($join, $clauses);
    }

    protected function clear(): void
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
    protected function generateConstraintName(array $columns, string $referenceTable, array $referenceColumns)
    {
        return sprintf(
            '%s:%s::%s:%s',
            $this->getName(),
            implode(':', $columns),
            $referenceTable,
            implode(':', $referenceColumns)
        );
    }

    protected function importCSV(string $pathname): void
    {
        $file = fopen($pathname, 'r');

        $columns = null;

        $nullify = function ($value) {
            return $value === 'null' ? null : $value;
        };

        while (($row = fgetcsv($file, 0, ',', '"', '"')) !== false) {
            // Skip blank lines (represented as [null] by fgetcsv).
            if (count($row) === 1 && $row[0] === null) {
                continue;
            }

            if ($columns === null) {
                $columns = $row;
                continue;
            }

            $this->insert(array_combine($columns, array_map($nullify, $row)));
        }

        fclose($file);
    }

    protected function importJSON(string $pathname): void
    {
        $json = json_decode(file_get_contents($pathname), true);

        if (isset($json['columns']) && isset($json['data'])) {
            $columns = $json['columns'];

            foreach ($json['data'] as $row) {
                $this->insert(array_combine($columns, $row));
            }
        } else {
            foreach ($json as $row) {
                $this->insert($row);
            }
        }
    }
}
