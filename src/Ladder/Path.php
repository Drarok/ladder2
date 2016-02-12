<?php

namespace Zerifas\Ladder;

abstract class Path
{
    /**
     * Join together all parameters with a directory separator.
     *
     * @return string
     */
    public static function join()
    {
        return implode(
            DIRECTORY_SEPARATOR,
            array_map(
                function ($k, $v) {
                    if ($k === 0) {
                        // Allow the 1st element to have a leading slash for absolute paths.
                        return rtrim($v, '/\\');
                    } else {
                        // The rest are trimmed.
                        return trim($v, '/\\');
                    }
                },
                array_keys(func_get_args()),
                func_get_args()
            )
        );
    }
}
