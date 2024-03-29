<?php declare(strict_types=1);

namespace PhpCli\Database\Drivers;

use PhpCli\Exceptions\ConfigurationException;
use PhpCli\Filesystem\File;

class Sqlite extends Driver {

    public static function create($Config): \PDO
    {
        $driver = new Sqlite($Config);
        $driver->requireConfigKey('path');

        $DbFile = new File($driver->Config->path);
        $info = $DbFile->info();

        if ($info['dirname'] === '.') {
            $DbFile = new File(rtrim($driver->Config->path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database.sqlite3');
        } elseif (!isset($info['extension'])) {
            $DbFile = new File($driver->Config->path.'.sqlite');
        }

        return new \PDO(sprintf('sqlite:%s', $DbFile->getPath()), '', '', [
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
    }
}