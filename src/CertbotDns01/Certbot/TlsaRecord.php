<?php

namespace RoyBongers\CertbotDns01\Certbot;

use Stayallive\TLSA\Builder;

class TlsaRecord
{
    private Builder $tlsaBuilder;

    public function __construct(
        string $url,
        string $protocol,
        string $pemCertificate,
        int $usage = Builder::CERTIFICATE_USAGE_DOMAIN_ISSUED_CERTIFICATE,
        int $selector = Builder::SELECTOR_PUBLIC_KEY,
        int $matchingType = Builder::MATCHING_TYPE_SHA256
    ) {
        $tlsaBuilder = new Builder($url, $protocol);

        $tlsaBuilder->forCertificate($pemCertificate);
        $tlsaBuilder->certificateUsage($usage);
        $tlsaBuilder->selector($selector);
        $tlsaBuilder->matchingType($matchingType);

        $this->tlsaBuilder = $tlsaBuilder;
    }

    public function getName(): string
    {
        return $this->tlsaBuilder->getRecordDNSName();
    }

    public function getFullName(): string
    {
        return $this->tlsaBuilder->getRecordFullDNSName();
    }

    public function getContent(): string
    {
        return $this->tlsaBuilder->getRecordContents();
    }
}
