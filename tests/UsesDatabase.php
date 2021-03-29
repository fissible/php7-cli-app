<?php declare(strict_types=1);

namespace Tests;

use PhpCli\Database\Query;
use PhpCli\Filesystem\File;

trait UsesDatabase
{
    public $database;

    public $db;

    protected function getDatabaseFile()
    {
        return new File(sprintf('%s/database.sqlite3', __DIR__));
    }

    protected function setUpDatabase(): \PDO
    {
        $db = new \PDO(sprintf('sqlite:%s', $this->getDatabaseFile()->getPath()), '', '', array(
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ));

        Query::setDriver($db);

        return $db;
    }

    protected function tearDownDatabase(): void
    {
        $File = $this->getDatabaseFile();
        if ($File->exists()) {
            $File->delete();
        }
    }
}