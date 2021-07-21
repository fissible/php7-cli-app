<?php declare(strict_types=1);

namespace PhpCli\Traits;

trait HasConfig
{
    protected array $config;

    /**
     * Throw an exception if a config key is missing.
     * 
     * @param string $key
     * @return void
     */
    protected function requireConfigKey(string $key): void
    {
        if (false !== strpos($key, '|')) {
            $ors = explode('|', key);
            $exists = array_map(function ($key) {
                return isset($this->config[$key]);
            }, $ors);

            if (count($ors) < 1) {
                throw new ConfigurationException('', $key);
            }
        } elseif (false !== strpos($key, ',')) {
            $ands = explode(',', key);
            foreach ($ands as $key) {
                $this->requireConfigKey($key);
            }
        } else {
            if (!isset($this->config[$key])) {
                throw new ConfigurationException('', $key);
            }
        }
    }
}