<?php

namespace Ladder;

abstract class Arr
{
    public static function get($arr, $key, $default = null)
    {
        return array_key_exists($key, $arr) ? $arr[$key] : $default;
    }

    public static function filter($arr, $callback = null)
    {
        if ($callback === null) {
            $callback = function ($key, $val) {
                return (bool) $val;
            };
        }

        $result = [];
        foreach ($arr as $key => $val) {
            if ($callback($key, $val)) {
                $result[$key] = $val;
            }
        }
        return $result;
    }
}
