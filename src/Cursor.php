<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Traits\RequiresBinary;
use PhpCli\Traits\SystemInterface;

class Cursor
{
    use RequiresBinary, SystemInterface;

    public static function hide()
    {
        static::tput('civis');
    }

    public static function show()
    {
        static::tput('cnorm');
    }

    public static function moveDown(int $distance = 1)
    {
        static::tput(sprintf('cud %d', $distance));
    }

    public static function moveLeft(int $distance = 1)
    {
        static::tput(sprintf('cub %d', $distance));
    }

    public static function moveRight(int $distance = 1)
    {
        static::tput(sprintf('cuf %d', $distance));
    }

    public static function moveUp(int $distance = 1)
    {
        static::tput(sprintf('cuu %d', $distance));
    }

    public static function put(int $y, int $x, string $string = null)
    {
        if ($string !== null) {
            // printf '\e[%d;%dH' 6 9
            // static::shell_exec(sprintf('printf \'\\e[%%d;%%d%s\' %d %d', $string, $y + 1, $x + 1));
            static::shell_exec(sprintf('tput cup %d %d', $y, $x));
            echo $string;
        } else {
            static::tput(sprintf('cup %d %d', $y, $x));
        }
    }

    public static function restore()
    {
        static::tput('rc');
    }

    public static function save()
    {
        static::tput('sc');
    }

    private static function rtput($command)
    {
        self::requireBinary('tput');
        return exec(sprintf('tput %s', $command));
    }

    private static function tput($command)
    {
        self::requireBinary('tput');
        return system(sprintf('tput %s', $command));
    }
}