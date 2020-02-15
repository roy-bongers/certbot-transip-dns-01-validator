<?php

namespace RoyBongers\CertbotTransIpDns01;

use Exception;
use Monolog\Logger;
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
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;
use RoyBongers\CertbotTransIpDns01\Certbot\Requests\Interfaces\HookRequestInterface;

class Bootstrap implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var LoggerInterface $logger */
    protected $logger;

    /** @var CertbotDns01 $acme2 */
    protected $acme2;

    protected $providers = [
        'transip' => TransIp::class,
    ];

    public function __construct(HookRequestInterface $request)
    {
        $this->setUp();

        try {
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
        $config = new ConfigLoader();

        // setup TranIP API credentials.
        $login = $config->get('transip_login', $config->get('login'));
        $privateKey = $config->get('transip_private_key', $config->get('private_key'));

        Transip_ApiSettings::$login = trim($login);
        Transip_ApiSettings::$privateKey = trim($privateKey);

        // set up logging
        $loglevel = $config->get('loglevel');
        $logfile = $config->get('logfile');
        $this->initializeLogger($loglevel, $logfile);

        // initialize TransIp Class
        $provider = new $this->providers[$config->get('provider')]();
        /** @var ProviderInterface $provider */
        $provider->setLogger($this->logger);

        // initialize Certbot DNS01 challenge class.
        $this->acme2 = new CertbotDns01($provider);
        $this->acme2->setLogger($this->logger);
    }

    private function initializeLogger(string $logLevel, string $logFile = null): void
    {
        $output = '[%datetime%] %level_name%: %message%' . PHP_EOL;
        $formatter = new LineFormatter($output, 'Y-m-d H:i:s.u');

        $handlers = [
            (new StreamHandler('php://stdout', $logLevel))->setFormatter($formatter),
        ];
        if ($logFile !== null) {
            $handlers[] = (new StreamHandler($logFile, $logLevel))->setFormatter($formatter);
        }

        $logger = new Logger(
            'CertbotTransIpDns01',
            $handlers
        );

        $this->setLogger($logger);
    }
}
