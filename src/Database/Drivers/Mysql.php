<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;

class Mysql extends Driver {

    protected int $port = 3306;

    public static function create($Config): \PDO
    {
        $driver = new Mysql($Config);
        $driver->requireConfigKey('host|socket');
        $driver->requireConfigKey('user|username');
        $username = $driver->Config->user ?? $driver->Config->username ?? null;
        $password = $driver->Config->password ?? null;
        $dsn = static::makeDsn([
            'host' => $driver->Config->host ?? null,
            'unix_socket' => $driver->Config->socket ?? null,
            'port' => $driver->Config->port ?? $driver->port,
            'dbname' => $driver->Config->name ?? null,
            'password' => $password,
            'charset' => $driver->Config->charset ?? null
        ]);

        return new \PDO('mysql: '.$dsn, $username, $password, [
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }
}