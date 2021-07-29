<?php declare(strict_types=1);

namespace PhpCli\Config;

use PhpCli\Arr;
use PhpCli\Exceptions\ConfigNotFoundException;
use PhpCli\Filesystem\File;
use PhpCli\Interfaces\Config;

/**
 * @todo: rename or refactor to use array only (no temp file)
 */

class Memory implements Config
{
    private \stdClass $data;

    public function __construct($data = null)
    {
        $this->data = new \stdClass;

        if (is_array($data)) {
            $data = Arr::toObject($data);
        }

        if (is_object($data)) {
            $this->setData($data);
        }
    }

    /**
     * Get a value at the specified key on the data object.
     * 
     * @param string $name
     * @return self
     */
    public function get(string $name, bool $skipPointer = false)
    {
        if (empty($name)) {
            return $this->getData();
        }

        if (false !== strpos($name, '.')) {
            // $keys = explode('.', $name);
            // $data = $this->data;

            // foreach ($keys as $key) {
            //     if (isset($data->$key)) {
            //         $data = $data->$key;
            //     } else {
            //         $data = null;
            //     }
            // }

            // return $data;

            return array_reduce(explode('.', $name), function ($previous, $current) {
                return is_numeric($current) ? ($previous[$current] ?? null) : ($previous->$current ?? null);
            }, $this->data);
        } elseif (isset($this->data->$name)) {
            return $this->data->$name;
        }

        return null;
    }

    /**
     * Get the data object.
     * 
     * @return \stdClass
     */
    public function getData(): \stdClass
    {
        return $this->data;
    }

    /**
     * Check if a key is set on the data object.
     * 
     * @param string $name
     * @return self
     */
    public function has(string $name): bool
    {
        if (empty($name)) {
            return !empty((array) $this->data);
        }

        if (false !== strpos($name, '.')) {
            // $keys = explode('.', $name);
            // $data = $this->data;

            // foreach ($keys as $key) {
            //     if (isset($data->$key)) {
            //         $data = $data->$key;
            //     } else {
            //         $data = null;
            //     }
            // }

            $data = array_reduce(explode('.', $name), function ($previous, $current) {
                return is_numeric($current) ? ($previous[$current] ?? null) : ($previous->$current ?? null);
            }, $this->data);

            return !is_null($data);
        }

        return isset($this->data->$name);
    }

    /**
     * Set a value on the data object.
     * 
     * @param string $name
     * @param mixed $value;
     * @return self
     */
    public function set(string $name, $value): self
    {
        if (empty($name)) throw new \InvalidArgumentException('Cannot set an empty key.');

        if (false !== strpos($name, '.')) {
            $array = Arr::fromObject($this->data);

            if (Arr::set($array, $name, $value, '.')) {
                $this->data = Arr::toObject($array);
            } else {
                throw new \Exception(sprintf('%s: error setting value.', $name));
            }
        } else {
            if (!isset($this->data)) {
                $this->data = new \stdClass;
            }
            $this->data->$name = $value;
        }

        return $this;
    }

    /**
     * Set the data object.
     * 
     * @param \stdClass $data
     * @return self
     */
    public function setData(\stdClass $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name): bool
    {
        return $this->has($name);
    }

    public function __set(string $name, $value)
    {
        return $this->set($name, $value);
    }
}