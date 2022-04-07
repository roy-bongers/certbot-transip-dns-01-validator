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
        $tlsaBuilder = new Builder($url, $protocol); // Builder for the alexbouma.me domain, port 25 and the UDP protocol

        $tlsaBuilder->forCertificate($pemCertificate);
        $tlsaBuilder->certificateUsage($usage); // Set the certificate usage to `3` (default)
        $tlsaBuilder->selector($selector); // Set the selector to `1` (default)
        $tlsaBuilder->matchingType($matchingType); // Set the matching type to `1` (default)

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
