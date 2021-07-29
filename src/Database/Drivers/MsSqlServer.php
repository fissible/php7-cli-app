<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;

class MsSqlServer extends Driver {

    protected int $port = 3306;

    public static function create($Config): \PDO
    {
        $driver = new Mysql($Config);
        $driver->requireConfigKey('host|socket');
        $driver->requireConfigKey('user|username');
        $Server = $driver->Config->Server;
        $username = $driver->Config->user ?? $driver->Config->username ?? null;
        $password = $driver->Config->password ?? null;

        if (isset($driver->Config->port)) {
            $Server .= ','.$driver->Config->port;
        } elseif (isset($driver->Config->Port)) {
            $Server .= ','.$driver->Config->Port;
        }

        $dsn = static::makeDsn([
            'Server' => $Server,
            'Database' => $driver->Config->Database ?? null,
        ]);

        return new \PDO('sqlsrv: '.$dsn, $username, $password, [
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }
}