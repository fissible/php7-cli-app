<?php declare(strict_types=1);

namespace PhpCli\Traits;

use PhpCli\Application;

trait RequiresServiceContainer
{
    protected static Application $app;

    public static function app(Application $app = null)
    {
        if (isset($app)) {
            static::$app = $app;
        }
        return self::$app;
    }
}