<?php declare(strict_types=1);

namespace PhpCli\Observers;

use PhpCli\Options;

class OptionsObserver extends Observer
{
    public function __construct()
    {
        parent::__construct();
    }

    public function update(\SplSubject $Subject): void
    {
        if (!($Subject instanceof \PhpCli\Options)) {
            throw new \InvalidArgumentException();
        }
        $Subject->Parameters()->parseOptions();
    }
}