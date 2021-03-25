<?php declare(strict_types=1);

namespace PhpCli\Models;

use PhpCli\Database\Query;

class Model
{
    protected static string $table;

    protected static string $primaryKey = 'id';

    protected static string $primaryKeyType = 'int';

    protected array $dates = [];

    protected const CREATED_FIELD = 'created_at';

    protected const UPDATED_FIELD = 'updated_at';

    private array $attributes;

    private array $dirty;

    public function __construct(array $attributes = [], \PDO $db = null)
    {
        $this->setAttributes($attributes);
        if ($db) {
            Query::setDriver($db);
        }
    }

    public static function find($id)
    {
        static::getConnection();

        return static::where(static::getPrimaryKey(), $id)->first();
    }

    public static function getConnection(\PDO $db = null)
    {
        if (is_null($db)) {
            $db = Query::driver();
        }

        if (!($db instanceof \PDO)) {
            throw new \RuntimeException('No PDO instance available.');
        }

        return $db;
    }

    public static function where()
    {
        $args = func_get_args();
        static::getConnection();

        $results = Query::table(static::getTable())->where(...$args)->get();

        return $results->map(function ($attributes) {
            return new static(get_object_vars($attributes));
        });
    }

    /**
     * Get an attribute value.
     * 
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name)
    {
        $value = null;

        if (isset($this->dirty[$name])) {
            $value = $this->dirty[$name];
        } elseif (isset($this->attributes[$name])) {
            $value = $this->attributes[$name];
        }

        if (in_array($name, array_merge($this->dates, [self::UPDATED_FIELD, self::CREATED_FIELD]))) {
            $value = (new \DateTime())->setTimestamp((int) $value);
        }

        return $value;
    }

    /**
     * @return string
     */
    public static function getTable(): string
    {
        if (isset(static::$table)) {
            return static::$table;
        }
        return strtolower((new \ReflectionClass(new static))->getShortName());
    }

    public static function getPrimaryKey(): string
    {
        return static::$primaryKey ?? 'id';
    }

    /**
     * Set an attribute.
     * 
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function setAttribute(string $name, $value): self
    {
        if (in_array($name, array_merge($this->dates, [self::UPDATED_FIELD, self::CREATED_FIELD])) && $value instanceof \DateTime) {
            $value = $value->getTimestamp();
        }

        if (!$this->exists()) {
            $this->attributes[$name] = $value;
        } else {
            if ($this->attributes[$name] !== $value) {
                $this->dirty[$name] = $value;
            } else {
                unset($this->dirty[$name]);
            }
        }
        return $this;
    }

    /**
     * Delete the record.
     * 
     * @return bool
     */
    public function delete(): bool
    {
        static::getConnection();

        return Query::table(static::getTable())
            ->where(static::$primaryKey, $this->primaryKey())
            ->delete();
    }

    /**
     * Check if the primary key is set (implying the record is persisted).
     * 
     * @return bool
     */
    public function exists(): bool
    {
        if (empty($this->attributes)) return false;

        return isset($this->attributes[static::$primaryKey]);
    }

    public function hasAttribute(string $name): bool
    {
        return (isset($this->dirty[$name]) || isset($this->attributes[$name]));
    }

    /**
     * Check if any fields have been updated in memory.
     * 
     * @return bool
     */
    public function isDirty(): bool
    {
        return !empty($this->dirty);
    }

    public function insert(array $data = []): bool
    {
        static::getConnection();

        if (empty($data)) {
            $data = $this->attributes;
            unset($data[static::$primaryKey]);
        }

        if (isset($data[static::$primaryKey])) {
            throw new \InvalidArgumentException('Data includes primary key value.');
        }

        if ($id = Query::table(static::getTable())->insert($data)) {
            if (static::$primaryKeyType === 'int') {
                $id = intval($id);
            }
            $this->attributes[static::$primaryKey] = $id;
            $this->refresh();

            return true;
        }

        return false;
    }

    public function primaryKey()
    {
        if ($this->exists()) {
            return $this->attributes[static::$primaryKey];
        }
        return null;
    }

    public function refresh()
    {
        static::getConnection();

        if (!$this->exists()) {
            throw new \LogicException('Cannot refresh nonexistent model.');
        }

        $attributes = Query::table(static::getTable())
            ->where(static::$primaryKey, $this->primaryKey())
            ->first();

        $this->setAttributes(get_object_vars($attributes));

        return $this;
    }

    public function save()
    {
        if ($this->exists()) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    public function update(array $data = []): bool
    {
        static::getConnection();

        if (!$this->isDirty()) {
            return true;
        }
        if (!empty($data)) {
            foreach ($data as $key => $val) {
                if ($this->attributes[$key] !== $val) {
                    $this->dirty[$key] = $val;
                }
            }
        }

        $data = $this->dirty;
        $data[static::$primaryKey] = $this->primaryKey();

        if (array_key_exists(self::UPDATED_FIELD, $this->attributes)) {
            unset($data[self::UPDATED_FIELD]);
        }

        if (Query::table(static::getTable())->update($data, self::UPDATED_FIELD)) {
            $this->refresh();

            return true;
        }

        return false;
    }

    public function __get(string $name)
    {
        $method = $this->attributeGetter($name);

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }
        
        return $this->getAttribute($name);
    }

    public function __isset(string $name): bool
    {
        if ($this->hasAttribute($name)) {
            return true;
        }

        return method_exists($this, $this->attributeGetter($name));
    }

    public function __set(string $name, $value)
    {
        $method = $this->attributeSetter($name);

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], $value);
        }

        $this->setAttribute($name, $value);
    }

    /**
     * Set the attributes array.
     */
    protected function setAttributes(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->dirty = [];
    }

    private function attributeGetter(string $name)
    {
        $parts = explode('_', $name);
        $method = implode(array_map('ucfirst', array_map('strtolower', $parts)));
        return 'get'.$method.'Attribute';
    }

    private function attributeSetter(string $name)
    {
        $parts = explode('_', $name);
        $method = implode(array_map('ucfirst', array_map('strtolower', $parts)));
        return 'set'.$method.'Attribute';
    }
}