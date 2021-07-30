<?php declare(strict_types=1);

namespace PhpCli\Git;

use PhpCli\Str;

class git {

    public const BINARY = 'git';

    public const DATE_FORMAT = 'D M j H:i:s Y T';

    private const PORCELAIN_VERSION = 'v2';

    protected static $result_code;

    protected static $working_directory;

    /**
     * Set the working directory for a git repository.
     *
     * @param string $path
     * @return void
     */
    public static function cd(string $path)
    {
        static::$working_directory = $path;
    }

    /**
     * Get the porcelain option string.
     * Give the output in an easy-to-parse format for scripts.
     *
     * @return string
     */
    public static function porcelain(): string
    {
        $porcelain = '--porcelain';

        if (self::PORCELAIN_VERSION) {
            $porcelain .= '='.self::PORCELAIN_VERSION;
        }

        return $porcelain;
    }

    /**
     * Get the return code of the last command.
     *
     * @return void
     */
    public static function result()
    {
        return static::$result_code;
    }

    public static function version(): string
    {
        $output = static::git('--version');

        return $output[0];
    }

    public static function __callStatic($name, $arguments)
    {
        $command = str_replace('_', '-', $name).' '.implode(' ', $arguments);
        
        return static::git($command);
    }

    private static function bin(): string
    {
        $bin = '';
        if ($dir = static::cwd()) {
            $bin = 'cd '.$dir.' && ';
        }
        $bin .= self::BINARY;

        return $bin;
    }

    private static function cwd(): ?string
    {
        if (isset(static::$working_directory)) {
            return static::$working_directory;
        }

        return null;
    }

    private static function git(string $command)
    {
        $bin = static::bin();

        // print "\nCMD: git ".$command."\n";
        exec($bin.' '.$command.' 2>&1', $output, static::$result_code);

        if (!empty($output)) {
            if (Str::startsWith($output[0], 'fatal:') || Str::startsWith($output[0], 'error:')) {
                $error = implode("\n", $output);
                throw new \Exception('Error: '.Str::lprune(Str::lprune($error, 'fatal:'), 'error:'));
            }
        }

        return $output;
    }
}