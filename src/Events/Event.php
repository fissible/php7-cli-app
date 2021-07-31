<?php declare(strict_types=1);

namespace PhpCli\Events;

use PhpCli\Collection;

class Event extends \Exception
{
    private static Collection $Handlers;

    public function __construct()
    {
        parent::__construct(get_class($this), 0);

        static::$Handlers = new Collection();
    }

    /**
     * Fire an event
     */
    public static function fire()
    {
        $args = func_get_args();

        if (static::$Handlers->empty()) {
            throw new static(...$args);
        }

        static::$Handlers->each(function (Handler $Handler) use ($args) {
            return $Handler(...$args);
        });
    }

    public static function subscribe(Handler $Handler)
    {
        static::$Handlers->push($Handler);
    }

    public static function unsubscribe(Handler $Handler)
    {
        static::$Handlers->delete($Handler);
    }

    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }
}