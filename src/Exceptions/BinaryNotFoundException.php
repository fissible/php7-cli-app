<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class BinaryNotFoundException extends Exception
{
    public function __construct($path, Exception $previous = null)
    {
        $message = sprintf('Binary "%s" does not exist or is not executable', $path);
        parent::__construct($message, 0, $previous);
    }
}