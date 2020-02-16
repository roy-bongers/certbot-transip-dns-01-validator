<?php

namespace RoyBongers\CertbotDns01;

use Psr\Log\LogLevel;
use RuntimeException;

class Config
{
    private $config = [];

    private $requires = [
        'login',
        'private_key',
    ];

    private $defaults = [
        'provider' => 'transip',
        'loglevel' => LogLevel::INFO,
    ];

    public function __construct()
    {
        if (file_exists(APP_ROOT . '/config/config.php')) {
            $this->config = include(APP_ROOT . '/config/config.php');
        } elseif (file_exists(APP_ROOT . '/config/transip.php')) {
            $this->config = include(APP_ROOT . '/config/transip.php');
        }

        foreach ($this->requires as $requiredConfigKey) {
            $requiredConfigKeyWithProvider = $this->get('provider') . '_' . $requiredConfigKey;
            if ($this->get($requiredConfigKeyWithProvider, $this->get($requiredConfigKey)) === null) {
                throw new RuntimeException(sprintf("Config option '%s' not found", $requiredConfigKey));
            }
        }
    }

    public function get(string $key, $default = null): ?string
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        $envValue = getenv(strtoupper($key));
        if ($envValue !== false) {
            return $envValue;
        }

        return $default ?? $this->defaults[$key] ?? null;
    }
}
