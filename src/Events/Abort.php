<?php declare(strict_types=1);

namespace PhpCli\Events;

class Abort extends Event
{
    protected $code;

    public function __construct(int $code = 0)
    {
        $this->code = $code;
    }
}