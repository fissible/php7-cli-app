<?php declare(strict_types=1);

namespace PhpCli\Events;

use PhpCli\Collection;

class Event extends \Exception
{
    private static Collection $Handlers;

    private array $payload;

    public function __construct(array $payload = [])
    {
        parent::__construct(get_class($this), 0);

        $this->payload = $payload;

        static::$Handlers = new Collection();
    }

    public function payload(): array
    {
        return $this->payload;
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

        if (isset($this->payload[$name])) {
            return $this->payload[$name];
        }

        return null;
    }
}