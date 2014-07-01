<?php

namespace Ladder\Database;

use Ladder\Arr;

class Index
{
    protected $name;

    protected $columns;

    public function __construct($name, array $columns)
    {
        $this->name = $name;
        $this->columns = $columns;
    }

    public function getCreateSQL()
    {
        $sql = '';

        if ($this->name == 'PRIMARY') {
            $sql .= 'PRIMARY KEY (';
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

    public function getAddSQL()
    {
        return 'ADD ' . $this->getCreateSQL();
    }

    public function getDropSQL()
    {
        if ($this->name === 'PRIMARY') {
            return 'DROP PRIMARY KEY';
        } else {
            return sprintf('DROP KEY `%s`', $this->name);
        }
    }
}
