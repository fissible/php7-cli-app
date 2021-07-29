<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class InvalidFileModeException extends Exception
{
    public function __construct(string $path, string $mode, string $message = null, Exception $previous = null)
    {
        $message = sprintf('%s%s has an invalid file mode %s', ($message ? $message.': ' : ''), $path, $mode);
        parent::__construct($message, 0, $previous);
    }
}
