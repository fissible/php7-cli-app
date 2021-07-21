<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;

class MsSqlServer extends Driver {

    protected int $port = 3306;

    public static function create(array $config): \PDO
    {
        $driver = new Mysql($config);
        $driver->requireConfigKey('host|socket');
        $driver->requireConfigKey('user|username');
        $Server = $config['Server'];
        $username = $config['user'] ?? $config['username'] ?? null;
        $password = $config['password'] ?? null;

        if (isset($config['port'])) {
            $Server .= ','.$config['port'];
        } elseif (isset($config['Port'])) {
            $Server .= ','.$config['Port'];
        }

        $dsn = static::makeDsn([
            'Server' => $Server,
            'Database' => $config['Database'] ?? null,
        ]);

        return new \PDO('sqlsrv: '.$dsn, $username, $password, [
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }
}