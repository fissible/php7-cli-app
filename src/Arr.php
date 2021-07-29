<?php declare(strict_types=1);

namespace PhpCli;

class Arr
{

    public static function fromObject($object): array
    {
        if (!is_object($object)) throw new \InvalidArgumentException();
        return json_decode(json_encode($object), true);
    }

    /**
     * Get a nested value from an array.
     * 
     * @param array $array
     * @param string $path
     * @param string $delimiter
     * @param callable $sanitizeKey
     * @return mixed
     */
    public static function get(array $array, string $path, string $delimiter, callable $sanitizeKey = null)
    {
        $mixed = $array;
        $keys = array_filter(explode($delimiter, $path));

        foreach ($keys as $key) {
            if ($sanitizeKey) $key = $sanitizeKey($key);
            if (!isset($mixed[$key])) return null;
            $mixed = $mixed[$key];
        }

        return $mixed;
    }

    /**
     * Check if the array is an associative array, eg.
     * [
     *    'val' => 'ue'
     * ]
     * 
     * @param array $array
     * @return bool
     */
    public static function isAssociative(array $array): bool
    {
        return !static::isIndexed($array);
    }

    /**
     * Check if the array is an indexed array, eg.
     * [
     *    'val', 'ue'
     * ]
     * 
     * @param array $array
     * @return bool
     */
    public static function isIndexed(array $array): bool
    {
        foreach ($array as $key => $_) {
            if (!filter_var($key, FILTER_VALIDATE_INT)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get a nested value from an array.
     * 
     * @param array $array
     * @param string $path
     * @param mixed $value
     * @param string $delimiter
     * @param bool $createMissing
     * @param callable $sanitizeKey
     * @return bool
     */
    public static function set(array &$array, string $path, $value, string $delimiter, bool $createMissing = true, callable $sanitizeKey = null): bool
    {
        $arr = &$array;
        $keys = array_filter(explode($delimiter, $path));
        $indexedArray = static::isIndexed($array);

        foreach ($keys as $key) {
            if ($sanitizeKey) $key = $sanitizeKey($key);

            if ($indexedArray) {
                $key = intval($key);
            }

            if (!isset($arr[$key])) {
                if ($createMissing) {
                    $arr[$key] = [];
                } else {
                    return false;
                }
            }
            $arr = &$arr[$key];
        }
        $arr = $value;
        unset($arr);

        return true;
    }

    /**
     * Convert an array to a \stdClass object instance
     * 
     * @param array $array
     * @return \sdtClass
     */
    public static function toObject(array $array): \stdClass
    {
        return (object) json_decode(json_encode($array));
    }
}