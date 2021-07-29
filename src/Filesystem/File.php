<?php declare(strict_types=1);

namespace PhpCli\Filesystem;

use PhpCli\Exceptions\FileNotFoundException;
use PhpCli\Exceptions\InvalidFileModeException;

class File {

    private string $mode;

    private string $path;

    private array $parts;

    private array $info;

    private $resource;

    private File $Parent;

    /**
     * Creates/writes
     * 
     * @must_not_exist
     * @alias CREATE_WRITE_ONLY / 'x'
     */
    public const CREATE = 'X';

    /**
     * Read only
     * 
     * @must_exist
     * @alias EXISTS_READ_ONLY / 'r'
     */
    public const READ_ONLY = 'R';

    /**
     * Write only
     * 
     * @appends
     * @alias WRITE_ONLY_APPEND / 'a'
     */
    public const WRITE_ONLY = 'W';

    /**
     * Read and write
     * 
     * @appends
     * @alias READ_WRITE_APPEND / 'a+'
     */
    public const READ_WRITE = 'RW';

    /**
     * Reads
     * 
     * @must_exist
     */
    public const EXISTS_READ_ONLY = 'r';

    /**
     * Reads/writes
     * 
     * @prepends
     * @must_exist
     */
    public const EXISTS_READ_WRITE_APPEND = 'r+';

    /**
     * Creates/writes
     * 
     * @truncates
     */
    public const CREATE_TRUNCATE_WRITE_ONLY = 'w';

    /**
     * Creates/reads/writes
     * 
     * @truncates
     */
    public const CREATE_TRUNCATE_READ_WRITE = 'w+';
    
    /**
     * Creates/writes
     * 
     * @appends
     */
    public const WRITE_ONLY_APPEND = 'a';

    /**
     * Creates/reads/writes
     * 
     * @appends
     */
    public const READ_WRITE_APPEND = 'a+';

    /**
     * Creates/writes
     * 
     * @must_not_exist
     */
    public const CREATE_WRITE_ONLY = 'x';

    /**
     * Creates/reads/writes
     * 
     * @must_not_exist
     */
    public const CREATE_READ_WRITE = 'x+';

    /**
     * Creates/writes
     * 
     * @prepends
     */
    public const CREATE_WRITE_ONLY_PREPEND = 'c';

    /**
     * Creates/reads/writes
     * 
     * @prepends
     */
    public const CREATE_READ_WRITE_PREPEND = 'c+';


    public function __construct(string $path, string $mode = self::READ_WRITE, $resource = null)
    {
        $this->setPath($path);
        $this->setMode($mode);

        if (!is_null($resource)) {
            $this->setResource($resource);
        }
    }

    /**
     * Create a temporary File instance.
     * 
     * @return File
     */
    public static function temp(): File
    {
        $resource = tmpfile();
        $path = stream_get_meta_data($resource)['uri'];

        return new static($path, self::CREATE_TRUNCATE_READ_WRITE, $resource);
    }

 /*
    CREATE = 'X'
    READ_ONLY = 'R'
    WRITE_ONLY = 'W'
    READ_WRITE = 'RW'
    EXISTS_READ_ONLY = 'r'
    EXISTS_READ_WRITE_APPEND = 'r+'
    CREATE_TRUNCATE_WRITE_ONLY = 'w'
    CREATE_TRUNCATE_READ_WRITE = 'w+'
    WRITE_ONLY_APPEND = 'a'
    READ_WRITE_APPEND = 'a+'
    CREATE_WRITE_ONLY = 'x'
    CREATE_READ_WRITE = 'x+'
    CREATE_WRITE_ONLY_PREPEND = 'c'
    CREATE_READ_WRITE_PREPEND = 'c+'
*/

