<?php

namespace RoyBongers\CertbotDns01\Providers\Interfaces;

use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Certbot\TlsaRecord;

interface ProviderInterface
{
    /**
     * Create a TXT DNS record via the provider's API.
     */
    public function createChallengeDnsRecord(ChallengeRecord $challengeRecord): void;

    /**
     * Remove the created TXT record via the provider's API.
     */
    public function cleanChallengeDnsRecord(ChallengeRecord $challengeRecord): void;

    /**
     * Return a simple array containing the domain names that can be managed via the API.
     */
    public function getDomainNames(): iterable;

    /**
     * Return all nameservers for a given domain.
     */
    public function getNameservers(string $domainName): array;

    public function addTlsaRecord(string $domainName, TlsaRecord $tlsaBuilder, int $ttl = 300);
}
