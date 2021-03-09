<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class MissingDefaultCommandException extends Exception
{
    public function __construct(Exception $previous = null)
    {
        parent::__construct('Application missing default command', 0, $previous);
    }
}