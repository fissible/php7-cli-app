<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class JsonPointerResolutionException extends Exception
{
    public function __construct(string $pointer, string $key = '', Exception $previous = null)
    {
        $message = sprintf('Error resolving JSON pointer ref "%s"%s', $pointer, ($key ? sprintf(', could not find "%s"', $key) : ''));
        parent::__construct($message, 0, $previous);
    }
}