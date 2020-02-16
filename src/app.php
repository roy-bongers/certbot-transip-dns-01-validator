<?php

use Monolog\Logger;
use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Psr\Container\ContainerInterface;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\TransIp;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;

// define all available providers
$providers = [
    'transip' => TransIp::class,
];

// build dependency injection container
$builder = new ContainerBuilder();
$builder->useAnnotations(false);
$builder->addDefinitions(
    array_map(
        function ($class) {
            return DI\get($class);
        },
        $providers
    )
);
$builder->addDefinitions(
    [
        ProviderInterface::class => DI\factory(
            function (ContainerInterface $container, Config $config) {
                return $container->get($config->get('provider'));
            }
        ),
        LoggerInterface::class => DI\factory(
            function (Config $config) {
                $output = '[%datetime%] %level_name%: %message%' . PHP_EOL;
                $formatter = new LineFormatter($output, 'Y-m-d H:i:s.u');

                $loglevel = $config->get('loglevel');
                $logfile = $config->get('logfile');

                $handlers = [
                    (new StreamHandler('php://stdout', $loglevel))->setFormatter($formatter),
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
