<?php

namespace RoyBongers\CertbotDns01;

class Config
{
    private $config = [];

    public function __construct()
    {
        if (file_exists(APP_ROOT . '/config/config.php')) {
            $this->config = include(APP_ROOT . '/config/config.php');
        } elseif (file_exists(APP_ROOT . '/config/transip.php')) {
            $this->config = include(APP_ROOT . '/config/transip.php');
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

        return $default;
    }
}
