<?php declare(strict_types=1);

namespace PhpCli\Traits;

use PhpCli\Config;

trait HasConfig
{
    protected Config $Config;

    public function setConfig(array $config)
    {
        $this->Config = new Config();
        $this->Config->setData($config);
    }

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
                return $this->Config->has($key);
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
            if (!$this->Config->has($key)) {
                throw new ConfigurationException('', $key);
            }
        }
    }
}