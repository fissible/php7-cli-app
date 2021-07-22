<?php declare(strict_types=1);

namespace PhpCli\Reporting\Drivers;

use PhpCli\Output;
use PhpCli\Reporting\Logger;

class StandardLogger extends Logger {

    public Output $output;

    public function __construct(array $config)
    {
        parent::__construct($config);

        $this->output = new Output();
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

        $entry = $this->format($level, $data, $prefix);

        switch ($level) {
            case LOGGER::FATAL:
            case LOGGER::ERROR:
                $this->output->error($entry);
            break;
            default:
                $this->output->line($entry);
            break;
        }
    }

    public static function create(array $config): Logger
    {
        return new StandardLogger($config);
    }
}