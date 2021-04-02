<?php declare(strict_types=1);

namespace PhpCli\Models;

use PhpCli\Database\Query;

class Model implements \JsonSerializable, \Serializable
{
    protected static string $table;

    protected static string $primaryKey = 'id';

    protected static string $primaryKeyType = 'int';

    protected static $casts = [];

    protected array $dates = [];

    protected ?bool $exists = null;

    protected static array $castTypes = [
        'int', 'integer',
        'bool', 'boolean',
        'float', 'long', 'short', 'real',
        'string',
        'array', 'json',
        'date', 'datetime'
    ];
    
    protected static $dateFormat = 'U';

    protected const CREATED_FIELD = 'created_at';

    protected const UPDATED_FIELD = 'updated_at';

    private array $attributes = [];

    private array $dirty = [];

    private Query $query;

    /**
     * @param array|object $attributes
     * @param \PDO|null $db
     */
    public function __construct($attributes = [], \PDO $db = null)
    {
        if (is_object($attributes)) {
            $attributes = get_object_vars($attributes);
        }
        if (!is_array($attributes)) {
            throw new \InvalidArgumentException('Models must be hyrdated with an array or object with public properties.');
        }

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
        return new static($attributes, static::getConnection());
    }

    public function first()
    {
        $instance = null;
        if ($result = $this->getQuery()->first()) {
            $instance = static::newInstance($result);
            $instance->exists = true;
        }
        return $instance;
    }

