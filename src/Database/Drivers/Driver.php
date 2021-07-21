<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;
use PhpCli\Traits\HasConfig;

class Driver
{
    use HasConfig;

    protected int $port = 0;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param array $config
     * @return Driver
     */
    public static function create(array $config): \PDO
    {
        $driver = new Driver($config);
        $driver->requireConfigKey('driver');

        switch ($driver->config['driver']) {
            case 'mysql':
                return Mysql::create($config);
            break;
            case 'pgsql':
            case 'postgres':
                return Postgres::create($config);
            break;
            case 'sqlite':
            case 'sqlite3':
                return Sqlite::create($config);
            break;
            case 'sqlsrv':
                return MsSqlServer::create($config);
            break;
        }

        $username = $config['user'] ?? $config['username'] ?? null;
        $password = $config['password'] ?? null;
        $dsn = static::makeDsn([
            'host'     => $config['host'] ?? null,
            'port'     => $config['port'] ?? $driver->port,
            'Server'   => $config['Server'] ?? null,
            'Database' => $config['Database'] ?? null,
            'user'     => $config['user'] ?? null,
            'username' => $config['username'] ?? null,
            'password' => $password
        ]);

        return new \PDO($driver->config['driver'].':'.$dsn, $username, $password, [
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