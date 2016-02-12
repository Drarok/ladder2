<?php

namespace Ladder;

abstract class Arr
{
    public static function get(array $arr, $key, $default = null)
    {
        return array_key_exists($key, $arr) ? $arr[$key] : $default;
    }
}
