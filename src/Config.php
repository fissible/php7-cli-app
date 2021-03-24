<?php declare(strict_types=1);

namespace PhpCli;

use PhpCli\Filesystem\File;
use PhpCli\Traits\RequiresBinary;

class Config
{
    private $data = [];

    private File $File;

    public function __construct(string $path = null)
    {
        if ($path) {
            $this->setFile($path);
            if ($this->File->exists()) {
                $this->loadData();
            }
        }
    }

    public function exists(): ?bool
    {
        if (isset($this->File)) {
            return $this->File->exists();
        }
        return null;
    }

    public function getFile(): ?File
    {
        if (isset($this->File)) {
            return $this->File;
        }
        return null;
    }

    public function persist()
    {
        $contents = json_encode($this->data, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR, 256);
        return $this->File->write($contents);
    }

    public function setFile(string $filepath): self
    {
        $this->File = new File($filepath);
        return $this;
    }

    public function __get($name) 
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }
    }

    public function __set(string $name, $value) 
    {
        $this->data[$name] = $value;
    }

    private function loadData(): self
    {
        if (!isset($this->File)) {
            throw new \LogicException('File undefined.');
        }
        if (!$this->File->exists()) {
            throw new \LogicException(sprintf('File "%s" not found.', $this->File->getPath()));
        }

        $contents = $this->File->read();
        $this->data = json_decode($contents, true, 256, JSON_THROW_ON_ERROR);

        return $this;
    }
}