<?php declare(strict_types=1);

namespace PhpCli\Filesystem;

use PhpCli\Exceptions\FileNotFoundException;

class File {

    private string $path;

    private array $parts;

    private array $info;

    private $resource;

    public function __construct($path)
    {
        $this->setPath($path);
    }

    public function chmod(int $mode): bool
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        $changed = chmod($this->path, $mode);
        clearstatcache();

        return $changed;
    }

    public function create(int $mode = 0777): bool
    {
        if ($this->exists()) {
            throw new \Exception(sprintf('File at path "%s" already exists.', $this->path));
        }

        if ($this->isDir()) {
            return mkdir($this->path, $mode);
        } elseif (touch($this->path)) {
            return $this->chmod($mode);
        }
        return false;
    }

    public function delete(): bool
    {
        return unlink($this->path);
    }

    public function exists(): bool
    {
        return file_exists($this->path);
    }

    /**
     * @return array
     */
    public function files(): array
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        if (!$this->isDir()) {
            throw new \InvalidArgumentException('This file is not a directory.');
        }

        $results = [];

        foreach (scandir($this->path) as $value) {
            if ($value === "." || $value === "..") {
                continue;
            }

            $File = new File($this->path.DIRECTORY_SEPARATOR.$value);
            if ($File->exists()) {
                $results[] = $File;
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
        if (isset($this->info['extension'])) {
            return $this->info['extension'];
        }
        return null;
    }

    public function getFilename(): ?string
    {
        $filename = end($this->parts) ?: null;
        reset($this->parts);
        return $filename;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string|null
     */
    public function getPermissions(): ?string
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }
        return substr(sprintf('%o', fileperms($this->path)), -4);
    }

    public function info(): array
    {
        return $this->info;
    }

    public function isDir(): bool
    {
        if (!$this->exists()) {
            return !isset($this->info['extension']);
        }
        return is_dir($this->path);
    }

    /**
     * Files lines generator.
     */
    public function lines()
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        $this->resource = fopen($this->path, 'r');
        if (!$this->resource) throw new \Exception(sprintf('Could not open file %s.', $this->path));

        while (false !== $line = fgets($this->resource)) {
            yield rtrim($line, "\r\n");
        }

        fclose($this->resource);
    }

    /**
     * Get the files in a directory (recursively).
     * 
     * @param string $dir
     * @param array $results
     * @return array[File]
     */
    public function scan($dir = null, &$results = array()): array
    {
        $path = rtrim($dir ?? $this->info['dirname'], DIRECTORY_SEPARATOR);

        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Path "%s" is not a directory.', $path));
        }

        $files = scandir($path);

        foreach ($files as $value) {
            if ($value === "." && $value === "..") {
                continue;
            }

            $File = new File($value);
            if ($File->exists()) {
                if ($File->isDir()) {
                    $this->scan($value, $results);
                }
                $results[] = $File;
            }
        }

        return $results;
    }

    /**
     * @param string $dir
     * @param callable $matcher
     * @return array[File]
     */
    public function scanMatch(string $dir, callable $matcher): array
    {
        $results = array_filter($this->scan($dir), function ($file) use ($matcher) {
            return $matcher($file) === true;
        });

        return array_values($results);
    }

    public function read(bool $asArray = false)
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        if ($asArray) {
            return file($this->path, FILE_IGNORE_NEW_LINES);
        } else {
            return file_get_contents($this->path);
        }
    }

    /**
     * @param string $contents
     * @param bool $append
     * @return int|false
     */
    public function write(string $contents, bool $append = false)
    {
        return file_put_contents($this->path, $contents, $append ? FILE_APPEND : 0);
    }


    public function __get($name): ?string
    {
        // $this->full_path -> $this->getFullPath()
        if (false !== strpos($name, '_')) {
            $name = implode(array_map(function (string $part) {
                return ucfirst(strtolower($part));
            }, explode('_', $name)));
        }
        $method = 'get' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func([$this, $method]);
        }

        return null;
    }

    public function __destruct()
    {
        if (isset($this->resource) && is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    /**
     * @param string $path
     * @return self
     */
    private function setPath(string $path): self
    {
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
        $this->info = pathinfo($path);
        $this->parts = array_filter(explode(DIRECTORY_SEPARATOR, $this->path));

        return $this;
    }
}