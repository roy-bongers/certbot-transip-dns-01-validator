<?php

namespace RoyBongers\CertbotDns01\Certbot;

use Stayallive\TLSA\Builder;

class TlsaRecord
{
    /** @var Builder */
    private $tlsaBuilder;

    public function __construct(
        int $port,
        string $protocol,
        string $domain,
        int $usage,
        int $selector,
        int $matchingType
    ) {
        $tlsaBuilder = new Builder($domain, $port, $protocol); // Builder for the alexbouma.me domain, port 25 and the UDP protocol

        $tlsaBuilder->forCertificate($pemEncodedCertificate);
        $tlsaBuilder->forPublicKey($pemEncodedPublicKey);
        $tlsaBuilder->certificateUsage(Builder::CERTIFICATE_USAGE_DOMAIN_ISSUED_CERTIFICATE); // Set the certificate usage to `3` (default)
        $tlsaBuilder->selector(Builder::SELECTOR_PUBLIC_KEY); // Set the selector to `1` (default)
        $tlsaBuilder->matchingType(Builder::MATCHING_TYPE_SHA256); // Set the matching type to `1` (default)

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
