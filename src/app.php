<?php

use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\Exceptions\ProviderNotFoundException;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;
use RoyBongers\CertbotDns01\Providers\TransIp\TransIp;

// define all available providers
$providers = [
    'transip' => TransIp::class,
];

// build dependency injection container
$builder = new ContainerBuilder();
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
                if (LogLevel::DEBUG === $loglevel) {
                    $formatter->includeStacktraces();
                }

                $handlers = [
                    (new StreamHandler('php://stdout', $loglevel))->setFormatter($formatter),
                    (new StreamHandler('php://stderr', LogLevel::ERROR))->setFormatter($formatter),
                ];
                if (null !== $logfile) {
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
