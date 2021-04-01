<?php declare(strict_types=1);

namespace PhpCli\Database;

class PaginatedQuery
{
    private static \PDO $db;

    private string $className;

    private int $limit;

    private int $pages;

    private int $perPage = 0;

    private $query;

    private int $total;

    public function __construct(string $classNameOrTable = null, int $perPage = 0, \PDO $db = null)
    {
        if ($db) {
            static::setDriver($db);
        }

        if (is_subclass_of($classNameOrTable, \PhpCli\Models\Model::class)) {
            $this->className = $classNameOrTable;
            $this->query();
        } else {
            $this->query($classNameOrTable);
        }

        $this->perPage($perPage);
    }

    public static function driver(): ?\PDO
    {
        if (isset(static::$db)) {
            return static::$db;
        }
    }

    public static function setDriver(\PDO $db)
    {
        static::$db = $db;
    }

    public static function table(string $table, int $perPage = 0): PaginatedQuery
    {
        return new static($table, $perPage);
    }

    public function limit(int $perPage = 0): PaginatedQuery
    {
        $this->perPage = $limit;
        return $this;
    }

    public function pages(): int
    {
        $total = $this->total();

        if ($this->perPage) {
            $this->pages = (int) ceil($total / $this->perPage);
        } else {
            $this->pages = 1;
        }

        return $this->pages;
    }

    public function perPage(int $limit = 0): PaginatedQuery
    {
        $this->perPage = $limit;
        return $this;
    }

    private function query(string $table = null)
    {
        if (!isset($this->query)) {
            if (isset($this->className)) {
                $this->query = call_user_func($this->className.'::query');
            } else {
                if (!$table) throw new \RuntimeException('PaginatedQuery requires a Model class name or table name.');
                $this->query = Query::table($table);
            }
        }
        
        return $this->query;
    }

    public function total(): int
    {
        if (!isset($this->total)) {
            $this->total = $this->query()->count();
        }

        return $this->total;
    }

    public function get(int $page = 1)
    {
        if ($page < 1) throw new \InvalidArgumentException('Page cannot be less than 1.');

        $this->total();
        
        if ($this->perPage) {
            $this->query()->limit($this->perPage);
            if ($offset = ($page * $this->perPage) - $this->perPage) {
                $this->query()->offset($offset);
            }
        }

        return $this->query()->get();
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->query(), $name), $arguments);
    }
}