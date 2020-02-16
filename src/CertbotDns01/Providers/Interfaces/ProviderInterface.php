<?php

namespace RoyBongers\CertbotDns01\Providers\Interfaces;

use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;

interface ProviderInterface
{
    public function createChallengeDnsRecord(ChallengeRecord $challengeRecord): void;

    public function cleanChallengeDnsRecord(ChallengeRecord $challengeRecord): void;

    public function getDomainNames(): array;
}