    public function get()
    {
        $results = $this->getQuery()->get();

        return $results->map(function ($attributes) {
            $instance = static::newInstance($attributes);
            $instance->exists = true;
            return $instance;
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
    public static function getDateFormat(): string
    {
        if (isset(static::$dateFormat)) {
            return static::$dateFormat;
        }
        return 'Y-m-d H:i:s';
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
        $exists = $this->exists();

        // Casting
        if ($this->isCastable($name)) {
            $value = $this->castAttribute($name, $value);
        }

        if (!isset($this->attributes[$name])) {
            $this->attributes[$name] = null;
        }

        if (!$exists) {
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
     * Cast the value for instance representation.
     * 
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    protected function castAttribute(string $name, $value)
    {
        $castType = null;
        $format = null;
        if (isset(static::$casts[$name])) {
            $castType = static::$casts[$name];
            if (false !== ($pos = strpos($castType, ':'))) {
                $format = substr($castType, $pos + 1);
            }
        }

        if (is_null($value) && in_array($castType, static::$castTypes)) {
            return $value;
        }
        
        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            break;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            break;
            case 'float':
            case 'long':
            case 'short':
            case 'real':
                return (float) $value;
            break;
            case 'string':
                return (string) $value;
            break;
            case 'json':
            case 'array':
                return json_decode($value);
            break;
            case 'date':
                $value = $this->asDate($value);
                if ($format) {
                    $value = $format->format($value);
                }
                return $value;
            break;
            case 'datetime':
                $value = $this->asDatetime($value);
                if ($format) {
                    $value = $format->format($value);
                }
                return $value;
            break;
        }

        if (!is_null($value) && in_array($name, $this->getDateFields())) {
            return $this->asDatetime($value);
        }

        return $value;
    }

    /**
     * Delete the record.
     * 
     * @return bool
     */
    public function delete(): bool
    {
        static::getConnection();

        $query = Query::table(static::getTable())
            ->where(static::$primaryKey, $this->primaryKey());

        $this->exists = !$query->delete();

        return !$this->exists;
    }

    /**
     * Check if the primary key is set (implying the record is persisted).
     * 
     * @return bool
     */
    public function exists(): bool
    {
        if (isset($this->exists)) {
            return $this->exists;
        }

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

        // Un-cast
        foreach ($this->getDateFields() as $field) {
            if (isset($data[$field]) && $data[$field] instanceof \DateTime) {
                $data[$field] = $data[$field]->format(static::$dateFormat);
            }
        }

        if ($id = Query::table(static::getTable())->insert($data, static::CREATED_FIELD)) {
            if (static::$primaryKeyType === 'int') {
                $id = intval($id);
            }
            $this->attributes[static::$primaryKey] = $id;
            $this->exists = true;
            $this->refresh();

            return true;
        }

        return false;
    }

    public function jsonSerialize()
    {
        $attributes = [];
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, $this->getDateFields())) {
                $value = $this->serializeDate($value);
            }
            $attributes[$key] = $value;
        }
        foreach ($this->dirty as $key => $value) {
            if (in_array($key, $this->getDateFields())) {
                $value = $this->serializeDate($value);
            }
            $attributes[$key] = $value;
        }

        return $attributes;
    }

    public function primaryKey()
    {
        if (isset($this->attributes[static::$primaryKey])) {
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

    public function serialize()
    {
        $attributes = $this->attributes;
        $dirty = $this->dirty;

        foreach ($this->getDateFields() as $dateField) {
            if (isset($attributes[$dateField]) && $attributes[$dateField] instanceof \DateTime) {
                $attributes[$dateField] = $this->serializeDate($attributes[$dateField]);
            }
            if (isset($dirty[$dateField]) && $dirty[$dateField] instanceof \DateTime) {
                $dirty[$dateField] = $this->serializeDate($dirty[$dateField]);
            }
        }

        return serialize([$this->exists, $attributes, $dirty]);
    }
    
    public function unserialize($data)
    {
        [$this->exists, $attributes, $dirty] = unserialize($data);

        foreach ($this->dates as $dateField) {
            if (isset($attributes[$dateField])) {
                $attributes[$dateField] = $this->asDatetime($attributes[$dateField]);
            }
            if (isset($dirty[$dateField])) {
                $dirty[$dateField] = $this->asDatetime($dirty[$dateField]);
            }
        }

        $this->attributes = $attributes;
        $this->dirty = $dirty;
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

        // Un-cast
        foreach ($this->getDateFields() as $field) {
            if (isset($data[$field]) && $data[$field] instanceof \DateTime) {
                $data[$field] = $data[$field]->format(static::$dateFormat);
            }
        }

        $query = Query::table(static::getTable());
        $query->where(static::$primaryKey, $this->primaryKey());

        if ($query->update($data, static::UPDATED_FIELD)) {
            $this->refresh();

            return true;
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return \DateTime
     */
    protected function asDate($value): \DateTime
    {
        return $this->asDatetime($value)->setTime(0, 0);
    }

    /**
     * @param mixed $value
     * @return \DateTime
     */
    protected function asDatetime($value): \DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTime::createFromInterface($value);
        }

        if (is_numeric($value)) {
            return \DateTime::createFromFormat('U', (string) $value);
        }

        // Y-m-d
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value)) {
            return (\DateTime::createFromFormat('Y-m-d', $value))->setTime(0, 0);
        }

        // Y-m-d H:i:s
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2}) (\d{2}):(\d{2}):(\d{2})$/', $value)) {
            return \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        }

        if (static::$dateFormat) {
            if (false !== ($dateTime = \DateTime::createFromFormat(static::$dateFormat, (string) $value))) {
                return $dateTime;
            }
        }
        
        return new \DateTime((string) $value);
    }

    protected function getDateFields(): array
    {
        return array_merge($this->dates, [static::UPDATED_FIELD, static::CREATED_FIELD]);
    }

    /**
     * @param string $field
     * @return bool
     */
    protected function isCastable(string $field): bool
    {
        return isset(static::$casts[$field]) || in_array($field, $this->getDateFields(), true);
    }
    
    /**
     * Serialize \DateTime objects
     * 
     * @param \DateTimeInterface $date
     * @return string
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:sP');
    }

    /**
     * Set the attributes array.
     */
    protected function setAttributes(array $attributes = [])
    {
        foreach ($attributes as $field => $value) {
            if ($this->isCastable($field)) {
                $this->attributes[$field] = $this->castAttribute($field, $value);
            } elseif ($field === static::$primaryKey && static::$primaryKeyType === 'int') {
                $this->attributes[$field] = (int) $value;
            } else {
                $this->attributes[$field] = $value;
            }

            if (isset($this->dirty[$field]) && $this->dirty[$field] === $this->attributes[$field]) {
                unset($this->dirty[$field]);
            }
        }
        return $this;
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