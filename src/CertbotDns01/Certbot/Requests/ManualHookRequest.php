<?php

namespace RoyBongers\CertbotDns01\Certbot\Requests;

class ManualHookRequest
{
    public function getValidation(): string
    {
        return getenv('CERTBOT_VALIDATION');
    }

    public function getDomain(): string
    {
        return getenv('CERTBOT_DOMAIN');
    }

    public function remainingChallenges(): int
    {
        return getenv('CERTBOT_REMAINING_CHALLENGES');
    }

    public function allDomains(): array
    {
        return explode(',', getenv('CERTBOT_ALL_DOMAINS'));
    }
}
