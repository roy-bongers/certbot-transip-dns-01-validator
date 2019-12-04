<?php

namespace RoyBongers\CertbotTransIpDns01\Certbot\Requests;

use RoyBongers\CertbotTransIpDns01\Certbot\Requests\Interfaces\HookRequestInterface;

abstract class AbstractHookRequest implements HookRequestInterface
{
    public function getChallenge(): string
    {
        return getenv('CERTBOT_VALIDATION');
    }

    public function getDomain(): string
    {
        return getenv('CERTBOT_DOMAIN');
    }

    abstract public function getHookName(): string;
}
