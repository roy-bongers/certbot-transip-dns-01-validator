<?php

namespace RoyBongers\CertbotTransIpDns01\Providers\Interfaces;

interface ProviderInterface
{
    public function createChallengeDnsRecord(string $challengeDnsRecord, string $challenge): void;

    public function cleanChallengeDnsRecord(string $challengeDnsRecord, string $challenge): void;
}
