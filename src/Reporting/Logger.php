<?php declare(strict_types=1);

namespace PhpCli\Reporting;

use PhpCli\Traits\HasConfig;
use PhpCli\Reporting\Drivers\FileLogger;
use PhpCli\Reporting\Drivers\BufferLogger;
use PhpCli\Reporting\Drivers\StandardLogger;

class Logger {

    use HasConfig;

    protected array $levels = [
        self::FATAL,
        'error',
        'warning',
        'info',
        'debug'
    ];

    public const FATAL = 'fatal';

    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const INFO = 'info';

    public const DEBUG = 'debug';

    public static string $prefixJoin = ' - ';

    public function __construct($Config)
    {
        $this->setConfig($Config);
    }

    public function env(): ?string
    {
        if (isset($this->Config->env)) {
            return $this->Config->env;
        }

        return null;
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

        echo $this->format($level, $data, $prefix);
    }

    public function fatal($data)
    {
       $this->log($data, Logger::FATAL);
    }

    public function error($data)
    {
       $this->log($data, Logger::ERROR);
    }

    public function warning($data)
    {
       $this->log($data, Logger::WARNING);
    }

    public function info($data)
    {
       $this->log($data, Logger::INFO);
    }

    public function debug($data)
    {
        $this->log($data, Logger::DEBUG);
    }

    public function name(): ?string
    {
        if (isset($this->Config->name)) {
            return $this->Config->name;
        }
        return null;
    }

    public static function create($Config): Logger
    {
        $driver = new Logger($Config);

        switch ($driver->Config->driver) {
            case 'buffer':
                return BufferLogger::create($Config);
            case 'file':
                return FileLogger::create($Config);
            case 'stdout':
            case 'standard':
            default:
                return StandardLogger::create($Config);
        }

        return $driver;
    }

    /**
     * @param string $level
     * @param mixed $data
     * @param array $prefix
     * @return string
     */
    protected function format(string $level, $data, array $prefix = []): string
    {
        $this->validateLevel($level);

        $prefix = array_filter(array_merge([
            $this->name(),
            date('Y-m-d H:i:s'),
            ($this->env() ? $this->env().'.' : '').$level
        ], $prefix));

        // Coerce data to a string
        $data = static::itemToString($data);
        $prefix = implode(static::$prefixJoin, $prefix);

        return sprintf('%s: %s', $prefix, $data);
    }

    protected static function itemToString($data): string
    {
        if (!is_string($data)) {
            if (is_object($data) && method_exists($data, '__toString')) {
                $data = '' . $data;
            } else {
                $data = var_export($data, true);
            }
        }

        return $data;
    }

    protected function validateLevel(string $level)
    {
        $level = strtolower($level);
        if (!in_array($level, $this->levels)) {
            throw new \InvalidArgumentException(sprintf('Log level "%s" does not exist.', $level));
        }
    }
}