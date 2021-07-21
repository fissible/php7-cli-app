<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;

class Postgres extends Driver {

    protected int $port = 5432;

    public static function create(array $config): \PDO
    {
        $driver = new Postgres($config);
        $driver->requireConfigKey('host|hostaddr');
        $username = $config['user'] ?? $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $dsn = static::makeDsn([
            'host' => $config['host'] ?? null,
            'hostaddr' => $config['hostaddr'] ?? null,
            'port' => $config['port'] ?? $driver->port,
            'dbname' => $config['name'] ?? $username ?? null,
            // 'user' => $username,
            // 'password' => $password
        ]);

        return new \PDO('pgsql:'.$dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }
}