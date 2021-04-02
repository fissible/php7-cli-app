<?php declare(strict_types=1);

namespace PhpCli\Database;

use PhpCli\Collection;
use PhpCli\Exceptions\QueryException;

class Query {

    private static \PDO $db;

    protected string $table;

    protected string $alias;

    protected array $group;

    protected array $having = [];

    protected array $insert;

    protected array $join = [];

    protected int $limit;

    protected int $offset;

    protected array $order;

    protected array $select = ['*'];

    protected string $type = 'SELECT';

    protected array $update;

    protected ?string $createdField;

    protected ?string $updatedField;

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

    public function as(string $alias): self
    {
        if (isset($this->join) && !empty($this->join)) {
            end($this->join);
            $this->join[key($this->join)]['as'] = $alias;
            reset($this->join);
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

    public function exe(string $sql)
    {
        if (!isset(static::$db)) {
            throw new \RuntimeException('No PDO driver available.');
        }

        if ($this->isMultiInsert()) {
            $where_params = $this->getWhereParameters();
            $having_params = $this->getHavingParameters();
            static::$db->beginTransaction();
            try {
                foreach ($this->insert as $input_parameters) {
                    $parameters = array_merge($input_parameters, $where_params, $having_params);
                    $stmt = $this->bindParameters($this->prepareStatement($sql), $parameters);
                    $result = $stmt->execute();
                }
                static::$db->commit();
            } catch (\Throwable $e) {
                static::$db->rollBack();
                throw $e;
            }
        } else {
            $stmt = $this->bindParameters($this->prepareStatement($sql), $this->getParams($sql));
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

    /**
     * @return array
     */
    public function getParams(string $sql = null): array
    {
        $input_parameters = [];
        if (is_null($sql)) {
            $sql = $this->compileQuery();
        }
        $where_params = $this->getWhereParameters();
        $having_params = $this->getHavingParameters();
        if (substr($sql, 0, 6) === 'INSERT' && isset($this->insert)) {
            $input_parameters = $this->insert;
        } elseif (substr($sql, 0, 6) === 'UPDATE' && isset($this->update)) {
            $input_parameters = $this->update;
        }

        return array_merge($input_parameters, $where_params, $having_params);
    }

    /**
     * Get the query SQL with parameters substituted for placeholders. Not intended for subsequent execution.
     * 
     * @return string
     */
    public function getSql(): string
    {
        $sql = $this->compileQuery();
        $params = $this->getParams($sql);
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
            throw new QueryException($error[2], $error[0], $error[1]);
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
            throw new QueryException($error[2], $error[0], $error[1]);
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
            throw new QueryException($error[2], $error[0], $error[1]);
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

    public static function insertId()
    {
        return static::$db->lastInsertId();
    }

    public function innerJoin($table, string $localKey, string $foreignKey): self
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'INNER');
    }

    public function join($table, string $localKey, string $foreignKey, $type = 'INNER'): self
    {
        $this->join[] = [$type, $table, $localKey, $foreignKey];
        return $this;
    }

    public function leftJoin($table, string $localKey, string $foreignKey): self
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'LEFT');
    }

    public function outerJoin($table, string $localKey, string $foreignKey): self
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'OUTER');
    }

    public function rightJoin($table, string $localKey, string $foreignKey): self
    {
        return $this->join($table, $localKey, $foreignKey, $type = 'RIGHT');
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

    public function orderBy(string $field, string $dir = 'ASC'): self
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

    public function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function orWhere(): self
    {
        $args = func_get_args();
        [$column, $operator, $value] = $this->expandColumnOperatorValue(...$args);

        if (count($args) === 1) {
            $this->addWhere('OR', $args[0]);
        } else {
            $this->addWhere('OR', $column, $operator, $value);
        }

        return $this;
    }

    /**
     * Convert value into SQL date for comparison.
     */
    public function orWhereDate(): self
    {
        $args = func_get_args();
        [$column, $operator, $value] = $this->expandColumnOperatorValue(...$args);

        if (is_int($value)) {
            $value = \DateTime::createFromFormat('U');
        }
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d');
        }

        // sqlite specific
        return $this->addWhere('OR', sprintf('date(%s, \'unixepoch\')', $column), $operator, $value);
    }

    public function orWhereIn(string $column, array $values): self
    {
        return $this->addWhere('OR', $column, 'IN', $values);
    }

    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->addWhere('OR', $column, 'NOT IN', $values);
    }

    public function where(): self
    {
        $args = func_get_args();
        [$column, $operator, $value] = $this->expandColumnOperatorValue(...$args);

        if (count($args) === 1) {
            $this->addWhere('AND', $args[0]);
        } else {
            $this->addWhere('AND', $column, $operator, $value);
        }

        return $this;
    }

    /**
     * Inclusive; BETWEEN 3 AND 5 yields 3, 4, 5
     */
    public function whereBetween(string $column, array $values, string $conjunction = 'AND'): self
    {
        return $this->addWhere(strtoupper($conjunction), $column, 'BETWEEN', $values);
    }

    /**
     * Convert value into SQL date for comparison.
     */
    public function whereDate(): self
    {
        $args = func_get_args();
        [$column, $operator, $value] = $this->expandColumnOperatorValue(...$args);

        if (is_int($value)) {
            $value = \DateTime::createFromFormat('U');
        }
        if ($value instanceof \DateTime) {
            $value = $value->format('Y-m-d');
        }

        // sqlite specific
        return $this->addWhere('AND', sprintf('date(%s, \'unixepoch\')', $column), $operator, $value);
    }

    public function whereIn(string $column, array $values): self
    {
        return $this->addWhere('AND', $column, 'IN', $values);
    }

    public function whereNotBetween(string $column, array $values, string $conjunction = 'AND'): self
    {
        return $this->addWhere(strtoupper($conjunction), $column, 'NOT BETWEEN', $values);
    }

    public function whereNotIn(string $column, array $values): self
    {
        return $this->addWhere('AND', $column, 'NOT IN', $values);
    }

    private function addHaving(string $column, $operator = null, $value = null): self
    {
        $this->having[] = [$column, $operator, $value];
        return $this;
    }

    private function addWhere(string $conjunction, $column, $operator = null, $value = null): self
    {
        $this->where[] = [$conjunction, $column, $operator, $value];
        return $this;
    }

    /**
     * From 2 or 3 arguments return column, operator and value(s).
     */
    private function expandColumnOperatorValue()
    {
        $args = func_get_args();
        $operator = null;
        $value = null;

        if (count($args) === 1 && !is_callable($args[0])) {
            throw new \InvalidArgumentException('Single parameter must be a callable.');
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

        return [$args[0], $operator, $value];
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
     * @param array $join
     * @return string
     */
    private function compileJoin(array $join): string
    {
        $table = $join[1];

        if ($table instanceof Query) {
            $table = '('.$table.')';
        }

        if (isset($join['as'])) {
            $table .= ' AS '.$join['as'];
        }

        return sprintf(
            '%s JOIN %s ON %s = %s',
            $join[0], $table, $join[2], $join[3]
        );
    }

    public function compileQuery(string $type = null, $input_parameters = null): string
    {
        $type = $type ?? $this->type ?? 'SELECT';
        switch ($type) {
            case 'COUNT':
                if (count($this->select) === 1 && $this->select[0] === '*') {
                    $sql = sprintf("SELECT COUNT(*) FROM `%s`", $this->table);
                } else {
                    $sql = sprintf("SELECT  COUNT(*), %s FROM `%s`", implode(', ', $this->select), $this->table);
                }
                if (isset($this->alias)) {
                    $sql .= ' AS '.$this->alias;
                }
            break;
            case 'DELETE':
                $sql = sprintf("DELETE FROM `%s`", $this->table);
                if (isset($this->alias)) {
                    $sql .= ' AS '.$this->alias;
                }
            break;
            case 'INSERT':
                if (is_null($input_parameters) && isset($this->insert)) {
                    $input_parameters = $this->insert;
                }

                if (isset($this->alias)) {
                    $sql = sprintf("INSERT INTO `%s` AS %s (", $this->table, $this->alias);
                } else {
                    $sql = sprintf("INSERT INTO `%s` (", $this->table);
                }
                
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
                $sql .= '(';

                if ($this->isMultiInsert($input_parameters)) {
                    foreach ($input_parameters[0] as $key => $val) {
                        $sql .= sprintf(" :%s,", $key);
                    }
                } else {
                    foreach ($input_parameters as $key => $val) {
                        $sql .= sprintf(" :%s,", $key);
                    }
                }
                if (isset($this->createdField) && !isset($input_parameters[$this->createdField])) {
                    $sql .= ' CURRENT_TIMESTAMP';
                } else {
                    $sql = ltrim(rtrim($sql, ','));
                }
                $sql .= ')';
            break;
            case 'SELECT':
                $sql = sprintf("SELECT %s FROM `%s`", implode(', ', $this->select), $this->table);
                if (isset($this->alias)) {
                    $sql .= ' AS '.$this->alias;
                }
            break;
            case 'UPDATE':
                if (is_null($input_parameters) && isset($this->update)) {
                    $input_parameters = $this->update;
                }

                if (isset($this->alias)) {
                    $sql = sprintf("UPDATE `%s` AS %s SET", $this->table, $this->alias);
                } else {
                    $sql = sprintf("UPDATE `%s` SET", $this->table);
                }

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

        if (!empty($this->join)) {
            foreach ($this->join as $join) {
                $sql .= ' '.$this->compileJoin($join);
            }
        }

        if ($where = $this->compileWhere()) {
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

    public function __toString(): string
    {
        return $this->compileQuery();
    }

    private function compileWhere(bool $enclose = false, int &$param_key = 0): string
    {
        $sql = '';

        if (!empty($this->where)) {
            if ($enclose) {
                $sql .= '(';
            }

            foreach ($this->where as $key => $where) {
                if ($key > 0) $sql .= ' '.$where[0].' '; // AND|OR
                $param_key + $key;
                list($whereSql, $input_parameters) = $this->compileWhereCriteria($where, $param_key);
                $this->where[$key][4] = $input_parameters;
                $sql .= $whereSql;
            }

            if ($enclose) {
                $sql .= ')';
            }
        }

        return $sql;
    }

    private function compileWhereCriteria($where, int &$param_key = 0): array
    {
        $conjunction = $where[0];

        if (is_object($where[1]) && $where[1] instanceof \Closure) {
            $query = new static(static::$db);
            $where[1]($query);
            $sql = $query->compileWhere(true, $param_key);
            
            return [$sql, $query->getWhereParameters()];
        }

        $operator = $where[2];
        $value = $where[3];
        $input_parameters = [];
        if (is_array($value)) {
            if (in_array($operator, ['IN', 'NOT IN'])) {
                $in = '';
                foreach ($value as $item) {
                    if (!is_string($item) || $item[0] !== '`') {
                        $param_key++;
                        $key = ':'.(str_replace(' ', '_', $operator)).$param_key;
                        $in .= "$key, ";
                        $input_parameters[$key] = $item;
                    } else {
                        $in .= "$item, ";
                    }
                }
                $value = '('.rtrim(trim($in), ',').')';
            } elseif($operator === 'BETWEEN') {
                $value = array_values($value);
                $values = [];

                if (!is_string($value[0]) || $value[0][0] !== '`') {
                    $keyFrom = $operator.(++$param_key);
                    $input_parameters[':'.$keyFrom] = $value[0];
                    $values[] = ':'.$keyFrom;
                } else {
                    $values[] = $value[0];
                }

                if (!is_string($value[1]) || $value[1][0] !== '`') {
                    $keyTo = $operator.(++$param_key);
                    $input_parameters[':'.$keyTo] = $value[1];
                    $values[] = ':'.$keyTo;
                } else {
                    $values[] = $value[1];
                }
                
                $value = implode(' AND ', $values);
            } else {
                throw new \InvalidArgumentException(sprintf('Invalid WHERE criteria value "%s', gettype($value)));
            }
        } elseif (!is_string($value) || $value[0] !== '`') {
            $param_key++;
            $key = ':'.$conjunction.$param_key;
            $input_parameters[$key] = $value;
            $value = $key;
        }

        // `table.field` -> `table`.`field`
        $column = $where[1];
        if ($column[0] === '`' && $column[-1] === '`' && false !== strpos($column, '.') && substr_count($column, '`') === 2) {
            $column = str_replace('.', '`.`', $column);
        }
        
        return [sprintf('%s %s %s', $column, $operator, $value), $input_parameters];
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

    /**
     * @return array
     */
    private function getWhereParameters(): array
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