    public function canCreate(): bool
    {
        return in_array($this->mode, [
            self::CREATE,
            self::CREATE_TRUNCATE_WRITE_ONLY,
            self::CREATE_TRUNCATE_READ_WRITE,
            self::WRITE_ONLY_APPEND,
            self::READ_WRITE_APPEND,
            self::CREATE_WRITE_ONLY,
            self::CREATE_READ_WRITE,
            self::CREATE_WRITE_ONLY_PREPEND,
            self::CREATE_READ_WRITE_PREPEND
        ]);
    }

    public function canRead(): bool
    {
        return in_array($this->mode, [
            self::READ_ONLY,
            self::READ_WRITE,
            self::EXISTS_READ_ONLY,
            self::EXISTS_READ_WRITE_APPEND,
            self::CREATE_TRUNCATE_READ_WRITE,
            self::READ_WRITE_APPEND,
            self::CREATE_READ_WRITE,
            self::CREATE_READ_WRITE_PREPEND
        ]);
    }

    public function canWrite(): bool
    {
        return in_array($this->mode, [
            self::WRITE_ONLY,
            self::READ_WRITE,
            self::EXISTS_READ_WRITE_APPEND,
            self::CREATE_TRUNCATE_WRITE_ONLY,
            self::CREATE_TRUNCATE_READ_WRITE,
            self::WRITE_ONLY_APPEND,
            self::READ_WRITE_APPEND,
            self::CREATE_WRITE_ONLY,
            self::CREATE_READ_WRITE,
            self::CREATE_WRITE_ONLY_PREPEND,
            self::CREATE_READ_WRITE_PREPEND
        ]);
    }

