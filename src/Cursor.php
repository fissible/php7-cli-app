<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Traits\RequiresBinary;

class Cursor
{
    use RequiresBinary;

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

    public static function put(int $y, int $x)
    {
        static::tput(sprintf('cup %d %d', $y, $x));
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