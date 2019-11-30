<?php

namespace RoyBongers\CertbotTransIpDns01;

use Exception;
use Monolog\Logger;
use Psr\Log\LogLevel;
use \RuntimeException;
use \Transip_ApiSettings;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use RoyBongers\CertbotTransIpDns01\Providers\TransIp;
use RoyBongers\CertbotTransIpDns01\Certbot\CertbotDns01;

class HookLoader
{
    public const AUTH_HOOK = 'auth';

    public const CLEANUP_HOOK = 'cleanup';

    public const LOG_FILE = 'certbot-transip.log';

    /** @var LoggerInterface $logger */
    protected $logger;

    /** @var CertbotDns01 $acme2 */
    protected $acme2;

    public function __construct()
    {
        $this->setUp();
    }

    public function runHook(string $hook): void
    {
        try {
            if ($hook === self::AUTH_HOOK) {
                $this->acme2->authHook();
            } elseif ($hook === self::CLEANUP_HOOK) {
                $this->acme2->cleanupHook();
            }
        }
        catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
            exit(1);
        }
    }

    private function setUp(): void
    {
        $config = $this->loadConfig();

        // set up logging
        $loglevel     = $config['loglevel'] ?? LogLevel::INFO;
        $this->logger = $this->getLogger($loglevel);

        // setup TranIP API credentials.
        Transip_ApiSettings::$login = trim($config['login'] ?? '');
        Transip_ApiSettings::$privateKey = trim($config['private_key'] ?? '');

        // initialize Certbot DNS01 challenge class.
        $provider    = new TransIp($this->logger);
        $this->acme2 = new CertbotDns01($provider, $this->logger);
    }

    private function loadConfig(): array
    {
        if (!file_exists('config/transip.php')) {
            throw new RuntimeException('Config file could not be found');
        }

        return include('config/transip.php');
    }

    private function getLogger(string $logLevel = LogLevel::INFO, $logFile = self::LOG_FILE): Logger
    {
        return new Logger(
            'CertbotTransIpDns01',
            [
                new StreamHandler('php://stdout', $logLevel),
                new StreamHandler($logFile, $logLevel),
            ]
        );
    }
}
