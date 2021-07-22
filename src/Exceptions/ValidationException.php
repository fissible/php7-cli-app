<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public function __construct(string $message, Exception $previous = null)
    {
        return parent::__construct($message, 422, $previous);
    }
}