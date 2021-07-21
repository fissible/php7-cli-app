<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;

class Mysql extends Driver {

    protected int $port = 3306;

    public static function create(array $config): \PDO
    {
        $driver = new Mysql($config);
        $driver->requireConfigKey('host|socket');
        $driver->requireConfigKey('user|username');
        $username = $config['user'] ?? $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $dsn = static::makeDsn([
            'host' => $config['host'] ?? null,
            'unix_socket' => $config['socket'] ?? null,
            'port' => $config['port'] ?? $driver->port,
            'dbname' => $config['name'] ?? null,
            'password' => $password,
            'charset' => $config['charset'] ?? null
        ]);

        return new \PDO('mysql: '.$dsn, $username, $password, [
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }
}