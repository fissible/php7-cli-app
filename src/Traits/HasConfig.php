<?php declare(strict_types=1);

namespace PhpCli\Traits;

use PhpCli\Arr;
use PhpCli\Config\JsonPointer;
use PhpCli\Config\Memory;
use PhpCli\Interfaces\Config;
use PhpCli\Exceptions\ConfigurationException;

trait HasConfig
{
    protected Config $Config;

    private array $subsets = [];

    public function config(): Config
    {
        $this->validateConfigInitialized();

        return $this->Config;
    }

    /**
     * Get a JSON Pointer 
     */
    public function configPointer(JsonPointer $Pointer = null, array $path = []): JsonPointer
    {
        $prefix = $Pointer ? $Pointer->reference : '';
        $key = '$ref';
        $pointer = new \stdClass;
        $pointer->$key = $prefix . implode('/', $path);

        return new JsonPointer($pointer);
    }

    public function isConfigured(): bool
    {
        return isset($this->Config);
    }

    public function setConfig($Config)
    {
        if (is_array($Config)) {
            $Config = Arr::toObject($Config);
        }

        if ($Config instanceof \stdClass) {
            $this->Config = new Memory();
            $this->Config->setData($Config);
        } elseif ($Config instanceof Config) {
            $this->Config = $Config;
        } else {
            throw new \InvalidArgumentException();
        }
    }

    /**
     * Throw an exception if a config key is missing.
     * 
     * @param string $key
     * @return void
     */
    protected function requireConfigKey(string $key): void
    {
        if (!isset($this->Config)) {
            throw new ConfigurationException('', $key);
        }

        if (false !== strpos($key, '|')) {
            $ors = explode('|', $key);
            $exists = array_filter(array_map(function ($key) {
                return $this->Config->has($key);
            }, $ors));

            if (count($exists) < 1) {
                throw new ConfigurationException('', $key);
            }
        } elseif (false !== strpos($key, ',')) {
            $ands = explode(',', $key);
            foreach ($ands as $key) {
                $this->requireConfigKey($key);
            }
        } else {
            if (!$this->Config->has($key)) {
                throw new ConfigurationException('', $key);
            }
        }
    }

    private function validateConfigInitialized()
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Configuration not set.');
        }
    }

    public function __get($name)
    {
        if ($name === 'config') {
            return $this->Config;
        }
        throw new \Exception(sprintf("%s: unknown property", $name));
    }
}