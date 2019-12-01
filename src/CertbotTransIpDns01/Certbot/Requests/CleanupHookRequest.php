<?php
namespace RoyBongers\CertbotTransIpDns01\Certbot\Requests;

class CleanupHookRequest extends AbstractHookRequest
{
    public function getHookName(): string
    {
        return 'cleanup';
    }
}
