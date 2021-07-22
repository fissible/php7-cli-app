<?php declare(strict_types=1);

namespace PhpCli\Facades;

use PhpCli\Database\Driver;
use PhpCli\Database\Query;
use PhpCli\Traits\RequiresServiceContainer;

class DB {

    use RequiresServiceContainer;

    public static function instance(string $name = null)
    {
        return self::app()->instance(Driver::class);
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([Query::class, $name], $arguments);
    }
}