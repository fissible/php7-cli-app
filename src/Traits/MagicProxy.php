<?php declare(strict_types=1);

namespace PhpCli\Traits;

trait MagicProxy
{
    public function __call($name, $args)
    {
        return call_user_func_array([$this, $name], $args);
    }

    public static function __callStatic($name, $args)
    {
        return call_user_func_array([static::class, $name], $args);
    }

    public function __get($name)
    {
        if (false !== strpos($name, '_')) {
            $name = implode(array_map(function (string $part) {
                return ucfirst(strtolower($part));
            }, explode('_', $name)));
        }
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }

        if (isset($this->$name)) {
            return $this->$name;
        }

        return null;
    }

    public function __isset($name): bool
    {
        return isset($this->$name);
    }

    public function __set(string $name, $value) 
    {
        $this->$name = $value;
    }
}