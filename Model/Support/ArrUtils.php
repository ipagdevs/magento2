<?php

namespace Ipag\Payment\Model\Support;

abstract class ArrUtils
{
    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function accessible($value)
    {
        return is_array($value);
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int  $key
     * @return bool
     */
    public static function exists($array, $key)
    {
        if (is_float($key)) {
            $key = (string) $key;
        }

        // Support dot notation for nested keys (e.g. 'attributes.pix.qrcode')
        if (is_string($key) && strpos($key, '.') !== false) {
            $segments = explode('.', $key);
            $current = $array;

            foreach ($segments as $segment) {
                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return false;
                }
                $current = $current[$segment];
            }

            return true;
        }

        return array_key_exists($key, $array);
    }

    /**
     * Get an item from an array using "dot" notation.
     *
     * @param  \ArrayAccess|array  $array
     * @param  string|int|null  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function get($array, $key, $default = null)
    {
        if (!static::accessible($array)) {
            return value($default);
        }

        if (is_null($key)) {
            return $array;
        }

        // If key exists directly (no dot-notation), return it
        if (static::exists($array, $key) && strpos((string) $key, '.') === false) {
            return $array[$key];
        }

        // If no dot notation present, safely return value or default
        if (false === strpos((string) $key, '.')) {
            return $array[$key] ?? value($default);
        }

        // Traverse nested keys using dot notation
        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return value($default);
            }
        }

        return $array;
    }

    public static function clearArray(array $array, ?bool $falsy = false): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = self::clearArray($value, $falsy);
            }

            if ($value === null || (is_array($value) && count($value) === 0) || (true === $falsy && !$value)) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    public static function arrayToObject($array)
    {
        return is_array($array) ? (object) array_map([__CLASS__, __METHOD__], $array) : $array;
    }
}

if (!function_exists('value')) {
    /**
     * Return the default value of the given value.
     *
     * @param  mixed  $value
     * @param  mixed  ...$args
     * @return mixed
     */
    function value($value, ...$args)
    {
        return $value; // $value instanceof Closure ? $value(...$args) : $value;
    }
}
