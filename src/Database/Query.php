<?php declare(strict_types=1);

namespace PhpCli\Database;

use PhpCli\Collection;
use PhpCli\Database\Grammar\Join;
use PhpCli\Exceptions\QueryException;
use PhpCli\Traits\Database\Where;

class Query {

    use Where;

    private static \PDO $db;

    private static QueryException $lastError;

    protected string $table;

    protected array $tables;

    protected string $alias;

    protected array $group;

    protected array $having = [];

    protected array $insert;

    protected Collection $join;

    protected int $limit;

    protected int $offset;

    protected array $order;

    protected array $select = ['*'];

    protected string $type = 'SELECT';

    protected array $update;

    protected ?string $createdField;

    protected ?string $updatedField;

    public function __construct(\PDO $db = null, Query $parent = null)
    {
        if ($db) {
            static::setDriver($db);
        }
        if ($parent) {
            $this->setParent($parent);
        }
    }

    public static function driver(): ?\PDO
    {
        if (isset(static::$db)) {
            return static::$db;
        }
    }

    public static function raw($value): \stdClass
    {
        $raw = new \stdClass();
        $raw->value = $value;
        return $raw;
    }

    public static function setDriver(\PDO $db)
    {
        static::$db = $db;
    }

    public static function table(string $table): Query
    {
        return (new static())->setTable($table);
    }

    public static function transaction(callable $callback)
    {
        $return = null;
        try {
            static::$db->beginTransaction();
            $return = $callback();
            static::$db->commit();
        } catch (\Throwable $e) {
            static::$db->rollBack();
            throw $e;
        }
        return $return;
    }

    public function addSelect(): self
    {
        foreach (func_get_args() as $field) {
            $this->select[] = $field;
        }
        return $this;
    }

    public function as(string $alias): self
    {
        if (isset($this->join) && !$this->join->empty()) {
            $this->join->last()->as($alias);
        } else {
            $this->alias = $alias;
        }
        return $this;
    }

    public function count(): int
    {
        $this->type = 'COUNT';
        $statement = $this->exe($this->compileQuery());
        if (!$statement) {
            $error = static::$db->errorInfo();
            static::$lastError = new QueryException($error[2], $error[0], $error[1]);
            throw static::$lastError;
        }
        $result = $statement->fetchColumn();

        return (int) $result ?? 0;
    }

    public function delete(): bool
    {
        $this->type = 'DELETE';
        return $this->exe($this->compileQuery());
    }

    /**
     * return:
     *  \PDOStatement
     *  bool
     *  int
     */
    public function exe(string $sql)
    {
        if (!isset(static::$db)) {
            throw new \RuntimeException('No PDO driver available.');
        }

        if (isset($this->parent)) {
            throw new \RuntimeException('Subquery cannot be executed independently.');
        }

        if ($this->isMultiInsert()) {
            $count = 0;
            $join_params = $this->getJoinParams();
            $where_params = $this->getWhereParameters();
            $having_params = $this->getHavingParameters();
            $inTransaction = (bool) static::$db->inTransaction();
            if (!$inTransaction) static::$db->beginTransaction();
            try {
                foreach ($this->insert as $key => $input_parameters) {
                    foreach ($input_parameters as $_key => $value) {
                        $newKey = sprintf(":%d%s", $key, $_key);
                        $this->insert[$key][$newKey] = $value;
                        unset($this->insert[$key][$_key]);
                    };
                }

                $stmt = $this->bindParameters($this->prepareStatement($sql), $this->getParams());
                if ($stmt->execute()) {
                    $count = $stmt->rowCount();
                }
                if (!$inTransaction) static::$db->commit();
            } catch (\Throwable $e) {
                if (!$inTransaction) static::$db->rollBack();
                throw $e;
            }
            return $count;
        } else {
            $stmt = $this->bindParameters($this->prepareStatement($sql), $this->getParams());
            $result = $stmt->execute();
        }

        if (substr($sql, 0, 6) === 'SELECT') {
            return $stmt;
        }

        if (substr($sql, 0, 6) === 'INSERT' && !$this->isMultiInsert()) {
            return static::insertId();
        }

        return $result;
    }

    public function from(): self
    {
        $this->tables = func_get_args();
        return $this;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        $input_parameters = [];
        $join_params = $this->getJoinParams();
        $where_params = $this->getWhereParameters();
        $having_params = $this->getHavingParameters();

        if (isset($this->insert)) {
            if ($this->isMultiInsert()) {
                $input_parameters = array_merge(...$this->insert);
            } else {
                $input_parameters = $this->insert;
            }
        } elseif (isset($this->update)) {
            $input_parameters = $this->update;
        }

        return array_merge($input_parameters, $join_params, $where_params, $having_params);
    }

