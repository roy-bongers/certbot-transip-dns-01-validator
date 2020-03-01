<?php

use Monolog\Logger;
use Psr\Log\LogLevel;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Container\ContainerInterface;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\TransIp;
use RoyBongers\CertbotDns01\Providers\OpenProvider;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;
use RoyBongers\CertbotDns01\Providers\Exceptions\ProviderNotFoundException;

// define all available providers
$providers = [
    'transip' => TransIp::class,
    'openprovider' => OpenProvider::class,
];

// build dependency injection container
$builder = new ContainerBuilder();
$builder->useAnnotations(false);
$builder->addDefinitions(array_map('DI\get', $providers));
$builder->addDefinitions(
    [
        Config::class => DI\autowire(),
        ProviderInterface::class => DI\factory(
            function (ContainerInterface $container, Config $config) {
                $provider = strtolower($config->get('provider', 'transip'));
                if (!$container->has($provider)) {
                    throw new ProviderNotFoundException($provider);
                }

                return $container->get($provider);
            }
        ),
        LoggerInterface::class => DI\factory(
            function (Config $config) {
                $loglevel = $config->get('loglevel', LogLevel::INFO);
                $logfile = $config->get('logfile');

                $outputFormat = "[%datetime%] %level_name%: %message% %context% %extra%\n";
                $formatter = new LineFormatter($outputFormat, 'Y-m-d H:i:s.u');
                if ($loglevel === LogLevel::DEBUG) {
                    $formatter->includeStacktraces();
                }

                $handlers = [
                    (new StreamHandler('php://stdout', $loglevel))->setFormatter($formatter),
                    (new StreamHandler('php://stderr', LogLevel::ERROR))->setFormatter($formatter),
                ];
                if ($logfile !== null) {
                    $handlers[] = (new StreamHandler($logfile, $loglevel))->setFormatter($formatter);
                }

                return new Logger(
                    'CertbotTransIpDns01',
                    $handlers
                );
            }
        ),
    ]
);
$container = $builder->build();
$logger = $container->get(LoggerInterface::class);
