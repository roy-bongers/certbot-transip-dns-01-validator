<?php

namespace RoyBongers\CertbotDns01\Providers\Interfaces;

interface ProviderInterface
{
    public function createChallengeDnsRecord(string $domain, string $challengeName, string $challengeValue): void;

    public function cleanChallengeDnsRecord(string $domain, string $challengeName, string $challengeValue): void;

    public function getDomainNames(): array;
}
