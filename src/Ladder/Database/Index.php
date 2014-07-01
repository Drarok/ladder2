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

    public function getSQL()
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
}
