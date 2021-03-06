<?php

namespace Zerifas\Ladder\Database;

use Zerifas\Collections\Arr;

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

    public function getCreateSQL()
    {
        return sprintf(
            '`%s` %s',
            $this->name,
            $this->getSQLDefinition()
        );
    }

    public function getAddSQL()
    {
        return 'ADD COLUMN ' . $this->getCreateSQL();
    }

    public function getAlterSQL()
    {
        $sql = '';

        if ($newName = Arr::get($this->options, 'name')) {
            return sprintf(
                'CHANGE COLUMN `%s` `%s` %s',
                $this->name,
                $newName,
                $this->getSQLDefinition()
            );
        } else {
            return 'MODIFY COLUMN ' . $this->getCreateSQL();
        }
    }

    public function getDropSQL()
    {
        return sprintf('DROP COLUMN `%s`', $this->name);
    }

    protected function getSQLDefinition()
    {
        if ($this->type == 'autoincrement') {
            $sql = 'INTEGER';
        } else {
            $sql = strtoupper($this->type);
        }

        if ($limit = Arr::get($this->options, 'limit')) {
            $sql .= sprintf('(%s)', $limit);
        } elseif ($options = Arr::get($this->options, 'options')) {
            $options = array_map(function ($option) {
                return '\'' . str_replace('\'', '\\\'', $option) . '\'';
            }, $options);
            $sql .= sprintf('(%s)', implode(', ', $options));
        }

        if (Arr::get($this->options, 'unsigned')) {
            $sql .= ' UNSIGNED';
        }

        // Auto-increment is implicity not null under MySQL, we make it explicit.
        if (! Arr::get($this->options, 'null', true) || $this->type == 'autoincrement') {
            $sql .= ' NOT NULL';
        }

        if ($this->type == 'autoincrement') {
            $sql .= ' AUTO_INCREMENT';
        }

        if (array_key_exists('default', $this->options)) {
            $default = Arr::get($this->options, 'default');
            if (! is_numeric($default)) {
                $default = '\'' . str_replace('\'', '\\\'', $default) . '\'';
            }
            $sql .= sprintf(' DEFAULT %s', $default);
        }

        if (Arr::get($this->options, 'first')) {
            $sql .= ' FIRST';
        } elseif ($after = Arr::get($this->options, 'after')) {
            $sql .= sprintf(' AFTER `%s`', $after);
        }

        return $sql;
    }
}
