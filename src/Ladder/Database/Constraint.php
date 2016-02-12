<?php

namespace Zerifas\Ladder\Database;

use Zerifas\Ladder\Arr;

class Constraint
{
    /**
     * Name of the index.
     *
     * @var string
     */
    protected $name;

    /**
     * Array of column names in the constraint.
     *
     * @var array
     */
    protected $columns;

    /**
     * Name of the reference table.
     *
     * @var string
     */
    protected $referenceTable;

    /**
     * Reference columns.
     *
     * @var array
     */
    protected $referenceColumns;

    /**
     * Array of options.
     *
     * @var array
     */
    protected $options;

    /**
     * Constructor.
     *
     * @param string $name             Name of the constraint.
     * @param array  $columns          Columns to constrain.
     * @param string $referenceTable   Name of the parent table.
     * @param array  $referenceColumns Columns in the parent table.
     * @param array  $options          Additional options.
     */
    public function __construct($name, array $columns, $referenceTable, array $referenceColumns, array $options = [])
    {
        $this->name = $name;
        $this->columns = $columns;
        $this->referenceTable = $referenceTable;
        $this->referenceColumns = $referenceColumns;
        $this->options = $options;
    }

    /**
     * Get SQL used in CREATE TABLE statements.
     *
     * @return string
     */
    public function getCreateSQL()
    {
        $columns = array_map(
            function ($e) {
                return '`' . $e . '`';
            },
            $this->columns
        );

        $referenceColumns = array_map(
            function ($e) {
                return '`' . $e . '`';
            },
            $this->referenceColumns
        );

        $optionString = '';

        if ($deleteAction = Arr::get($this->options, 'delete')) {
            $optionString .= 'ON DELETE ' . $deleteAction . ' ';
        }

        if ($updateAction = Arr::get($this->options, 'update')) {
            $optionString .= 'ON UPDATE ' . $updateAction . ' ';
        }

        return trim(sprintf(
            'CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s` (%s) %s',
            $this->name,
            implode(', ', $columns),
            $this->referenceTable,
            implode(', ', $referenceColumns),
            $optionString
        ));
    }

    /**
     * Get SQL used in ALTER TABLE when adding an index.
     *
     * @return string
     */
    public function getAddSQL()
    {
        return 'ADD ' . $this->getCreateSQL();
    }

    /**
     * Get SQL used for dropping an index.
     *
     * @return string
     */
    public function getDropSQL()
    {
        return sprintf('DROP FOREIGN KEY `%s`', $this->name);
    }
}
