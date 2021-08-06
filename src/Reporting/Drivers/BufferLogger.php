<?php declare(strict_types=1);

namespace PhpCli\Reporting\Drivers;

use PhpCli\Buffer;
use PhpCli\Reporting\Logger;

class BufferLogger extends Logger {

    public Buffer $Buffer;

    public function __construct($Config)
    {
        parent::__construct($Config);

        $this->Buffer = new Buffer();
    }

    /**
     * @param mixed $data
     * @param string $level
     * @param array $prefix
     * @return void
     */
    public function log($data, string $level, array $prefix = []): void
    {

        $this->validateLevel($level);

        $entry = static::itemToString($data);
        if ($prefix = implode(static::$prefixJoin, $prefix)) {
            $entry = $prefix . ': ' . $entry;
        }

        $this->Buffer->collect($entry);
    }

    public static function create($Config): Logger
    {
        return new BufferLogger($Config);
    }
}