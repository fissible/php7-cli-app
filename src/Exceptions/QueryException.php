<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class QueryException extends Exception
{
    public function __construct(string $message, $SQLSTATE_code, $driver_code, Exception $previous = null)
    {
        parent::__construct(sprintf(
            'SQLSTATE ERROR %s: %s - %s',
            $SQLSTATE_code,
            $driver_code,
            $message
        ), $driver_code, $previous);
    }
}