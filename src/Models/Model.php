<?php declare(strict_types=1);

namespace PhpCli\Models;

class Model
{
    protected string $table;

    protected string $primaryKey = 'id';

    private \PDO $db;

    private array $attributes;

    public function __construct(\PDO $db, array $attributes = [])
    {
        $this->db = $db;
        $this->setAttributes($attributes);
    }

    public function setAttributes(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    public function delete()
    {
        if (!$this->exists()) {
            throw new \LogicException('Cannot delete nonexistent model');
        }
        $sql = sprintf("DELETE FROM `%s` WHERE %s=? LIMIT 1", $this->table, $this->primaryKey);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$this->primaryKey()]);
    }

    public function exists()
    {
        if (empty($this->attributes)) return false;

        return isset($this->attributes[$this->primaryKey]);
    }

    public function find($id)
    {
        $stmt = $this->db->prepare(sprintf("SELECT * FROM %s WHERE %s=?", $this->table, $this->primaryKey));
        $stmt->execute([$id]); 
        $attributes = $stmt->fetch();

        return new static($this->db, $attributes);
    }

    public function primaryKey()
    {
        if ($this->exists()) {
            return $this->attributes[$this->primaryKey];
        }
        return null;
    }

    public function insert(array $data)
    {
        if (isset($data[$this->primaryKey])) {
            throw new \InvalidArgumentException('Data includes primary key value.');
        }

        $sql = sprintf("INSERT INTO %s (", $this->table);
        foreach ($data as $key => $val) {
            $sql .= sprintf(" %s,", $key);
        }
        $sql = ltrim(rtrim($sql, ','));
        $sql .= ') VALUES (';
        foreach ($data as $key => $val) {
            $sql .= sprintf(" :%s,", $key);
        }
        $sql = ltrim(rtrim($sql, ','));
        $sql .= ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        $id = $this->db->lastInsertId();

        return $this->find($id);
    }

    public function save()
    {
        $attributes = $this->attributes;
        unset($attributes[$this->primaryKey]);
        
        if ($this->exists()) {
            return $this->update($attributes);
        } else {
            return $this->insert($attributes);
        }
    }

    public function update(array $data): bool
    {
        if (isset($data[$this->primaryKey])) {
            throw new \InvalidArgumentException('Data includes primary key value.');
        }

        $data[$this->primaryKey] = $this->primaryKey();
        $sql = sprintf("UPDATE %s SET", $this->table);
        foreach ($data as $key => $val) {
            $sql .= sprintf(" %s=:%s,", $key, $key);
        }
        $sql = rtrim($sql, ',');
        $sql .= sprintf(" WHERE %s=:%s", $this->primaryKey, $this->primaryKey);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    public function __get(string $name)
    {
        $method = $this->attributeGetter($name);

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }

        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }
        
        return null;
    }

    public function __isset(string $name): bool
    {
        if (isset($this->attributes[$name])) {
            return true;
        }

        $method = $this->attributeGetter($name);

        if (method_exists($this, $method)) {
            return true;
        }

        return false;
    }

    public function __set(string $name, $value)
    {
        $method = $this->attributeSetter($name);

        if (method_exists($this, $method)) {
            return call_user_func([$this, $method], $value);
        }

        $this->attributes[$name] = $value;
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