    public function canDelete(): bool
    {
        return $this->canWrite();
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

    /**
     * Copy this File to the provided destination File.
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
        if ($this->isDir() && !$Destination->isDir()) {
            throw new \InvalidArgumentException(sprintf('Cannot copy directory %s into destination filepath %s', $this->path, $Destination->path));
        }

        // Create directories that do not exist in the destination path
        if ($Directory = $Destination->getDir()) {
            if (!$Directory->exists()) {
                $Directory->create();
            }
        }

        switch (true) {
            // FILE to DIR
            case (!$this->isDir() && $Destination->isDir()):
                if (!$Destination->exists()) {
                    $Destination->create(0777);
                }
                $Destination = new File($Destination->path . DIRECTORY_SEPARATOR . $this->getFilename());
                break;
            // DIR to DIR
            case ($this->isDir() && $Destination->isDir()):
                if (!$Destination->exists()) {
                    $Destination->create($this->getPermissions());
                }

                foreach ($this->files() as $File) {
                    $File->copy(new File($Destination->path . DIRECTORY_SEPARATOR . $File->filename));
                }

                return $Destination;
                break;
        }

        if ($Destination->exists()) {
            if (!in_array($Destination->mode, [self::CREATE_TRUNCATE_WRITE_ONLY, self::CREATE_TRUNCATE_READ_WRITE])) {
                throw new InvalidFileModeException($Destination->path, $Destination->mode, 'Error overwriting file');
            }
        } elseif (!$Destination->canWrite()) {
            throw new InvalidFileModeException($Destination->path, $Destination->mode, 'Error copying file');
        }

        if (!copy($this->path, $Destination->path)) {
            throw new \Exception(sprintf('%s: error copying to "%s".', $Destination->path));
        }

        return $Destination;
    }

    public function create(int $mode = null): bool
    {
        if (!$this->canCreate()) {
            throw new InvalidFileModeException($this->path, $this->mode, 'Error creating file');
        }

        if ($this->exists()) {
            throw new \Exception(sprintf('File at path "%s" already exists.', $this->path));
        }

        if (is_null($mode) && $Ancestor = $this->getAncestor()) {
            $mode = $Ancestor->getPermissions();
        }

        $mode = $mode ?? 0777;
        $created = false;

        if ($this->isDir()) {
            if ($created = mkdir($this->path, $mode, true)) {
                clearstatcache();
                $this->setMode();
            }
        } elseif (touch($this->path)) {
            $created = true;
            $this->setMode();

            if (!$this->chmod($mode)) {
                throw new \Exception('%s: error setting permissions to %o', $mode);
            }
        }

        return $created;
    }

    public function delete(bool $recurse = false): bool
    {
        if (!$this->canDelete()) {
            throw new InvalidFileModeException($this->path, $this->mode, 'Error deleting file');
        }

        if ($this->isDir() && $recurse) {
            foreach ($this->files() as $File) {
                $File->delete(true);
            }
        }

        if ($this->isDir() && !$this->empty()) {
            throw new \Exception(sprintf('Directory "%s" not empty.', $this->path));
        }

        $deleted = $this->isDir() ? rmdir($this->path) : unlink($this->path);

        if ($deleted) {
            $this->setMode();
        }

        return $deleted;
    }

    public function empty(): bool
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        if ($this->isDir()) {
            return count($this->files()) == 0;
        }
        return count($this->read(true)) == 0;
    }

    public function exists(): bool
    {
        if (!isset($this->path)) return false;
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
     * Get this file's directory.
     * 
     * @reutrn File|null
     */
    public function getDir(): ?File
    {
        $dirname = dirname($this->path);
        if ($dirname !== $this->path) {
            return new File($dirname);
        }
        return null;
    }

    /**
     * Same as getDir() unless the file does not exist, then it gets the closest existing ancestor.
     * 
     * @return File|null
     */
    public function getAncestor(): ?File
    {
        $Ancestor = $this->getDir();
        while ($Ancestor instanceof File && !$Ancestor->exists()) {
            $Ancestor = $Ancestor->getDir();
        }

        return $Ancestor;
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
        if (isset($this->parts)) {
            $filename = end($this->parts) ?: null;
            reset($this->parts);

            return $filename;
        }
        return basename($this->path);
    }

    public function getMime(): string
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }
        return mime_content_type($this->path);
    }

    public function getMode(): string
    {
        $this->setMode();
        return $this->mode;
    }

    public function getName(): ?string
    {
        return $this->getFilename();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the containing directory as a File instance.
     * 
     * @return File|null
     */
    public function getParent(): ?File
    {
        if (!isset($this->Parent)) {
            if ($DirectoryFile = $this->getDir()) {
                $this->Parent = $DirectoryFile;
            }
        }

        return $this->Parent ?? null;
    }

    /**
     * Returns octal permissions, eg. 33261
     * 
     * @return int
     */
    public function getPermissions(): int
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }
        return fileperms($this->path);
    }

    /**
     * Returns string permissions, eg. "755"
     * 
     * @return string
     */
    public function getPermissionsString(): string
    {
        return decoct(fileperms($this->path) & 0777);
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

    public function isReadOnly(): bool
    {
        return in_array($this->mode, [
            self::READ_ONLY,
            self::EXISTS_READ_ONLY,
            self::CREATE_WRITE_ONLY,
            self::CREATE_WRITE_ONLY_PREPEND
        ]);
    }

    public function isWriteOnly(): bool
    {
        return in_array($this->mode, [
            self::WRITE_ONLY,
            self::CREATE_TRUNCATE_WRITE_ONLY,
            self::WRITE_ONLY_APPEND
        ]);
    }

    public function isReadWrite(): bool
    {
        return in_array($this->mode, [
            self::READ_WRITE,
            self::EXISTS_READ_WRITE_APPEND,
            self::CREATE_TRUNCATE_READ_WRITE,
            self::READ_WRITE_APPEND,
            self::CREATE_READ_WRITE,
            self::CREATE_READ_WRITE_PREPEND
        ]);
    }

    /**
     * Files lines generator.
     * 
     * @yields string
     */
    public function lines()
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        if ($this->isDir()) {
            throw new \InvalidArgumentException('This file is a directory.');
        }

        if ($this->isWriteOnly() || !$this->canRead()) {
            throw new InvalidFileModeException($this->path, $this->mode, 'Error reading file');
        }

        $previousMode = $this->mode ?? null;
        $opened = $this->open(self::EXISTS_READ_ONLY);
        rewind($this->resource);

        while (false !== $line = fgets($this->resource)) {
            yield rtrim($line, "\r\n");
        }

        if ($opened) {
            $this->close();
        } elseif ($previousMode !== $this->mode) {
            $this->setMode($previousMode);
        }
    }

    /**
     * Get a File object for the provided path relative to this file.
     */
    public function path(string $relative): ?string
    {
        $thisPath = $this->isDir() ? $this->path : dirname($this->path);
        $path = ltrim($relative, DIRECTORY_SEPARATOR);
        $up = 0;
        
        if (substr($path, 0, 2) === '.'.DIRECTORY_SEPARATOR) {
            $path = substr($path, 2);
            $up =+ 0;
        } elseif (substr($path, 0, 3) === '..'.DIRECTORY_SEPARATOR) {
            while (substr($path, 0, 3) === '..'.DIRECTORY_SEPARATOR) {
                $path = substr($path, 3);
                $up =+ 1;
            }
        }

        $File = $this;
        while ($up > 0 && $File = $File->getDir()) {
            $thisPath = $File->getPath();
            $up--;
        }
        
        $_path = realpath($thisPath.DIRECTORY_SEPARATOR.$path);

        if (false === $_path) {
            throw new FileNotFoundException($thisPath.DIRECTORY_SEPARATOR.$path);
        }
        
        return $_path;
    }

    public function rename(string $newFilename): bool
    {
        if ($this->isReadOnly() || !$this->canWrite()) {
            throw new InvalidFileModeException($this->path, $this->mode, 'Error renaming file');
        }

        $path = dirname($this->path).DIRECTORY_SEPARATOR.basename($newFilename);
        $renamed = true;

        if (!$this->exists() || $renamed = rename($this->path, $path)) {
            $this->setPath($path);
        }

        return $renamed;
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

    /**
     * Set the file mode for reading/writing operations.
     * 
     *  Mode   Creates  Reads   Writes  Pointer-Starts  Truncates-File  Notes                           Purpose
     *  r               y               beginning                       fails if file doesn't exist     basic read existing file
     *  r+              y       y       beginning                       fails if file doesn't exist     basic r/w existing file
     *  w       y               y       beginning+end   y                                               create, erase, write file
     *  w+      y       y       y       beginning+end   y                                               create, erase, write file with read option
     *  a       y               y       end                                                             write from end of file, create if needed
     *  a+      y       y       y       end                                                             write from end of file, create if needed, with read options
     *  x       y               y       beginning                       fails if file exists            like w, but prevents over-writing an existing file
     *  x+      y       y       y       beginning                       fails if file exists            like w+, but prevents over writing an existing file
     *  c       y               y       beginning                                                       open/create a file for writing without deleting current content
     *  c+      y       y       y       beginning                                                       open/create a file that is read, and then written back down
     * 
     * @param string $mode
     * @return string
     */
    public function setMode(string $mode = null): ?string
    {
        $exists = $this->exists();
        $current = $this->mode ?? null;

        switch ($mode) {
            case self::CREATE:
                $mode = self::CREATE_WRITE_ONLY;
                break;
            case self::READ_ONLY:
                $mode = self::EXISTS_READ_ONLY;
                break;
            case self::WRITE_ONLY:
                $mode = self::WRITE_ONLY_APPEND;
                break;
            case self::READ_WRITE:
                $mode = self::READ_WRITE_APPEND;
                break;
        }

        if (is_null($mode)) {
            $mode = $current ?? self::READ_WRITE_APPEND;

            if ($exists && $current === self::CREATE_WRITE_ONLY) {
                $mode = self::WRITE_ONLY_APPEND;
            }
            
            if ($exists && $current === self::CREATE_READ_WRITE) {
                $mode = self::READ_WRITE_APPEND;
            }

            if (!$exists && $current === self::EXISTS_READ_ONLY) {
                $mode = self::CREATE_TRUNCATE_WRITE_ONLY;
            }

            if (!$exists && $current === self::EXISTS_READ_WRITE_APPEND) {
                $mode = self::CREATE_READ_WRITE_PREPEND;
            }
        } else {
            if ($exists && in_array($mode, [self::CREATE_WRITE_ONLY, self::CREATE_READ_WRITE])) {
                throw new \Exception(sprintf('%s: already exists', $this->path));
            }
            
            if (!$exists && in_array($mode, [self::EXISTS_READ_ONLY, self::EXISTS_READ_WRITE_APPEND])) {
                throw new \Exception(sprintf('%s: does not exist', $this->path));
            }
        }

        $this->mode = $mode;

        return $current;
    }

    public function read(bool $asArray = false, int $offset = 0, int $length = null)
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        if ($this->isDir()) {
            throw new \InvalidArgumentException('This file is a directory.');
        }

        if ($this->isWriteOnly() || !$this->canRead()) {
            throw new InvalidFileModeException($this->path, $this->mode, 'Error reading file');
        }

        if ($asArray) {
            return file($this->path, FILE_IGNORE_NEW_LINES);
        } elseif (isset($this->resource) && $offset === 0 && is_null($length)) {
            // rewind($this->resource);
            $size = filesize($this->path);

            if ($size > 0) {
                return fread($this->resource, filesize($this->path));
            }
            return '';
        }

        if (is_null($length)) {
            return file_get_contents($this->path, false, null, $offset);
        }

        return file_get_contents($this->path, false, null, $offset, $length);
    }

    public function truncate(): bool
    {
        if (!$this->exists()) {
            throw new FileNotFoundException($this->path);
        }

        if ($this->isDir()) {
            throw new \InvalidArgumentException('This file is a directory.');
        }

        if ($this->isReadOnly() || !$this->canWrite()) {
            throw new InvalidFileModeException($this->path, $this->mode, 'Error truncating file');
        }

        $previousMode = $this->mode ?? null;
        $opened = $this->open(self::EXISTS_READ_WRITE_APPEND);
        $truncated = ftruncate($this->resource, 0);
        rewind($this->resource);

        if ($opened) {
            $this->close();
        } elseif ($previousMode !== $this->mode) {
            $this->setMode($previousMode);
        }

        return $truncated;
    }

    /**
     * @param string $contents
     * @param bool $append
     * @return int|false
     */
    public function write(string $contents, bool $append = false)
    {
        if ($this->isDir()) {
            throw new \InvalidArgumentException('This file is a directory.');
        }

        if ($this->isReadOnly() || !$this->canWrite()) {
            throw new InvalidFileModeException($this->path, $this->mode, 'Error writing to file');
        }

        $written = file_put_contents($this->path, $contents, $append ? FILE_APPEND : 0);

        if (isset($this->resource)) {
            clearstatcache();
        }

        return $written;
    }


    public function __get($name)
    {
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
        $this->close();
    }

    /**
     * Close the file if opened.
     * 
     * @return bool
     */
    private function close(): bool
    {
        if (isset($this->resource) && is_resource($this->resource)) {
            return fclose($this->resource);
        }
        return false;
    }

    /**
     * Open the file in the given mode.
     * 
     * @param string $mode
     * @return bool
     */
    private function open(string $mode): bool
    {
        if (isset($this->resource)) {
            $this->setMode($mode);
            return false;
        }

        if (!$resource = fopen($this->path, $mode)) {
            throw new \Exception(sprintf('Could not open file %s (in mode %s).', $this->path, $mode));
        }

        $this->setResource($resource);

        return true;

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

    /**
     * Set the resource for this File.
     * 
     * @param resource $resource
     */
    private function setResource($resource): self
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException();
        }

        $this->resource = $resource;

        return $this;
    }
}