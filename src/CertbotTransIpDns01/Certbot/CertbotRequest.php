<?php
namespace RoyBongers\CertbotTransIpDns01\Certbot;

class CertbotRequest
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
