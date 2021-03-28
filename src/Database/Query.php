<?php declare(strict_types=1);

namespace PhpCli\Database;

use PhpCli\Collection;
use PhpCli\Exceptions\QueryException;

class Query {

    private static \PDO $db;

    protected string $table;

    protected array $insert;

    protected array $join = [];

    protected int $limit;

    protected int $offset;

    protected array $order;

    protected array $select = ['*'];

    protected string $type;

    protected array $update;

    protected string $updateField;

    protected array $where = [];

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
        $this->type = 'COUNT';
        $statement = $this->exe($this->compileQuery());
        if (!$statement) {
            $error = static::$db->errorInfo();
            throw new QueryException($error[2], $error[0], $error[1]);
        }
        $result = $statement->fetchColumn();

        return (int) $result ?? 0;
    }

    public function delete(): bool
    {
        $this->type = 'DELETE';
        return $this->exe($this->compileQuery());
    }

    public static function transaction(callable $callback)
    {
        try {
            static::$db->beginTransaction();
            return $callback();
            static::$db->commit();
        } catch (\Throwable $e) {
            static::$db->rollBack();
            throw $e;
        }
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
        $where_params = $this->getWhereParameters();

        // print_r(['sql'=>$sql, 'input_parameters'=>array_merge($input_parameters, $where_params)]);

        
        if ($this->isMultiInsert()) {
            try {
                static::$db->beginTransaction();
                foreach ($this->insert as $input_parameters) {
                    $parameters = array_merge($input_parameters, $where_params);
                    $stmt = $this->bindParameters($this->prepareStatement($sql), $parameters);
                    $result = $stmt->execute();
                }
                static::$db->commit();
            } catch (\Throwable $e) {
                static::$db->rollBack();
                throw $e;
            }
        } else {
            $parameters = array_merge($input_parameters, $where_params);
            $stmt = $this->bindParameters($this->prepareStatement($sql), $parameters);
            $result = $stmt->execute();
        }
        

        if (substr($sql, 0, 6) === 'SELECT') {
            return $stmt;
        }

        if (substr($sql, 0, 6) === 'INSERT' && !$this->isMultiInsert()) {
            return static::$db->lastInsertId();
        }

        return $result;
    }

    private function prepareStatement(string $sql): \PDOStatement
    {
        $stmt = static::$db->prepare($sql);
        if (!$stmt) {
            $error = static::$db->errorInfo();
            throw new QueryException($error[2], $error[0], $error[1]);
        }
        return $stmt;
    }

    private function bindParameters(\PDOStatement $stmt, array $input_parameters)
    {
        foreach ($input_parameters as $key => $value) {
            if (is_int($value)) $param = \PDO::PARAM_INT;
            elseif (is_bool($value)) $param = \PDO::PARAM_BOOL;
            elseif (is_null($value)) $param = \PDO::PARAM_NULL;
            elseif (is_string($value)) $param = \PDO::PARAM_STR;
            else $param = FALSE;
                
            if ($param) $stmt->bindValue($key, $value, $param);
        }
        return $stmt;
    }

    public function first()
    {
        $this->type = 'SELECT';
        $statement = $this->exe($this->compileQuery());
        if (!$statement) {
            $error = static::$db->errorInfo();
            throw new QueryException($error[2], $error[0], $error[1]);
        }
        $result = $statement->fetch(\PDO::FETCH_OBJ);
        if (!$result) {
            $result = null;
        }
        return $result;
    }

    public function get()
    {
        $this->type = 'SELECT';
        $statement = $this->exe($this->compileQuery());
        if (!$statement) {
            $error = static::$db->errorInfo();
            throw new QueryException($error[2], $error[0], $error[1]);
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
        $this->type = 'INSERT';
        return $this->exe($this->compileQuery());
    }

    public function innerJoin(string $table, string $localKey, string $foreignKey)
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'INNER');
    }

    public function join(string $table, string $localKey, string $foreignKey, $type = 'INNER'): self
    {
        $this->join[] = [$type, $table, $localKey, $foreignKey];
        return $this;
    }

    public function leftJoin(string $table, string $localKey, string $foreignKey)
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'LEFT');
    }

    public function outerJoin(string $table, string $localKey, string $foreignKey)
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'OUTER');
    }

    public function rightJoin(string $table, string $localKey, string $foreignKey)
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'RIGHT');
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
        $this->type = 'UPDATE';
        return $this->exe($this->compileQuery());
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

    public function orderBy(string $field, string $dir = 'ASC')
    {
        if (!isset($this->order)) {
            $this->order = [];
        }
        $this->order[$field] = strtoupper($dir);
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
        if (count($args) === 1) {
            if (!is_callable($args[0])) throw new \InvalidArgumentException('Single parameter must be a callable.');
            
            $this->where[] = ['OR', $args[0], null, null];
        } else {
            $column = $args[0];
            $operator = count($args) > 2 ? strtoupper($args[1]) : '=';
            $value = count($args) > 2 ? $args[2] : $args[1];

            if ($value === null) {
                if (!in_array($operator, ['IS', 'NOT'])) {
                    if ($operator !== '=') {
                        $operator = 'NOT';
                    } else {
                        $operator = 'IS';
                    }
                }
            }

            $this->where[] = ['OR', $column, $operator, $value];
        }
        

        return $this;
    }

    public function orWhereIn(string $column, array $values): self
    {
        $this->where[] = ['OR', $column, 'IN', $values];
        return $this;
    }

    public function orWhereNotIn(string $column, array $values): self
    {
        $this->where[] = ['OR', $column, 'NOT IN', $values];
        return $this;
    }

    public function where(): self
    {
        $args = func_get_args();
        if (count($args) === 1) {
            if (!is_callable($args[0])) throw new \InvalidArgumentException('Single parameter must be a callable.');
            
            $this->where[] = ['AND', $args[0], null, null];
        } else {
            $column = $args[0];
            $operator = count($args) > 2 ? strtoupper($args[1]) : '=';
            $value = count($args) > 2 ? $args[2] : $args[1];

            if ($value === null) {
                if (!in_array($operator, ['IS', 'NOT'])) {
                    if ($operator !== '=') {
                        $operator = 'NOT';
                    } else {
                        $operator = 'IS';
                    }
                }
            }

            $this->where[] = ['AND', $column, strtoupper($operator), $value];
        }

        return $this;
    }

    public function whereBetween(string $column, array $values, string $conjunction = 'AND')
    {
        $this->where[] = [strtoupper($conjunction), $column, 'BETWEEN', $values];
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->where[] = ['AND', $column, 'IN', $values];
        return $this;
    }

    public function whereNotBetween(string $column, array $values, string $conjunction = 'AND'): self
    {
        $this->where[] = [strtoupper($conjunction), $column, 'NOT BETWEEN', $values];
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->where[] = ['AND', $column, 'NOT IN', $values];
        return $this;
    }

    private function isMultiInsert(): bool
    {
        if (isset($this->insert)) {
            return isset($this->insert[0]) && is_array($this->insert[0]);
        }
        return false;
    }

    private function compileJoin(array $join)
    {
        return sprintf(
            '%s JOIN %s ON %s = %s',
            $join[0], $join[1], $join[2], $join[3]
        );
    }

    public function compileQuery(string $type = null): string
    {
        $input_parameters = null;
        $type = $type ?? $this->type;
        switch ($type) {
            case 'COUNT':
                if (count($this->select) === 1 && $this->select[0] === '*') {
                    $sql = sprintf("SELECT COUNT(*) FROM `%s`", $this->table);
                } else {
                    $sql = sprintf("SELECT  COUNT(*), %s FROM `%s`", implode(', ', $this->select), $this->table);
                }
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

        if (!empty($this->join)) {
            foreach ($this->join as $join) {
                $sql .= ' '.$this->compileJoin($join);
            }
        }

        if ($where = $this->compileWhere()) {
            $sql .= ' WHERE '.$where;
        }

        if (isset($this->order)) {
            $sql .= ' ORDER BY ';
            $orderBys = [];
            foreach ($this->order as $key => $dir) {
                if ($key[0] === '`' && $key[-1] === '`' && false !== strpos($key, '.') && substr_count($key, '`') === 2) {
                    $key = str_replace('.', '`.`', $key);
                }
                $orderBys[] = $key.($dir === 'DESC' ? ' DESC' : ' ASC');
            }
            $sql .= implode(', ', $orderBys);
        }

        if (isset($this->limit)) {
            $sql .= sprintf(' LIMIT %d', $this->limit);
        }

        if (isset($this->offset)) {
            $sql .= sprintf(' OFFSET %d', $this->offset);
        }

        return $sql;
    }

    private function compileWhere(bool $enclose = false, int $nested_key = 0): string
    {
        $sql = '';

        if (!empty($this->where)) {
            if ($enclose) {
                $sql .= '(';
            }

            foreach ($this->where as $key => $where) {
                if ($key > 0) $sql .= ' '.$where[0].' ';
                list($whereSql, $input_parameters) = $this->compileWhereCriteria($where, $key + $nested_key);
                $this->where[$key][4] = $input_parameters;
                $sql .= $whereSql;
            }

            if ($enclose) {
                $sql .= ')';
            }
        }

        return $sql;
    }

    private function compileWhereCriteria($where, int $nested_key)
    {
        $conjunction = $where[0];

        if (is_callable($where[1])) {
            $query = new static(static::$db);
            $where[1]($query);
            $sql = $query->compileWhere(true, $nested_key);
            
            return [$sql, $query->getWhereParameters()];
        }

        $param_key = $nested_key;
        $operator = $where[2];
        $value = $where[3];
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
            $param_key++;
            $key = ':'.$conjunction.$param_key;
            $input_parameters[$key] = $value;
            $value = $key;
        }

        $column = $where[1];
        if ($column[0] === '`' && $column[-1] === '`' && false !== strpos($column, '.') && substr_count($column, '`') === 2) {
            $column = str_replace('.', '`.`', $column);
        }
        
        return [sprintf('%s %s %s', $column, $operator, $value), $input_parameters];
    }

    /**
     * @return array
     */
    private function getWhereParameters()
    {
        $parameters = [];
        foreach ($this->where as $where) {
            if (isset($where[4]) && !empty($where[4])) {
                $parameters = array_merge($parameters, $where[4]);
            }
        }
        return $parameters;
    }
}