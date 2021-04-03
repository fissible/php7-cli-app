<?php declare(strict_types=1);

namespace PhpCli\Traits\Database;

trait Where
{
    private array $where = [];

    /**
     * @return array
     */
    public function getWhereParameters(): array
    {
        $parameters = [];
        foreach ($this->where as $where) {
            if (isset($where[4]) && !empty($where[4])) {
                $parameters = array_merge($parameters, $where[4]);
            }
        }
        return $parameters;
    }

    /**
     * alias for AND WHERE
     */
    public function and()
    {
        $args = func_get_args();
        return $this->where(...$args);
    }

    /**
     * alias for OR WHERE
     */
    public function or()
    {
        $args = func_get_args();
        return $this->orWhere(...$args);
    }

    /**
     * OR WHERE
     */
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
     * OR WHERE BETWEEN
     * Inclusive; BETWEEN 3 AND 5 yields 3, 4, 5
     */
    public function orWhereBetween(string $column, array $values): self
    {
        return $this->addWhere('OR', $column, 'BETWEEN', $values);
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

    /**
     * OR WHERE IN
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->addWhere('OR', $column, 'IN', $values);
    }

    /**
     * OR WHERE NOT IN
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->addWhere('OR', $column, 'NOT IN', $values);
    }

    /**
     * [AND] WHERE
     */
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
     * [AND] WHERE BETWEEN
     * Inclusive; BETWEEN 3 AND 5 yields 3, 4, 5
     */
    public function whereBetween(string $column, array $values): self
    {
        return $this->addWhere('AND', $column, 'BETWEEN', $values);
    }

    protected function whereColumn(): self
    {
        $args = func_get_args();
        [$column, $operator, $value] = $this->expandColumnOperatorValue(...$args);

        if (count($args) === 1) {
            $this->addWhere('AND', $args[0]);
        } else {
            $this->addWhere('AND', $column, $operator, \PhpCli\Database\Query::raw($value));
        }

        return $this;
    }

    /**
     * [AND] WHERE DATE
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

    protected function addWhere(string $conjunction, $column, $operator = null, $value = null): self
    {
        $this->where[] = [$conjunction, $column, $operator, $value];
        return $this;
    }

    protected function compileWhere(bool $enclose = false, ?int &$param_key = 0): string
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

    private function compileWhereCriteria(array $where, ?int &$param_key = 0): array
    {
        $conjunction = $where[0];

        if (is_object($where[1]) && !($where[1] instanceof \stdClass) && ($where[1] instanceof \Closure)) {
            $query = new \PhpCli\Database\Query();
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
                    if (!$this->valueisRaw($item)) {
                        $param_key++;
                        $key = ':'.(str_replace(' ', '_', $operator)).$param_key;
                        $in .= "$key, ";
                        $input_parameters[$key] = $item;
                    } else {
                        $in .= $this->unwrap($item).", ";
                    }
                }
                $value = '('.rtrim(trim($in), ',').')';
            } elseif($operator === 'BETWEEN') {
                $value = array_values($value);
                $values = [];

                if (!$this->valueisRaw($value[0])) {
                    $keyFrom = $operator.(++$param_key);
                    $input_parameters[':'.$keyFrom] = $value[0];
                    $values[] = ':'.$keyFrom;
                } else {
                    $values[] = $this->unwrap($value[0]);
                }

                if (!$this->valueisRaw($value[1])) {
                    $keyTo = $operator.(++$param_key);
                    $input_parameters[':'.$keyTo] = $value[1];
                    $values[] = ':'.$keyTo;
                } else {
                    $values[] = $this->unwrap($value[1]);
                }
                
                $value = implode(' AND ', $values);
            } else {
                throw new \InvalidArgumentException(sprintf('Invalid WHERE criteria value "%s', gettype($value)));
            }
        } elseif (!$this->valueisRaw($value)) {
            $param_key++;
            $key = ':'.$conjunction.$param_key;
            $input_parameters[$key] = $value;
            $value = $key;
        } else {
            $value = $this->unwrap($value);
        }

        // `table.field` -> `table`.`field`
        $column = $where[1];
        if ($column[0] === '`' && $column[-1] === '`' && false !== strpos($column, '.') && substr_count($column, '`') === 2) {
            $column = str_replace('.', '`.`', $column);
        }
        
        return [sprintf('%s %s %s', $column, $operator, $value), $input_parameters];
    }

    private function unwrap($value)
    {
        if (is_object($value)) {
            return $value->value;
        }
        return $value;
    }

    private function valueisRaw($value)
    {
        if ($value instanceof \stdClass) {
            return true;
        }
        if (is_string($value) && $value[0] === '`') {
            return true;
        }
        return false;
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
}