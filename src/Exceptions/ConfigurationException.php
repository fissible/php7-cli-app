<?php declare(strict_types=1);

namespace PhpCli\Exceptions;

use Exception;

class ConfigurationException extends Exception
{
    public function __construct(string $message = 'Configuration missing required keys', ?string $missingKey = null, Exception $previous = null)
    {
        if ($missingKey) {
            return parent::__construct($message.sprintf('Configuration mising key "%s"', $missingKey), 0, $previous);
        }
        return parent::__construct($message);
    }
}