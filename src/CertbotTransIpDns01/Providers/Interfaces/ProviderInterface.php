<?php

namespace RoyBongers\CertbotTransIpDns01\Providers\Interfaces;

use Psr\Log\LoggerAwareInterface;

interface ProviderInterface extends LoggerAwareInterface
{
    public function createChallengeDnsRecord(string $domain, string $challengeName, string $challengeValue): void;

    public function cleanChallengeDnsRecord(string $domain, string $challengeName, string $challengeValue): void;

    public function getDomainNames(): array;
}
