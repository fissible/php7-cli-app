<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class FileNotFoundException extends Exception
{
    public function __construct($path, Exception $previous = null)
    {
        $message = sprintf('File "%s" not found', $path);
        parent::__construct($message, 0, $previous);
    }
}