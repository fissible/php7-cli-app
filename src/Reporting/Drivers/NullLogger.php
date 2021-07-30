<?php declare(strict_types=1);

namespace PhpCli\Reporting\Drivers;

use PhpCli\Reporting\Logger;

class NullLogger extends Logger
{
    public function __construct()
    {
        parent::__construct(new \stdClass);
    }

    /**
     * @param mixed $data
     * @param string $level
     * @param array $prefix
     * @return void
     */
    public function log($data, string $level, array $prefix = []): void
    {
        //dev/null
    }

    public static function create($Config = null): Logger
    {
        return new static();
    }
}
