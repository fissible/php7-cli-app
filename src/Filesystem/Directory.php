<?php declare(strict_types=1);

namespace PhpCli\Filesystem;

use PhpCli\Exceptions\FileNotFoundException;

class Directory extends File
{
    public function __construct(string $path)
    {
        $this->setPath($path);
    }

    /**
     * Create a temporary File instance.
     * 
     * @return Directory
     */
    public static function temp(): Directory
    {
        $resource = tmpfile();
        $path = stream_get_meta_data($resource)['uri'];

        return new static(dirname($path));
    }

    public function canCreate(): bool
    {
        return true;
    }

    public function canRead(): bool
    {
        return true;
    }

    public function canWrite(): bool
    {
        return true;
    }

    public function canDelete(): bool
    {
        return $this->canWrite();
    }

    /**
     * Copy this Directory to the provided destination File.
     * 
     * @param File $Destination
     * @return File
     */
    public function copy(File $Destination): File
    {
        /*
            Source ($this)      Destination                     Operation
            --------------------------------------------------------------------------
       FILE /tmp/file.txt  FILE /Users/bob/files/file.txt       copy file to file
       FILE /tmp/file.txt   DIR /Users/bob/cache                copy file into directory
        DIR /tmp/logs       DIR /Users/bob/logs                 copy files in Source to directory
        DIR /tmp/logs      FILE /Users/bob/logs/today.log       ERROR

        */
        if (!$Destination->isDir()) {
            throw new \InvalidArgumentException(sprintf('Cannot copy directory %s into destination filepath %s', $this->path, $Destination->path));
        }

        // Create directories that do not exist in the destination path
        if ($Directory = $Destination->getDir()) {
            if (!$Directory->exists()) {
                $Directory->create();
            }
        }

        if (!$Destination->exists()) {
            $Destination->create($this->getPermissions());
        }

        foreach ($this->files() as $File) {
            $File->copy(new File($Destination->path . DIRECTORY_SEPARATOR . $File->filename));
        }

        return $Destination;
    }

    public function create(int $mode = null): bool
    {
        if ($this->exists()) {
            throw new \Exception(sprintf('File at path "%s" already exists.', $this->path));
        }

        if (is_null($mode) && $Ancestor = $this->getAncestor()) {
            $mode = $Ancestor->getPermissions();
        }

        $mode = $mode ?? 0777;

        if (mkdir($this->path, $mode, true)) {
            clearstatcache();
            return true;
        }

        return false;
    }

    public function delete(bool $recurse = false): bool
    {
        if ($recurse) {
            foreach ($this->files() as $File) {
                $File->delete(true);
            }
        }

        if (!$this->empty()) {
            throw new \Exception(sprintf('Directory "%s" not empty.', $this->path));
        }

        if (rmdir($this->path)) {
            return true;
        }

        return false;
    }

    public function empty(): bool
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        return count($this->files()) == 0;
    }

    /**
     * @return array
     */
    public function files(): array
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        $results = [];

        foreach (scandir($this->path) as $value) {
            if ($value === "." || $value === "..") {
                continue;
            }

            $path = $this->path . DIRECTORY_SEPARATOR . $value;

            if (is_dir($path)) {
                $results[] = new Directory($path);
            } else {
                $results[] = new File($path);
            }
        }

        return $results;
    }

    /**
     * @param callable $matcher
     * @return array
     */
    public function filesMatch(callable $matcher): array
    {
        $results = array_filter($this->files(), function ($file) use ($matcher) {
            return $matcher($file) === true;
        });

        return array_values($results);
    }

    /**
     * @return string|null
     */
    public function getExtension(): ?string
    {
        return null;
    }

    public function lines()
    {
        throw new \InvalidArgumentException('This file is a directory.');
    }

    public function setMode(string $mode = null): ?string
    {
        throw new \InvalidArgumentException('This file is a directory.');
    }

    public function read(bool $asArray = false, int $offset = 0, int $length = null)
    {
        throw new \InvalidArgumentException('This file is a directory.');
    }
    
    public function truncate(): bool
    {
        throw new \InvalidArgumentException('This file is a directory.');
    }

    public function write(string $_, bool $__ = false)
    {
        throw new \Exception('Cannot write file data to a directory.');
    }

    public function __destruct()
    {
        return;
    }
}