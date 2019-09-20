<?php

namespace RoyBongers\CertbotTransIpDns01;

use Monolog\Logger;
use \Transip_ApiSettings;
use Monolog\Handler\StreamHandler;
use RoyBongers\CertbotTransIpDns01\Certbot\CertbotDns01;
use RoyBongers\CertbotTransIpDns01\Providers\TransIp;

class HookLoader
{
    const LOG_FILE = 'log.txt';

    const AUTH_HOOK = 1;
    const CLEANUP_HOOK = 2;

    public function __construct(int $hook)
    {
        $this->loadConfig();
        $logger = $this->getLogger();

        $provider = new TransIp();

        $acme2 = new CertbotDns01($provider, $logger);
        if ($hook === self::AUTH_HOOK) {
            $acme2->authHook();
        }
        if ($hook === self::CLEANUP_HOOK) {
            $acme2->cleanupHook();
        }
    }

    private function loadConfig(): void
    {
        if (!file_exists('config/transip.php')) {
            throw new \Exception('Config file could not be found');
        }

        $config = include('config/transip.php');

        Transip_ApiSettings::$login = $config['login'];
        Transip_ApiSettings::$privateKey = $config['private_key'];
    }

    private function getLogger(): Logger
    {
        return new Logger('CertbotTransIpDns01', [
            new StreamHandler('php://stdout', Logger::INFO),
            new StreamHandler('log.txt', Logger::INFO),
        ]);
    }
}
