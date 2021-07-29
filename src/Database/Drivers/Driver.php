<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Filesystem\File;
use PhpCli\Traits\HasConfig;

class Driver
{
    use HasConfig;

    protected int $port = 0;

    public function __construct($Config)
    {
        $this->setConfig($Config);
    }

    /**
     * @param mixed $Config
     * @return Driver
     */
    public static function create($Config): \PDO
    {
        $driver = new Driver($Config);
        $driver->requireConfigKey('driver');

        switch ($driver->Config->driver) {
            case 'mysql':
                return Mysql::create($Config);
            break;
            case 'pgsql':
            case 'postgres':
                return Postgres::create($Config);
            break;
            case 'sqlite':
            case 'sqlite3':
                return Sqlite::create($Config);
            break;
            case 'sqlsrv':
                return MsSqlServer::create($Config);
            break;
        }

        $username = $driver->Config->user ?? $driver->Config->username ?? null;
        $password = $driver->Config->password ?? null;
        $dsn = static::makeDsn([
            'host'     => $driver->Config->host ?? null,
            'port'     => $driver->Config->port ?? $driver->port,
            'Server'   => $driver->Config->Server ?? null,
            'Database' => $driver->Config->Database ?? null,
            'user'     => $driver->Config->user ?? null,
            'username' => $driver->Config->username ?? null,
            'password' => $password
        ]);

        return new \PDO($driver->Config->driver.':'.$dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
    }

    /**
     * @param array $config
     * @return string
     */
    protected static function makeDsn(array $config): string
    {
        return http_build_query(array_filter($config), '', ';');
    }
}