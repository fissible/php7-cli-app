<?php declare(strict_types=1);

namespace PhpCli\Events;

class Event extends \Exception
{
    public function __construct()
    {
        parent::__construct(get_class($this), 0);
    }

    /**
     * Fire an event
     */
    public static function fire()
    {
        $args = func_get_args();
        throw new static(...$args);
    }

    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }
}