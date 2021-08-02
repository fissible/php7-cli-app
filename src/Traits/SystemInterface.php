<?php declare(strict_types=1);

namespace PhpCli\Traits;

use PhpCli\Exceptions\SystemInterfaceException;

trait SystemInterface
{
    public static int $return_status = 0;

    /**
     * Execute an external program.
     * Capture result to an array and return it.
     * 
     * @param string
     * @return array
     */
    public static function exec(): array
    {
        $output = [];
        $command = implode(' ', func_get_args());

        if (false === exec($command, $output, static::$return_status)) {
            throw new SystemInterfaceException(sprintf('%s: failure', $command));
        }

        return $output;
    }

    /**
     * Execute an external program and display raw output.
     * Returns the return status.
     * 
     * @std
     * 
     * @param string
     * @return int
     */
    public static function passthru(): int
    {
        $command = implode(' ', func_get_args());

        passthru($command, static::$return_status);

        return static::$return_status;
    }

    /**
     * Execute command via shell and return the complete output as a string.
     * A string containing the output from the executed command
     * 
     * @param string
     * @return string|null
     */
    public static function shell_exec(): ?string
    {
        $command = implode(' ', func_get_args());

        $return = shell_exec($command);

        if ($return === false) {
            throw new SystemInterfaceException(sprintf('%s: pipe coule not be established', $command));
        }

        return $return;
    }

    /**
     * Execute an external program and display the output.
     * Returns the last line of the command output on success, and false on failure.
     * 
     * @std
     * 
     * @param string
     * @return string
     */
    public static function system(): string
    {
        $command = implode(' ', func_get_args());

        if (false === ($return = system($command, static::$return_status))) {
            throw new SystemInterfaceException(sprintf('%s: failure', $command));
        }

        return $return;
    }
}