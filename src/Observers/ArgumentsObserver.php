<?php declare(strict_types=1);

namespace PhpCli\Observers;

use PhpCli\Arguments;

class ArgumentsObserver extends Observer
{
    public function __construct()
    {
        parent::__construct();
    }

    public function update(\SplSubject $Subject): void
    {
        if (!($Subject instanceof \PhpCli\Arguments)) {
            throw new \InvalidArgumentException();
        }
        $Subject->Parameters()->parseArguments();
    }
}