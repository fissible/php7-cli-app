<?php declare(strict_types=1);

namespace PhpCli\Facades;

use PhpCli\Reporting\Drivers\StandardLogger;
use PhpCli\Reporting\Logger;
use PhpCli\Traits\RequiresServiceContainer;

class Log {

    use RequiresServiceContainer;

    public static function fatal($data, string $name = null)
    {
        static::instance($name)->log($data, Logger::FATAL);
    }

    public static function error($data, string $name = null)
    {
        static::instance($name)->log($data, Logger::ERROR);
    }

    public static function warning($data, string $name = null)
    {
        static::instance($name)->log($data, Logger::WARNING);
    }

    public static function info($data, string $name = null)
    {
        static::instance($name)->log($data, Logger::INFO);
    }

    public static function debug($data, string $name = null)
    {
        static::instance($name)->log($data, Logger::DEBUG);
    }

    public static function instance(string $name = null)
    {
        if ($app = self::app()) {
            if ($name) {
                if ($config = $app->config()->get('logger.'.$name)) {
                    return Logger::create($config);
                }
            } else {
                return $app->make(Logger::class);
            }
        }
        return StandardLogger::create(new \stdClass);
    }
}