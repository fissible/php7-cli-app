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

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->Application, $name], $arguments);
    }
}