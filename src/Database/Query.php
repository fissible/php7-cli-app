<?php declare(strict_types=1);

namespace PhpCli\Database;

use PhpCli\Collection;

class Query {

    private static \PDO $db;

    protected string $table;

    protected array $insert;

    protected int $limit;

    protected int $offset;

    protected array $order;

    protected array $select = ['*'];

    protected string $type;

    protected array $update;

    protected string $updateField;

    protected array $where = [
        'AND' => [],
        'OR' => []
    ];

    public function __construct(\PDO $db = null)
    {
        if ($db) {
            static::setDriver($db);
        }
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

    public static function table(string $table): Query
    {
        return (new static())->setTable($table);
    }

    public function addSelect(): self
    {
        foreach (func_get_args() as $field) {
            $this->select[] = $field;
        }
        return $this;
    }

    public function count(): int
    {
        return $this->exe($this->compileQuery('COUNT'));
    }

    public function delete(): bool
    {
        return $this->exe($this->compileQuery('DELETE'));
    }

    public function exe(string $sql)
    {
        if (!isset(static::$db)) {
            throw new \RuntimeException('No PDO driver available.');
        }
        
        $input_parameters = null;
        if (substr($sql, 0, 6) === 'INSERT') {
            $input_parameters = $this->insert;
        } elseif (substr($sql, 0, 6) === 'UPDATE') {
            $input_parameters = $this->update;
        }

        // print_r(compact('sql', 'input_parameters'));

        $stmt = static::$db->prepare($sql);
        if (!$stmt) {
            list($SQLSTATE_error_code, $driver_error_code, $error_message) = static::$db->errorInfo();
            throw new \Exception(sprintf(
                'SQLSTATE ERROR %s: %s - %s',
                $SQLSTATE_error_code,
                $driver_error_code,
                $error_message
            ));
        }

        static::$db->beginTransaction();

        if ($this->isMultiInsert()) {
            foreach ($this->insert as $input_parameters) {
                $result = $stmt->execute($input_parameters);
            }
        } else {
            $result = $stmt->execute($input_parameters);
        }

        static::$db->commit();

        if (substr($sql, 0, 6) === 'SELECT') {
            return $stmt;
        }

        if (substr($sql, 0, 6) === 'INSERT' && !$this->isMultiInsert()) {
            return static::$db->lastInsertId();
        }

        return $result;
    }

    public function first()
    {
        $statement = $this->exe($this->compileQuery('SELECT'));
        if (!$statement) {
            list($SQLSTATE_error_code, $driver_error_code, $error_message) = static::$db->errorInfo();
            throw new \Exception(sprintf(
                'SQLSTATE ERROR %s: %s - %s',
                $SQLSTATE_error_code,
                $driver_error_code,
                $error_message
            ));
        }
        $result = $statement->fetch(\PDO::FETCH_OBJ);
        if (!$result) {
            $result = null;
        }
        return $result;
    }

    public function get()
    {
        $statement = $this->exe($this->compileQuery('SELECT'));
        if (!$statement) {
            list($SQLSTATE_error_code, $driver_error_code, $error_message) = static::$db->errorInfo();
            throw new \Exception(sprintf(
                'SQLSTATE ERROR %s: %s - %s',
                $SQLSTATE_error_code,
                $driver_error_code,
                $error_message
            ));
        }
        $result = $statement->fetchAll(\PDO::FETCH_OBJ);
        if (!$result) {
            $result = null;
        }
        return new Collection($result);
    }

    /**
     * @param array $data
     * @return bool|string
     */
    public function insert(array $data)
    {
        $this->insert = $data;
        return $this->exe($this->compileQuery('INSERT'));
    }

    /**
     * @param array $data
     * @param string|null $updateField
     * @return bool
     */
    public function update(array $data, ?string $updateField = null): bool
    {
        $this->update = $data;
        $this->updateField = $updateField;
        return $this->exe($this->compileQuery('UPDATE'));
    }

    public function value(string $column)
    {
        $first = $this->first();
        return property_exists($first, $column) ? $first->$column : null;
    }

    public function limit (int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function select(): self
    {
        $this->type = 'SELECT';
        $this->select = func_get_args();
        return $this;
    }

    public function setTable(string $table)
    {
        $this->table = $table;
        return $this;
    }

    public function orWhere(): self
    {
        $args = func_get_args();
        $column = $args[0];
        $operator = count($args) > 2 ? $args[1] : '=';
        $value = count($args) > 2 ? $args[2] : $args[1];

        $this->where['OR'][] = [$column, $operator, $value];

        return $this;
    }

    public function where(): self
    {
        $args = func_get_args();
        $column = $args[0];
        $operator = count($args) > 2 ? $args[1] : '=';
        $value = count($args) > 2 ? $args[2] : $args[1];

        $this->where['AND'][] = [$column, $operator, $value];

        return $this;
    }

    private function isMultiInsert(): bool
    {
        if (isset($this->insert)) {
            return isset($this->insert[0]) && is_array($this->insert[0]);
        }
        return false;
    }

    private function compileQuery(string $type = null): string
    {
        $input_parameters = null;
        $type = $type ?? $this->type;
        switch ($type) {
            case 'COUNT':
                $sql = sprintf("SELECT COUNT(*) FROM `%s`", $this->table);
            break;
            case 'DELETE':
                $sql = sprintf("DELETE FROM `%s`", $this->table);
            break;
            case 'INSERT':
                $input_parameters = $this->insert;
                $sql = sprintf("INSERT INTO `%s` (", $this->table);
                if ($this->isMultiInsert()) {
                    foreach ($this->insert[0] as $key => $val) {
                        $sql .= sprintf(" `%s`,", $key);
                    }
                } else {
                    foreach ($this->insert as $key => $val) {
                        $sql .= sprintf(" `%s`,", $key);
                    }
                }
                
                $sql = ltrim(rtrim($sql, ','));
                $sql .= ') VALUES ';

                $sql .= '(';
                if ($this->isMultiInsert()) {
                    foreach ($this->insert[0] as $key => $val) {
                        $sql .= sprintf(" :%s,", $key);
                    }
                } else {
                    foreach ($this->insert as $key => $val) {
                        $sql .= sprintf(" :%s,", $key);
                    }
                }
                $sql = ltrim(rtrim($sql, ','));
                $sql .= ')';
            break;
            case 'SELECT':
                $sql = sprintf("SELECT %s FROM `%s`", implode(', ', $this->select), $this->table);
            break;
            case 'UPDATE':
                $input_parameters = $this->update;
                $sql = sprintf("UPDATE %s SET", $this->table);
                foreach ($this->update as $key => $val) {
                    $sql .= sprintf(" `%s` = :%s,", $key, $key);
                }
                if ($this->updateField) {
                    $sql .= ' `'.$this->updateField.'` = CURRENT_TIMESTAMP';
                } else {
                    $sql .= rtrim($sql, ',');
                }
            break;
        }

        // join

        if ($where = $this->compileWhere()) {
            $sql .= ' '.$where;
        }

        if (isset($this->limit)) {
            $sql .= sprintf(' LIMIT %d', $this->limit);
        }

        if (isset($this->offset)) {
            $sql .= sprintf(' OFFSET %d', $this->offset);
        }

        return $sql;
    }

    private function compileWhere(): string
    {
        $sql = '';
        $both = !empty($this->where['AND']) && !empty($this->where['OR']);

        if ($both) {
            $sql .= '(';
        }

        if (!empty($this->where['AND'])) {
            $sql .= implode(' AND ', array_map(function ($whereClause) {
                $value = is_numeric($whereClause[2]) ? $whereClause[2] : '\''.$whereClause[2].'\'';
                return sprintf('%s %s %s', $whereClause[0], $whereClause[1], $value);
            }, $this->where['AND']));
        }

        if ($both) {
            $sql .= ') AND (';
        }

        if (!empty($this->where['OR'])) {
            $sql .= implode(' OR ', array_map(function ($whereClause) {
                $value = is_numeric($whereClause[2]) ? $whereClause[2] : '\''.$whereClause[2].'\'';
                return sprintf('%s %s %s', $whereClause[0], $whereClause[1], $value);
            }, $this->where['OR']));
        }

        if ($both) {
            $sql .= ')';
        }

        if (!empty($sql)) {
            $sql = ' WHERE '.$sql;
        }

        return $sql;
    }
}