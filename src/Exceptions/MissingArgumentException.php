<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class MissingArgumentException extends Exception
{
    public function __construct(string $parameterName, Exception $previous = null)
    {
        $message = sprintf('Command missing required parameter "<%s>"', $parameterName);
        parent::__construct($message, 0, $previous);
    }
}