<?php

namespace RoyBongers\CertbotDns01\Certbot\Requests;

class ManualHookRequest
{
    public function getChallenge(): string
    {
        return getenv('CERTBOT_VALIDATION');
    }

    public function getDomain(): string
    {
        return getenv('CERTBOT_DOMAIN');
    }
}
