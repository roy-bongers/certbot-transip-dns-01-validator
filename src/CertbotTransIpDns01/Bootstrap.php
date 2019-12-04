<?php

namespace RoyBongers\CertbotTransIpDns01;

use Exception;
use Monolog\Logger;
use Psr\Log\LogLevel;
use RuntimeException;
use Transip_ApiSettings;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use RoyBongers\CertbotTransIpDns01\Providers\TransIp;
use RoyBongers\CertbotTransIpDns01\Certbot\CertbotDns01;
use RoyBongers\CertbotTransIpDns01\Certbot\Requests\AuthHookRequest;
use RoyBongers\CertbotTransIpDns01\Certbot\Requests\CleanupHookRequest;
use RoyBongers\CertbotTransIpDns01\Certbot\Requests\Interfaces\HookRequestInterface;

class Bootstrap implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const LOG_FILE = 'logs/certbot-transip.log';

    /** @var LoggerInterface $logger */
    protected $logger;

    /** @var CertbotDns01 $acme2 */
    protected $acme2;

    public function __construct(HookRequestInterface $request)
    {
        try {
            $this->setUp();

            if ($request instanceof AuthHookRequest) {
                $this->acme2->authHook($request);
            } elseif ($request instanceof CleanupHookRequest) {
                $this->acme2->cleanupHook($request);
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            exit(1);
        }
    }

    private function setUp(): void
    {
        $config = $this->loadConfig();

        // set up logging
        $loglevel = $config['loglevel'] ?? LogLevel::INFO;
        $logfile = $config['logfile'] ?? self::LOG_FILE;
        $this->initializeLogger($loglevel, $logfile);

        // setup TranIP API credentials.
        Transip_ApiSettings::$login = trim($config['login'] ?? '');
        Transip_ApiSettings::$privateKey = trim($config['private_key'] ?? '');

        // initialize TransIp Class
        $provider = new TransIp();
        $provider->setLogger($this->logger);

        // initialize Certbot DNS01 challenge class.
        $this->acme2 = new CertbotDns01($provider);
        $this->acme2->setLogger($this->logger);
    }

    private function loadConfig(): array
    {
        if (!file_exists(APP_ROOT . '/config/transip.php')) {
            throw new RuntimeException('Config file could not be found');
        }

        return include(APP_ROOT . '/config/transip.php');
    }

    private function initializeLogger(string $logLevel = LogLevel::INFO, $logFile = self::LOG_FILE): void
    {
        if (realpath($logFile) !== $logFile) {
            $logFile = APP_ROOT . DIRECTORY_SEPARATOR . ltrim($logFile, '/');
        }

        $output = '[%datetime%] %level_name%: %message%' . PHP_EOL;
        $formatter = new LineFormatter($output, 'Y-m-d H:i:s.u');

        $logger = new Logger(
            'CertbotTransIpDns01',
            [
                (new StreamHandler('php://stdout', $logLevel))->setFormatter($formatter),
                (new StreamHandler($logFile, $logLevel))->setFormatter($formatter),
            ]
        );

        $this->setLogger($logger);
    }
}
