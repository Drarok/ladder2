<?php

namespace Zerifas\Ladder\Database;

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

        if (($newName = $this->options['name'] ?? null)) {
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

        if (($limit = $this->options['limit'] ?? null)) {
            $sql .= sprintf('(%s)', $limit);
        } elseif (($options = $this->options['options'] ?? null)) {
            $options = array_map(function ($option) {
                return '\'' . str_replace('\'', '\\\'', $option) . '\'';
            }, $options);
            $sql .= sprintf('(%s)', implode(', ', $options));
        }

        if ($this->options['unsigned'] ?? null) {
            $sql .= ' UNSIGNED';
        }

        // Auto-increment is implicity not null under MySQL, we make it explicit.
        if (! ($this->options['null'] ?? true) || $this->type == 'autoincrement') {
            $sql .= ' NOT NULL';
        }

        if ($this->type == 'autoincrement') {
            $sql .= ' AUTO_INCREMENT';
        }

        if (array_key_exists('default', $this->options)) {
            $default = $this->options['default'];
            if (! is_numeric($default)) {
                $default = '\'' . str_replace('\'', '\\\'', $default) . '\'';
            }
            $sql .= sprintf(' DEFAULT %s', $default);
        }

        if ($this->options['first'] ?? null) {
            $sql .= ' FIRST';
        } elseif (($after = $this->options['after'] ?? null)) {
            $sql .= sprintf(' AFTER `%s`', $after);
        }

        return $sql;
    }
}