    /**
     * Get the query SQL with parameters substituted for placeholders. Not intended for subsequent execution.
     * 
     * @return string
     */
    public function getSql(): string
    {
        $sql = $this->compileQuery();
        $params = $this->getParams();

        foreach ($params as $key => $value) {
            $sql = str_replace($key, $value, $sql);
        }
        return $sql;
    }

    private function prepareStatement(string $sql): \PDOStatement
    {
        try {
            $stmt = static::$db->prepare($sql);
        } catch (\PDOException $e) {
            throw $e;
        }

        if (!$stmt) {
            $error = static::$db->errorInfo();
            static::$lastError = new QueryException($error[2], $error[0], $error[1]);
            throw static::$lastError;
        }
        return $stmt;
    }

    private function bindParameters(\PDOStatement $stmt, array $input_parameters): \PDOStatement
    {
        foreach ($input_parameters as $key => $value) {
            $param = \PDO::PARAM_STR;
            if (is_int($value)) $param = \PDO::PARAM_INT;
            elseif (is_bool($value)) $param = \PDO::PARAM_BOOL;
            elseif (is_null($value)) $param = \PDO::PARAM_NULL;
                
            if ($param !== false) $stmt->bindValue($key, $value, $param);
        }
        return $stmt;
    }

    public function first()
    {
        $this->type = 'SELECT';
        $statement = $this->exe($this->compileQuery());
        if (!$statement) {
            $error = static::$db->errorInfo();
            static::$lastError = new QueryException($error[2], $error[0], $error[1]);
            throw static::$lastError;
        }
        $result = $statement->fetch(\PDO::FETCH_OBJ);
        if (!$result) {
            $result = null;
        }
        return $result;
    }

    public function get(): Collection
    {
        $this->type = 'SELECT';
        $statement = $this->exe($this->compileQuery());
        if (!$statement) {
            $error = static::$db->errorInfo();
            static::$lastError = new QueryException($error[2], $error[0], $error[1]);
            throw static::$lastError;
        }
        $result = $statement->fetchAll(\PDO::FETCH_OBJ);
        if (!$result) {
            $result = null;
        }
        return new Collection($result);
    }

    /**
     * @param string $field
     */
    public function groupBy(): self
    {
        $args = func_get_args();
        if (!isset($this->group)) {
            $this->group = [];
        }
        $this->group = array_merge($this->group, $args);
        return $this;
    }

    /**
     * Add a HAVING criteria.
     */
    public function having(): self
    {
        $args = func_get_args();
        $operator = null;
        $value = null;

        if (count($args) === 1) {
            throw new \InvalidArgumentException('Method requires at least two parameters.');
        }

        if (count($args) > 1) {
            $operator = count($args) > 2 ? strtoupper($args[1]) : '=';
            $value = count($args) > 2 ? $args[2] : $args[1];

            // `column` IS NULL || `column` IS NOT NULL
            if ($value === null && !in_array($operator, ['IS', 'IS NOT'])) {
                if ($operator === '=') {
                    $operator = 'IS';
                } elseif ($operator === '!=' || $operator === '<>') {
                    $operator = 'IS NOT';
                } else {
                    throw new \InvalidArgumentException(sprintf('Invalid operator "%s" for NULL value.', $operator));
                }
            }
        }

        return $this->addHaving($args[0], $operator, $value);
    }

    /**
     * @param array $data
     * @param string|null $createdField
     * @return bool|string
     */
    public function insert(array $data, ?string $createdField = null)
    {
        $this->insert = $data;
        $this->createdField = $createdField;
        $this->type = 'INSERT';
        return $this->exe($this->compileQuery());
    }

    public static function getLastError(): ?QueryException
    {
        if (isset(static::$lastError)) {
            return static::$lastError;
        }
        return null;
    }

    public static function insertId()
    {
        return static::$db->lastInsertId();
    }

    /**
     * Set a JOIN clause:
     *  ->join($table, $localKey[, $operator], $foreignKey[, $type])
     *  ->join($table, $onArray[, $type])
     */
    public function join(): self
    {
        $args = func_get_args();
        $type = Join::TYPE_INNER;
        $tableOrSubquery = array_shift($args);

        if ($tableOrSubquery instanceof Query) {
            $tableOrSubquery->setParent($this);
        }

        // Scan for a join type
        end($args);
        if (in_array(current($args), Join::validTypes())) {
            $type = array_pop($args);
        }
        reset($args);

        $join = new Join($this, $type, $tableOrSubquery);

        // Parse out the ON condition
        if (!in_array($type, [Join::TYPE_NATURAL, Join::TYPE_CROSS])) {
            $where = [];
            foreach ($args as $arg) {
                if (is_string($arg)) {
                    $where[] = $arg;
                } elseif (is_array($arg)) {
                    $join->on($arg);
                }
            }
            if (count($where) > 0) {
                if (count($where) === 2 || count($where) === 3) {
                    $join->on(...$where);
                } else {
                    throw new \InvalidArgumentException('Query join statement must provide a foreign and local key plus an optional operator.');
                }
            }
        }

        if (!isset($this->join)) {
            $this->join = new Collection();
        }

        $this->join->push($join);
        return $this;
    }

