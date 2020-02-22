<?php

namespace RoyBongers\CertbotDns01\Providers\Exceptions;

use InvalidArgumentException;

class ProviderNotFoundException extends InvalidArgumentException
{
    public function __construct(string $provider)
    {
        parent::__construct(sprintf("Provider '%s' not found", $provider));
    }
}
