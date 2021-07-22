<?php declare(strict_types=1);

namespace PhpCli\Reporting;

use PhpCli\Traits\HasConfig;
use PhpCli\Reporting\Drivers\FileLogger;
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

    public function __construct(array $config)
    {
        $this->setConfig($config);
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

    public function name(): ?string
    {
        if (isset($this->Config->name)) {
            return $this->Config->name;
        }
        return null;
    }

    public static function create(array $config): Logger
    {
        $driver = new Logger($config);

        switch ($driver->Config->driver) {
            case 'file':
                return FileLogger::create($config);
                break;
            case 'stdout':
            case 'standard':
            default:
                return StandardLogger::create($config);
                break;
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
        if (!is_string($data)) {
            if (is_object($data) && method_exists($data, '__toString')) {
                $data = ''.$data;
            } else {
                $data = var_export($data, true);
            }
        }

        $prefix = implode(static::$prefixJoin, $prefix);

        return sprintf('%s: %s', $prefix, $data);
    }

    protected function validateLevel(string $level)
    {
        $level = strtolower($level);
        if (!in_array($level, $this->levels)) {
            throw new \InvalidArgumentException(sprintf('Log level "%s" does not exist.', $level));
        }
    }
}