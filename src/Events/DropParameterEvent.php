<?php declare(strict_types=1);

namespace PhpCli\Events;

class DropParameterEvent extends Event
{
    protected $Parameter;

    // private Parameters $Parameters;

    public function __construct($Parameter/*Parameters $Parameters*/)
    {
        // $this->Parameters = $Parameters;
        $this->Parameter = $Parameter;
    }
}