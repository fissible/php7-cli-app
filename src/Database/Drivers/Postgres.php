<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;

class Postgres extends Driver {

    protected int $port = 5432;

    public static function create($Config): \PDO
    {
        $driver = new Postgres($Config);
        $driver->requireConfigKey('host|hostaddr');
        $username = $driver->Config->user ?? $driver->Config->username ?? null;
        $password = $driver->Config->password ?? null;
        $dsn = static::makeDsn([
            'host' => $driver->Config->host ?? null,
            'hostaddr' => $driver->Config->hostaddr ?? null,
            'port' => $driver->Config->port ?? $driver->port,
            'dbname' => $driver->Config->name ?? $username ?? null,
            // 'user' => $username,
            // 'password' => $password
        ]);

        return new \PDO('pgsql:'.$dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }
}