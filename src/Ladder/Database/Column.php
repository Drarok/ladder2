<?php

namespace Ladder\Database;

use Ladder\Arr;

class Column
{
    protected $name;

    protected $type;

    protected $options;

    public function __construct($name, $type, array $options = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->options = $options;
    }

    public function getSQLType()
    {
        if ($this->type == 'autoincrement') {
            $type = 'INTEGER';
        } else {
            $type = strtoupper($this->type);
        }

        if ($limit = Arr::get($this->options, 'limit')) {
            $type .= '(' . $limit . ')';
        }

        if ($unsigned = Arr::get($this->options, 'unsigned')) {
            $type .= ' UNSIGNED';
        }

        if (! ($null = Arr::get($this->options, 'null', true))) {
            $type .= ' NOT NULL';
        }

        if ($this->type == 'autoincrement') {
            if ($null) {
                $type .= ' NOT NULL';
            }
            $type .= ' AUTO_INCREMENT';
        }

        return $type;
    }

    public function getSQLOptions()
    {
        // TODO: Might not need this?
    }
}
