<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class RequestException extends Exception
{
    public function __construct(string $message, Exception $previous = null)
    {
        parent::__construct(sprintf('Request error: %s', $message), 0, $previous);
    }
}