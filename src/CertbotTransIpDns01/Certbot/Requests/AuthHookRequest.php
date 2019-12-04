<?php

namespace RoyBongers\CertbotTransIpDns01\Certbot\Requests;

class AuthHookRequest extends AbstractHookRequest
{
    public function getHookName(): string
    {
        return 'auth';
    }
}
