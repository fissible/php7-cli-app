<?php declare(strict_types=1);

namespace PhpCli\Events;

class Handler
{
    private Event $Event;

    private $callback;

    /**
     * @param Event $Event
     * @param callable $callback
     */
    public function __construct(Event $Event, callable $callback)
    {
        $this->Event = $Event;
        $this->callback = $callback;
    }

    /**
     * @param Event $Event
     * @return bool
     */
    public function handles(Event $Event): bool
    {
        return get_class($this->Event) === get_class($Event);
    }

    /**
     * @param Observer|null
     * @return mixed
     */
    public function __invoke(Observer $Observer = null)
    {
        $callback = $this->callback;
        return $callback($Observer);
    }
}