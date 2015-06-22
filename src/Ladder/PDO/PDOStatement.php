<?php

namespace Ladder\PDO;

class PDOStatement extends \PDOStatement
{
    /**
     * Constructor is required, but must not be public.
     */
    private function __construct()
    {
    }

    public function execute($params = null)
    {
        // TODO: Output if option set.
        if ($params === null) {
            return parent::execute();
        } else {
            return parent::execute($params);
        }
    }
}
