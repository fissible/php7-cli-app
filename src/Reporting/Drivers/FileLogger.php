<?php declare(strict_types=1);

namespace PhpCli\Reporting\Drivers;

use PhpCli\Filesystem\File;
use PhpCli\Reporting\Logger;

class FileLogger extends Logger {

    public static string $fileExtension = 'log';

    public function __construct($Config)
    {
        parent::__construct($Config);

        $this->requireConfigKey('path');
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

        $entry = $this->format($level, $data, $prefix) . "\n";
        $File = $this->getFile($level);
        $File->write($entry, true);
    }

    public static function create($Config): Logger
    {
        return new static($Config);
    }

    /**
     * Get a File instance to write a log entry to.
     * 
     * @param string $level
     * @return File
     */
    private function getFile(string $level): File
    {
        $directoryPermissions = $this->Config->get('permissions.directory') ?? 0700;
        $filePermissions = $this->Config->get('permissions.file') ?? 0600;
        $extension = $this->Config->get('extension') ?? static::$fileExtension;
        $File = new File($this->Config->path);

        if ($File->isDir()) {
            if (!$File->exists()) {
                $File->create($directoryPermissions);
            }

            $filename = date('Y-m-d').'.'.$extension;
            if ($this->Config->get('files.'.$level)) {
                $filename = $this->Config->get('files.'.$level).'.'.$extension;
            }

            $File = new File($File->getPath().DIRECTORY_SEPARATOR.$filename);
        }

        if (!$File->exists()) {
            $File->create($filePermissions);
        }

        return $File;
    }
}