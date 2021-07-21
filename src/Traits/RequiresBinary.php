<?php declare(strict_types=1);

namespace PhpCli\Traits;

/**
 * Methods for checking for the existance of a binary and for excepting if a
 * given binary is not found.
 */
trait RequiresBinary
{
    private static array $hasBinary = [];

    /**
     * @param string $binary
     * @return bool
     */
    public static function binaryInstalled(string $binary = null): bool
    {
        return !!`which ${binary}`;
    }

    /**
     * @param string $binary
     * @return void
     */
    public static function requireBinary(string $binary = null)
    {
        if (!isset(static::$hasBinary[$binary])) {
            if (!static::binaryInstalled($binary)) {
                throw new \RuntimeException(sprintf('Error: %s is not installed.', $binary));
            }
            static::$hasBinary[$binary] = true;
        }
    }
}