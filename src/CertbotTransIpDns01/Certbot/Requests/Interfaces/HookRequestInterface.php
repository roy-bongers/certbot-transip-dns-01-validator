<?php

namespace RoyBongers\CertbotTransIpDns01\Certbot\Requests\Interfaces;

interface HookRequestInterface
{
    public function getChallenge(): string;

    public function getDomain(): string;

    public function getHookName(): string;
}
