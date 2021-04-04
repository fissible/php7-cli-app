<?php declare(strict_types=1);

namespace PhpCli\Database\Grammar;

use \PhpCli\Database\Query;
use PhpCli\Traits\Database\Where;

class Join
{
    use Where;

    public const TYPE_LEFT = 'LEFT';

    public const TYPE_INNER = 'INNER';

    public const TYPE_CROSS = 'CROSS';

    public const TYPE_NATURAL = 'NATURAL';

    protected Query $subQuery;

    protected string $table;

    protected string $alias;

    protected string $type;

    protected array $on;

    protected array $using;

    /**
     * @param string $type
     * @param string|Query $tableOrSubquery
     */
    public function __construct(Query $parent, string $type, $tableOrSubquery)
    {
        $this->parent = $parent;
        $this->setType($type);

        if (is_string($tableOrSubquery)) {
            $this->setTable($tableOrSubquery);
        } elseif ($tableOrSubquery instanceof Query) {
            $this->setSubquery($tableOrSubquery);
        } else {
            throw new \InvalidArgumentException('Must join to table name or query.');
        }
    }

    /**
     * @param string $alias
     * @return self
     */
    public function as(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @return string
     */
    public function compile(?int &$param_key = 0): string
    {
        $table = $this->table ?? '('.$this->subQuery->compileQuery().')';

        if (isset($this->alias)) {
            $table .= ' AS '.$this->alias;
        }

        $sql = sprintf('%s JOIN %s', $this->type, $table);

        if (!in_array($this->type, [self::TYPE_CROSS, self::TYPE_NATURAL])) {
            if (empty($this->where) && (!isset($this->using) || empty($this->using))) {
                throw new \LogicException('JOIN missing required criteria.');
            }
            if ($where = $this->compileWhere(false, $param_key)) {
                $sql .= ' ON '.$where;
            }
            if (isset($this->using)) {
                $sql .= sprintf(' USING(%s)', implode(', ', $this->using));
            }
        }

        return $sql;
    }

    public function getSubquery(): ?Query
    {
        if (isset($this->subQuery)) {
            return $this->subQuery;
        }
        return null;
    }

    /**
     * ON string $localKey[, string $operator], string $foreignKey
     * @return self
     */
    public function on(): self
    {
        $args = func_get_args();
        return $this->whereColumn(...$args);
    }

    /**
     * USING(...$arguments)
     * @return self
     */
    public function using(): self
    {
        $this->using = func_get_args();
        return $this;
    }

    public static function validTypes(): array
    {
        return [self::TYPE_LEFT, self::TYPE_INNER, self::TYPE_CROSS, self::TYPE_NATURAL];
    }

    public function __toString(): string
    {
        return $this->compile();
    }

    /**
     * @param Query $subQuery
     * @return self
     */
    private function setSubquery(Query $subQuery): self
    {
        $this->subQuery = $subQuery;
        return $this;
    }

    /**
     * @param string $table
     * @return self
     */
    private function setTable(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * @param string $type
     * @return self
     */
    private function setType(string $type): self
    {
        $type = strtoupper($type);
        if (!in_array($type, static::validTypes())) {
            throw new \InvalidArgumentException(sprintf(
                'JOIN type "%s" invalid, must be one of %s', $type, implode(', ', $allowed_types)
            ));
        }

        $this->type = $type;
        return $this;
    }
}