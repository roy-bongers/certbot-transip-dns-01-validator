<?php

namespace RoyBongers\CertbotTransIpDns01\Providers;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;
use Transip_DnsEntry;
use Transip_DomainService;
use Transip_DnsService;
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;

class TransIp implements ProviderInterface
{
    use LoggerAwareTrait;

    /** @var LoggerInterface $logger */
    protected $logger;

    /** @var array $domainNames */
    protected $domainNames = [];

    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    public function createChallengeDnsRecord(string $domain, string $challengeName, string $challengeValue): void
    {
        $dnsEntries = $this->getDnsEntries($domain);

        $challengeDnsEntry = new Transip_DnsEntry($challengeName, 60, Transip_DnsEntry::TYPE_TXT, $challengeValue);
        array_push($dnsEntries, $challengeDnsEntry);

        Transip_DnsService::setDnsEntries($domain, $dnsEntries);
    }

    public function cleanChallengeDnsRecord(string $domain, string $challengeName, string $challengeValue): void
    {
        $dnsEntries = $this->getDnsEntries($domain);

        foreach ($dnsEntries as $index => $dnsEntry) {
            if ($dnsEntry->name === $challengeName && $dnsEntry->content === $challengeValue) {
                $this->logger->info(
                    sprintf('Removing challenge DNS record(%s 60 TXT %s)', $dnsEntry->name, $dnsEntry->content)
                );
                unset($dnsEntries[$index]);
            }
        }
        $dnsEntries = array_values($dnsEntries);

        Transip_DnsService::setDnsEntries($domain, $dnsEntries);
    }

    public function getDomainNames(): array
    {
        if (empty($this->domainNames)) {
            $this->domainNames = Transip_DomainService::getDomainNames();
        }

        $this->logger->debug(sprintf('Domain names available: %s', implode(', ', $this->domainNames)));

        return $this->domainNames;
    }

    private function getDnsEntries(string $domainName): array
    {
        $dnsEntries = Transip_DomainService::getInfo($domainName)->dnsEntries;

        $this->logger->debug(sprintf('Existing DNS records for %s:', $domainName));

        foreach ($dnsEntries as $dnsEntry) {
            $this->logger->debug(
                sprintf(
                    '%s %s %s %s',
                    $dnsEntry->name,
                    $dnsEntry->expire,
                    $dnsEntry->type,
                    $dnsEntry->content
                )
            );
        }

        return $dnsEntries;
    }
}
