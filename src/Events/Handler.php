<?php declare(strict_types=1);

use PhpCli\Observers\Observer;

namespace PhpCli\Events;

class Handler
{
    private $callback;

    /**
     * @param callable $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @param Observer|null
     * @return mixed
     */
    public function __invoke($Observer = null)
    {
        $callback = $this->callback;
        return $callback($Observer);
    }
}