<?php declare(strict_types=1);

namespace PhpCli\Models;

use PhpCli\Database\Query;

class Model
{
    protected static string $table;

    protected static string $primaryKey = 'id';

    protected static string $primaryKeyType = 'int';

    protected array $dates = [];
    
    protected static $dateFormat = 'U';

    protected const CREATED_FIELD = 'created_at';

    protected const UPDATED_FIELD = 'updated_at';

    private array $attributes = [];

    private array $dirty;

    private Query $query;

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

    public static function newInstance($attributes = []): Model
    {
        if (is_object($attributes)) {
            $attributes = get_object_vars($attributes);
        }
        if (!is_array($attributes)) {
            throw new \InvalidArgumentException('Models must be hyrdated with an array or object with public properties.');
        }
        return new static($attributes, static::getConnection());
    }

    public function first()
    {
        if ($result = $this->getQuery()->first()) {
            return static::newInstance($result);
        }
        return null;
    }

    public function get()
    {
        $results = $this->getQuery()->get();

        return $results->map(function ($attributes) {
            return static::newInstance($attributes);
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

        if (!is_null($value)) {
            if (in_array($name, array_merge($this->dates, [static::UPDATED_FIELD, static::CREATED_FIELD]))) {
                $value = \DateTime::createFromFormat(static::$dateFormat, $value);
            }
        }
        

        return $value;
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
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
        if (in_array($name, array_merge($this->dates, [static::UPDATED_FIELD, static::CREATED_FIELD])) && $value instanceof \DateTime) {
            $value = $value->format(static::$dateFormat);
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

    public static function query()
    {
        $instance = static::newInstance();
        $instance->getQuery();
        return $instance;
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

        if (!empty($data)) {
            foreach ($data as $key => $val) {
                if ($this->attributes[$key] !== $val) {
                    $this->dirty[$key] = $val;
                }
            }
        }

        if (!$this->isDirty()) {
            return true;
        }

        $data = $this->dirty;

        if (static::UPDATED_FIELD && array_key_exists(static::UPDATED_FIELD, $data)) {
            unset($data[static::UPDATED_FIELD]);
        }
        unset($data[static::$primaryKey]);

        $query = Query::table(static::getTable());
        $query->where(static::$primaryKey, $this->primaryKey());

        if ($query->update($data, static::UPDATED_FIELD)) {
            $this->refresh();

            return true;
        }

        return false;
    }

    private function getQuery()
    {
        if (!isset($this->query)) {
            $this->query = Query::table(static::getTable());
        }
        return $this->query;
    }

    private static function callQuery()
    {
        $args = func_get_args();
        $instance = array_shift($args);
        $method = array_shift($args);

        return call_user_func_array(array($instance->getQuery(), $method), $args);
    }

    public function __call($name, $arguments)
    {
        $result = static::callQuery($this, $name, ...$arguments);
        if (!($result instanceof Query)) {
            return $result;
        }

        return $this;
    }

    public static function __callStatic($name, $arguments)
    {
        $instance = static::newInstance();
        $result = static::callQuery($instance, $name, ...$arguments);
        if (!($result instanceof Query)) {
            return $result;
        }

        return $instance;
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
        foreach ($attributes as $field => $attribute) {
            $this->attributes[$field] = $attribute;
        }
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