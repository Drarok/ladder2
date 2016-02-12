<?php

namespace Zerifas\Ladder\Database;

use Zerifas\Ladder\Arr;

class Index
{
    /**
     * Name of the index.
     *
     * @var string
     */
    protected $name;

    /**
     * Array of column names in the index.
     *
     * @var array
     */
    protected $columns;

    /**
     * Array of options.
     *
     * @var array
     */
    protected $options;

    public function __construct($name, array $columns, array $options = [])
    {
        $this->name = $name;
        $this->columns = $columns;
        $this->options = $options;
    }

    /**
     * Get SQL used in CREATE TABLE statements.
     *
     * @return string
     */
    public function getCreateSQL()
    {
        $sql = '';

        if ($this->name == 'PRIMARY') {
            $sql .= 'PRIMARY KEY (';
        } elseif (Arr::get($this->options, 'unique')) {
            $sql .= sprintf('UNIQUE KEY `%s` (', $this->name);
        } else {
            $sql .= sprintf('KEY `%s` (', $this->name);
        }

        $columns = array_map(
            function ($e) {
                return '`' . $e . '`';
            },
            $this->columns
        );
        $sql .= implode(', ', $columns);

        $sql .= ')';

        return $sql;
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
        if ($this->name === 'PRIMARY') {
            return 'DROP PRIMARY KEY';
        } else {
            return sprintf('DROP KEY `%s`', $this->name);
        }
    }
}
