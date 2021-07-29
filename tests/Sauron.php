<?php declare(strict_types=1);

namespace Tests;

use ReflectionClass;

class Sauron {

    private ReflectionClass $class;

    private static $forClass;

    private $instance;
    
    public function __construct(...$args)
    {
        if (isset(static::$forClass)) {
            // Mock wrapper constructor
            $class = static::$forClass;
            $this->class = new ReflectionClass(static::$forClass);
            
            $this->instance = new $class(...$args);
        } else {
            // Wrapper constructor
            $this->class = new ReflectionClass($class);

            if (is_string($class)) {
                $this->instance = new $class;
            } else {
                $this->instance = $class;
            }
        }
    }

    /**
     * Inform Sauron who to invoke a static method call against.
     */
    public static function sees(string $class): void
    {
        static::$forClass = $class;
    }
    
    public static function forget(): void
    {
        unset(static::$forClass);
    }

    public function __call($name, $args)
    {
        $method = $this->class->getMethod($name);

        if ($method->isPublic()) {
            return call_user_func_array([$this->instance, $name], $args);
        }

        $method->setAccessible(true);

        return $method->invokeArgs($this->instance, $args);
    }

    public static function __callStatic($name, $args)
    {
        if (!isset(static::$forClass)) {
            throw new \Exception('I don\'t know what class this is for.');
        }

        $class = new ReflectionClass(static::$forClass);
        $method = $class->getMethod($name);
        
        if ($method->isPublic()) {
            return call_user_func_array([static::$forClass, $name], $args);
        }

        $method->setAccessible(true);

        return $method->invokeArgs(null, $args);
    }

    public function __get($name) 
    {
        try {
            $property = $this->class->getProperty($name);

            if (!$property->isPublic()) {
                $property->setAccessible(true);
                return $property->getValue($this->instance);
            }
        } catch (\ReflectionException $e) {
            //
        }

        return $this->instance->$name;
    }

    public function __isset($name): bool
    {
        try {
            $property = $this->class->getProperty($name);

            return $property->isInitialized($this->instance);
        } catch (\ReflectionException $e) {
            //
        }

        return isset($this->instance->$name);
    }

    public function __set(string $name, $value) 
    {
        try {
            $property = $this->class->getProperty($name);

            if (!$property->isPublic()) {
                $property->setAccessible(true);
                return $property->setValue($this->instance, $value);
            }
        } catch (\ReflectionException $e) {
            //
        }

        $this->instance->$name = $value;
    }
}