    public function crossJoin(string $table): self
    {
        return $this->join($table, Join::TYPE_CROSS);
    }

    public function innerJoin(): self
    {
        $args = func_get_args();
        $args[] = Join::TYPE_INNER;
        return $this->join(...$args);
    }

    public function leftJoin(): self
    {
        $args = func_get_args();
        $args[] = Join::TYPE_LEFT;
        return $this->join(...$args);
    }

    public function naturalJoin(string $table): self
    {
        return $this->join($table, Join::TYPE_NATURAL);
    }

    /**
     * @param array $data
     * @param string|null $updatedField
     * @return bool
     */
    public function update(array $data, ?string $updatedField = null): bool
    {
        $this->update = $data;
        $this->updatedField = $updatedField;
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

    public function orderBy(): self
    {
        if (!isset($this->order)) {
            $this->order = [];
        }
        $args = func_get_args();
        if (is_string($args[0])) {
            $this->order[$args[0]] = isset($args[1]) ? strtoupper($args[1]) : 'ASC';
        } elseif (is_array($args[0])) {
            $this->order = array_merge($this->order, $args[0]);
        }
        
        return $this;
    }

    /**
     * @param Query $query
     * @return self
     */
    private function setParent(Query $query): self
    {
        $this->parent = $query;
        return $this;
    }

    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    private function addHaving(string $column, $operator = null, $value = null): self
    {
        $this->having[] = [$column, $operator, $value];
        return $this;
    }

    private function internalSelect(): self
    {
        $this->type = 'SELECT';
        $this->select = func_get_args();
        return $this;
    }

    private function isMultiInsert($input_parameters = null): bool
    {
        $input_parameters ??= (isset($this->insert) ? $this->insert : null);
        if ($input_parameters) {
            return isset($input_parameters[0]) && is_array($input_parameters[0]);
        }
        return false;
    }

    /**
     * Compile the HAVING clause.
     * @return string
     */
    private function compileHaving(): string
    {
        $sql = '';

        if (!empty($this->having)) {
            $param_key = 0;
            foreach ($this->having as $key => $having) {
                if (is_object($having[0]) && $having[0] instanceof \Closure) {
                    throw new \InvalidArgumentException('HAVING cannot be compile from a callable.');
                }
                if ($key > 0) $sql .= ', ';
                $param_key + $key;
                array_unshift($having, 'HAVING');
                list($havingSql, $input_parameters) = $this->compileWhereCriteria($having, $param_key);
                $this->having[$key][3] = $input_parameters;
                $sql .= $havingSql;
            }
        }

        return $sql;
    }

    /**
     * @param string|null $type
     * @param array|null $input_parameters
     * @return string
     */
    public function compileQuery(string $type = null, array $input_parameters = null): string
    {
        $type = $type ?? $this->type ?? 'SELECT';
        $tables = $this->compileTables();

        switch ($type) {
            case 'COUNT':
            case 'SELECT':
                $sql = sprintf("SELECT %s FROM %s", $this->compileSelect($type), $tables);
            break;
            case 'DELETE':
                $sql = sprintf("DELETE FROM %s", $tables);
            break;
            case 'INSERT':
                $input_parameters ??= $this->insert;
                $sql = sprintf("INSERT INTO %s (", $tables);
                
                if ($this->isMultiInsert($input_parameters)) {
                    foreach ($input_parameters[0] as $key => $val) {
                        $sql .= sprintf(" `%s`,", $key);
                    }
                } else {
                    foreach ($input_parameters as $key => $val) {
                        $sql .= sprintf(" `%s`,", $key);
                    }
                }
                if (isset($this->createdField) && !isset($input_parameters[$this->createdField])) {
                    $sql .= sprintf(" `%s`,", $this->createdField);
                }
                
                $sql = ltrim(rtrim($sql, ','));
                $sql .= ') VALUES ';

                if ($this->isMultiInsert($input_parameters)) {
                    foreach ($input_parameters as $key => $parameters) {
                        $sql .= '(';
                        foreach ($parameters as $_key => $val) {
                            $sql .= sprintf(":%d%s, ", $key, $_key);
                        }
                        if (isset($this->createdField) && !isset($input_parameters[$this->createdField])) {
                            $sql .= ' CURRENT_TIMESTAMP';
                        } else {
                            $sql = ltrim(rtrim(trim($sql), ','));
                        }
                        $sql .= '),';
                    }
                    $sql = ltrim(rtrim(trim($sql), ','));
                } else {
                    $sql .= '(';
                    foreach ($input_parameters as $key => $val) {
                        $sql .= sprintf(":%s, ", $key);
                    }
                    if (isset($this->createdField) && !isset($input_parameters[$this->createdField])) {
                        $sql .= ' CURRENT_TIMESTAMP';
                    } else {
                        $sql = ltrim(rtrim(trim($sql), ','));
                    }
                    $sql .= ')';
                }
                // if (isset($this->createdField) && !isset($input_parameters[$this->createdField])) {
                //     $sql .= ' CURRENT_TIMESTAMP';
                // } else {
                    // $sql = ltrim(rtrim(trim($sql), ','));
                // }
            break;
            break;
            case 'UPDATE':
                $input_parameters ??= $this->update;
                $sql = sprintf("UPDATE %s SET", $tables);

                foreach ($input_parameters as $key => $val) {
                    $sql .= sprintf(" `%s` = :%s,", $key, $key);
                }
                if (isset($this->updatedField) && !isset($input_parameters[$this->updatedField])) {
                    $sql .= ' `'.$this->updatedField.'` = CURRENT_TIMESTAMP';
                } else {
                    $sql = rtrim($sql, ',');
                }
            break;
        }

        $param_key = count($input_parameters ?? []);

        if (isset($this->join)) {
            $this->join->each(function (Join $join) use (&$sql) {
                $sql .= ' '.$join->compile($param_key);
            });
        }

        if ($where = $this->compileWhere(false, $param_key)) {
            $sql .= ' WHERE '.$where;
        }

        if (isset($this->group)) {
            $sql .= ' GROUP BY '.implode(', ', $this->group);
        }

        if ($having = $this->compileHaving()) {
            $sql .= ' HAVING '.$having;
        }

        if ($type !== 'COUNT') {
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
        }

        return $sql;
    }

    /**
     * @param string $type
     * @return string
     */
    private function compileSelect(string $type = null): string
    {
        $selects = [];
        $selectArray = $this->select;
        if ($type === 'COUNT') {
            if (count($selectArray) === 1 && $selectArray[0] === '*') {
                return 'COUNT(*)';
            }
            $selects[] = 'COUNT(*)';
            $selectArray = array_filter($selectArray, function ($select) {
                return $select !== '*';
            });
        }

        foreach ($selectArray as $key => $value) {
            if (is_string($value)) {
                $selects[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $alias => $select) {
                    $selects[] = $select.' AS '.$alias;
                }
            }
        }

        return implode(', ', $selects);
    }

    /**
     * @return string
     */
    private function compileTables(): string
    {
        if (isset($this->tables)) {
            $tables = [];
            foreach ($this->tables as $table) {
                if (is_array($table)) {
                    foreach ($table as $alias => $table) {
                        $tables[] = sprintf('%s AS %s', $table, $alias);
                    }
                } else {
                    $tables[] = $table;
                }
            }
            $tables = implode(', ', $tables);
        } else {
            $tables = $this->table;
            if (isset($this->alias)) {
                $tables .= ' AS '.$this->alias;
            }
        }

        return $tables;
    }

    /**
     * @return array
     */
    private function getHavingParameters(): array
    {
        $parameters = [];
        foreach ($this->having as $having) {
            if (isset($having[3]) && !empty($having[3])) {
                $parameters = array_merge($parameters, $having[3]);
            }
        }
        return $parameters;
    }

    private function getJoinParams(): array
    {
        $parameters = [];
        if (isset($this->join)) {
            $this->join->each(function (Join $join) use (&$parameters) {
                $parameters = array_merge($parameters, $join->getWhereParameters());
            });
        }
        return $parameters;
    }

    public function __call($method, $args)
    {
        if ($method === 'select') $method = 'internalSelect';
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $args);
        }
        return $this->internalSelect(...$args);
    }

    public static function __callStatic($method, $args)
    {
        $instance = new static();
        if ($method == 'select') $method = 'internalSelect';
        if (method_exists($instance, $method)) {
            return call_user_func_array(array($instance, $method), $args);
        }
        return $instance->internalSelect(...$args);
    }

    public function __toString(): string
    {
        return $this->getSql();
    }
}