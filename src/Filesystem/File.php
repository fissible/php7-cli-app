<?php declare(strict_types=1);

namespace PhpCli\Filesystem;

class File {

    private string $path;

    private array $parts;

    private array $info;

    public function __construct($path)
    {
        $this->path = $path;
        $this->info = pathinfo($path);
        $this->setParts($path);
    }

    public function chmod(int $mode)
    {
        return chmod($this->path, $mode);
    }

    public function delete(): bool
    {
        return unlink($this->path);
    }

    public function exists()
    {
        return file_exists($this->path);
    }

    /**
     * @return array
     */
    public function files()
    {
        if (!$this->isDir()) {
            throw new \InvalidArgumentException('This file is not a directory.');
        }

        foreach (scandir($this->path) as $value) {
            if ($value === "." || $value === "..") {
                continue;
            }

            $File = new File($value);
            if ($File->exists()) {
                $results[] = $File;
            }
        }

        return $results;
    }

    /**
     * @param string $dir
     * @param callable $matcher
     * @return array
     */
    function filesMatch(string $dir, callable $matcher): array
    {
        $results = array_filter($this->files($dir), function ($file) use ($matcher) {
            return $matcher($file) === true;
        });

        return array_values($results);
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

    public function isDir(): bool
    {
        return is_dir($this->path);
    }

    public function setParts(string $path)
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $this->parts = array_filter(explode(DIRECTORY_SEPARATOR, $path));

        return $this;
    }

    /**
     * Get the files in a directory (recursively).
     * 
     * @param string $dir
     * @param array $results
     * @return array[File]
     */
    function scan($dir = null, &$results = array()): array
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
    function scanMatch(string $dir, callable $matcher): array
    {
        $results = array_filter($this->scan($dir), function ($file) use ($matcher) {
            return $matcher($file) === true;
        });

        return array_values($results);
    }

    public function read(bool $asArray)
    {
        if ($asArray) {
            return file($this->path, FILE_IGNORE_NEW_LINES);
        } else {
            return file_get_contents($this->path);
        }
    }

    /**
     * @param string $contents
     * @return int|false
     */
    public function write(string $contents)
    {
        return file_put_contents($this->path, $contents);
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
}