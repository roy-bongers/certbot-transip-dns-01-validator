<?php

namespace RoyBongers\CertbotTransIpDns01\Providers;

use Transip_DnsEntry;
use Transip_DomainService;
use Transip_DnsService;
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;

class TransIp implements ProviderInterface
{
    /** @var array $dnsEntries */
    protected $dnsEntries = [];

    /** @var array $domainNames */
    protected $domainNames = [];

    public function createChallengeDnsRecord(string $challengeDnsRecord, string $challenge): void
    {
        $domain = $this->resolveDomain($challengeDnsRecord);
        $dnsEntries = $this->getDnsEntries($domain['domain']);

        $challengeDnsRecord = $domain['subdomain'];
        $challengeDnsEntry = new Transip_DnsEntry($challengeDnsRecord, 60, Transip_DnsEntry::TYPE_TXT, $challenge);
        array_push($dnsEntries, $challengeDnsEntry);

        Transip_DnsService::setDnsEntries($domain['domain'], $dnsEntries);
    }

    public function cleanChallengeDnsRecord(string $challengeDnsRecord, string $challenge): void
    {
        $domain = $this->resolveDomain($challengeDnsRecord);
        $dnsEntries = $this->getDnsEntries($domain['domain']);

        foreach ($dnsEntries as $index => $dnsEntry) {
            if ($dnsEntry->name === $domain['subdomain'] && $dnsEntry->content === $challenge) {
                log_msg(sprintf('Removing challenge DNS record(%s 60 TXT %s)', $dnsEntry->name, $dnsEntry->content));
                unset($dnsEntries[$index]);
            }
        }

        $this->dnsEntries = array_values($dnsEntries);
        Transip_DnsService::setDnsEntries($domain['domain'], $dnsEntries);
    }

    private function getDnsEntries(string $domainName): array
    {
        return Transip_DomainService::getInfo($domainName)->dnsEntries;
    }

    private function getDomainNames(): array
    {
        if (empty($this->domainNames)) {
            $this->domainNames = Transip_DomainService::getDomainNames();
        }

        return $this->domainNames;
    }

    private function resolveDomain(string $fullyQualifiedDomainName): array
    {
        $domains = $this->getDomainNames();
        $domains = implode('|', array_map('preg_quote', $domains));

        if (1 !== preg_match('/^((.*)\.)?('.$domains.')$/', $fullyQualifiedDomainName, $matches)) {
            log_msg(sprintf('Can\'t manage DNS for given domain (%s).', $fullyQualifiedDomainName));
            exit(1);
        }

        return [
            'domain'    => $matches[3],
            'subdomain' => $matches[2],
        ];
    }
}
