<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Traits\RequiresBinary;
use PhpCli\Traits\SystemInterface;

class stty
{
    use RequiresBinary, SystemInterface;

    private static string $default;

    /**
     * Capture the current settings of the stty.
     */
    public static function capture(): void
    {
        self::requireBinary('stty');

        static::$default = self::settings();
    }

    /**
     * Check if the stty flags have been modified by php.
     */
    public static function changed(): bool
    {
        if (!isset(self::$default)) return false;

        return static::$default === self::settings();
    }

    /**
     * If the settings have been modified by php, restore to the captured default.
     * Otherwise invoke `stty sane`.
     */
    public static function reset()
    {
        if (isset(static::$default)) {
            self::system(sprintf("stty '%s'", static::$default));
        } else {
            self::sane();
        }
    }

    /**
     * Invoke `stty sane` to restore stty settings to 'sane' defaults.
     */
    public static function sane(): void
    {
        self::exec('stty sane');
    }

    /**
     * Enable or disable a stty flag. Returns boolean indicating if anything changed.
     * 
     * @param string $flag
     * @param bool $value
     * @return bool
     */
    public static function set(string $flag, bool $value = true): bool
    {
        if (!isset(static::$default)) {
            self::capture();
        }

        if ($value) {
            self::system(sprintf("stty %s", $flag));
        } else {
            self::system(sprintf("stty -%s", $flag));
        }

        return static::changed();
    }

    /**
     * Report current settings in stty format.
     */
    public static function settings(): string
    {
        return self::shell_exec('stty -g');
    }
}