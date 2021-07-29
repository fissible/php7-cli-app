<?php declare(strict_types=1);

namespace PhpCli\Traits;

use PhpCli\Arr;
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

    // public function configSubset(string $configPointer): Config
    // {
    //     $this->validateConfigInitialized();

    //     if (!isset($this->subsets[$configPointer])) {
    //         $this->subsets[$configPointer] = new Config($this->Config->path, $this->Config);
    //         $this->subsets[$configPointer]->setPointer($configPointer);
    //     }

    //     return $this->subsets[$configPointer];
    // }

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
        if (!isset($this->Config)) {
            throw new \Exception('Configuration not set.');
        }
    }
}