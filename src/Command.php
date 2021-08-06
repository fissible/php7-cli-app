<?php declare(strict_types=1);

namespace PhpCli;

class Command
{
    protected Application $Application;

    public function __construct(Application $Application)
    {
        $this->Application = $Application;
    }

    public function app(): Application
    {
        return $this->Application;
    }

    public function run()
    {
        return 0;
    }
    
    public function __get($name)
    {
        if (isset($this->$name)) {
            return $this->$name;
        }

        return $this->app()->{$name};
    }

    public function __set($name, $value)
    {
        if (isset($this->$name)) {
            $this->$name = $value;
        } else {

        }

        $this->app()->{$name} = $value;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->Application, $name], $arguments);
    }
}