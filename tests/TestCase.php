<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseClass;

class TestCase extends BaseClass
{
    public function exe(string $binary, array $args = [])
    {
        foreach ($args as $arg => $value) {
            $name = ltrim($arg, '-');

            if (strlen($name) > 1) {
                $binary .= ' --'.$name;
            } elseif (strlen($name) === 1) {
                $binary .= ' -'.$name;
            }
            if (!is_bool($value) && is_scalar($value)) {
                $binary .= ' '.$value;
            }
        }

        ob_start();
        passthru($binary);

        return ob_get_clean();
    }
}
