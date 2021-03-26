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
        
        $input_parameters = [];
        if (substr($sql, 0, 6) === 'INSERT') {
            $input_parameters = $this->insert;
        } elseif (substr($sql, 0, 6) === 'UPDATE') {
            $input_parameters = $this->update;
        }

        // WHERE IN variables
        $in_params = [];
        foreach ($this->where as $conj => $whereClauses) {
            foreach ($whereClauses as $where) {
                if (isset($where[3]) && !empty($where[3])) {
                    $in_params = array_merge($in_params, $where[3]);
                }
            }
        }

        // print_r(['sql'=>$sql, 'input_parameters'=>array_merge($input_parameters, $in_params)]);

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
                $result = $stmt->execute(array_merge($input_parameters, $in_params));
            }
        } else {
            $result = $stmt->execute(array_merge($input_parameters, $in_params));
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

    public function limit(int $limit): self
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

        $this->where['AND'][] = [$column, strtoupper($operator), $value];

        return $this;
    }

    public function whereBetween(string $column, array $values, string $conjunction = 'AND')
    {
        $this->where[strtoupper($conjunction)][] = [$column, 'BETWEEN', $values];
        return $this;
    }

    public function whereIn(string $column, array $values, string $conjunction = 'AND'): self
    {
        $this->where[strtoupper($conjunction)][] = [$column, 'IN', $values];
        return $this;
    }

    public function whereNotBetween(string $column, array $values, string $conjunction = 'AND'): self
    {
        $this->where[strtoupper($conjunction)][] = [$column, 'NOT BETWEEN', $values];
        return $this;
    }

    public function whereNotIn(string $column, array $values, string $conjunction = 'AND'): self
    {
        $this->where[strtoupper($conjunction)][] = [$column, 'NOT IN', $values];
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
            $sql .= $where;
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
            $whereClauses = [];
            foreach ($this->where['AND'] as $key => $where) {
                list($whereSql, $input_parameters) = $this->compileWhereCriteria($where, 'AND', $key);
                $this->where['AND'][$key][3] = $input_parameters;
                $whereClauses[] = $whereSql;
            }
            $sql .= implode(' AND ', $whereClauses);
        }

        if ($both) {
            $sql .= ') AND (';
        }

        if (!empty($this->where['OR'])) {
            $whereClauses = [];
            foreach ($this->where['OR'] as $key => $where) {
                list($whereSql, $input_parameters) = $this->compileWhereCriteria($where, 'AND', $key);
                $this->where['OR'][$key][3] = $input_parameters;
                $whereClauses[] = $whereSql;
            }
            $sql .= implode(' OR ', $whereClauses);
        }

        if ($both) {
            $sql .= ')';
        }

        if (!empty($sql)) {
            $sql = ' WHERE '.$sql;
        }

        return $sql;
    }

    private function compileWhereCriteria(array $where, string $conjunction, int $key)
    {
        $param_key = $key;
        $operator = $where[1];
        $value = $where[2];
        $input_parameters = [];
        if (is_array($value)) {
            if (in_array($operator, ['IN', 'NOT IN'])) {
                $in = '';
                foreach ($value as $item) {
                    $param_key++;
                    $key = ':'.$conjunction.$param_key;
                    $in .= "$key,";
                    $input_parameters[$key] = $item;
                }
                $value = '('.rtrim($in, ',').')';
            } elseif($operator === 'BETWEEN') {
                $value = array_values($value);
                $keyFrom = $conjunction.(++$param_key);
                $keyTo = $conjunction.(++$param_key);
                $input_parameters[':'.$keyFrom] = $value[0];
                $input_parameters[':'.$keyTo] = $value[1];
                $value = sprintf(':%s AND :%s', $keyFrom, $keyTo);
            } else {
                throw new \InvalidArgumentException(sprintf('Invalid WHERE criteria value "%s', gettype($value)));
            }
        } else {
            $value = is_numeric($value) ? $value : '\''.$value.'\'';
        }
        
        return [sprintf('%s %s %s', $where[0], $where[1], $value), $input_parameters];
    }
}