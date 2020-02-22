<?php

namespace RoyBongers\CertbotDns01\Providers\Interfaces;

use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;

interface ProviderInterface
{
    /**
     * Create a TXT DNS record via the provider's API.
     *
     * @param  ChallengeRecord  $challengeRecord
     */
    public function createChallengeDnsRecord(ChallengeRecord $challengeRecord): void;

    /**
     * Remove the created TXT record via the provider's API.
     *
     * @param  ChallengeRecord  $challengeRecord
     */
    public function cleanChallengeDnsRecord(ChallengeRecord $challengeRecord): void;

    /**
     * Return a simple array containing the domain names that can be managed via the API.
     *
     * @return iterable
     */
    public function getDomainNames(): iterable;
}
