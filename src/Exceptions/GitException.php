<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class GitException extends Exception
{
    public function __construct(string $message, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}