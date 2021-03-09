<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class InvalidTypeException extends Exception
{
    public function __construct(string $CollectionType, string $invalidType, Exception $previous = null)
    {
        return parent::__construct(sprintf(
            'Typed Collection<%s> cannot be coerced to type %s',
            $CollectionType,
            $invalidType
        ), 0, $previous);
    }
}