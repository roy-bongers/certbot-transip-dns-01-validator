<?php

namespace RoyBongers\CertbotDns01\Providers\TransIp;

use Psr\Log\LoggerInterface;
use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Certbot\TlsaRecord;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;
use Transip\Api\Library\Entity\Domain\DnsEntry;
use Transip\Api\Library\Entity\Domain\Nameserver;
use Transip\Api\Library\TransipAPI;

class TransIp implements ProviderInterface
{
    private LoggerInterface $logger;
    private Config $config;
    private ?TransipAPI $client = null;
    private array $domainNames = [];

    public function __construct(Config $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Create a TXT DNS record via the provider's API.
     */
    public function createChallengeDnsRecord(ChallengeRecord $challengeRecord): void
    {
        $challengeDnsEntry = new DnsEntry();
        $challengeDnsEntry->setName($challengeRecord->getRecordName());
        $challengeDnsEntry->setExpire(60);
        $challengeDnsEntry->setType(DnsEntry::TYPE_TXT);
        $challengeDnsEntry->setContent($challengeRecord->getValidation());

        $this->getTransIpApiClient()
            ->domainDns()
            ->addDnsEntryToDomain($challengeRecord->getDomain(), $challengeDnsEntry);
    }

    /**
     * Remove the created TXT record via the provider's API.
     */
    public function cleanChallengeDnsRecord(ChallengeRecord $challengeRecord): void
    {
        $client = $this->getTransIpApiClient();
        $dnsEntries = $client->domainDns()->getByDomainName($challengeRecord->getDomain());

        foreach ($dnsEntries as $dnsEntry) {
            if ($dnsEntry->getName() === $challengeRecord->getRecordName() &&
                $dnsEntry->getContent() === $challengeRecord->getValidation()
            ) {
                $this->logger->debug(
                    sprintf(
                        'Removing challenge DNS record(%s 60 TXT %s)',
                        $dnsEntry->getName(),
                        $dnsEntry->getContent()
                    )
                );
                $client->domainDns()->removeDnsEntry($challengeRecord->getDomain(), $dnsEntry);
            }
        }
    }

    /**
     * Return a simple array containing the domain names that can be managed via the API.
     */
    public function getDomainNames(): iterable
    {
        if (empty($this->domainNames)) {
            $domains = $this->getTransIpApiClient()->domains()->getAll();
            foreach ($domains as $domain) {
                $this->domainNames[] = $domain->getName();
            }
        }

        $this->logger->debug(sprintf('Domain names available: %s', implode(', ', $this->domainNames)));

        return $this->domainNames;
    }

    public function getNameservers(string $domainName): array
    {
        $nameservers = $this->getTransIpApiClient()
            ->domainNameserver()
            ->getByDomainName($domainName);

        return array_map(function (Nameserver $nameserver) {
            return $nameserver->getHostname();
        }, $nameservers);
    }

    public function addTlsaRecord(
        string $domainName,
        TlsaRecord $tlsaRecord,
        $ttl = 300
    ) {
        $dnsEntry = new DnsEntry([
            'name' => $tlsaRecord->getName(),
            'expires' => $ttl,
            'type' => DnsEntry::TYPE_TLSA,
            'content' => $tlsaRecord->getContent(),
        ]);

        $this->insertOrUpdateDnsEntry($domainName, $dnsEntry);
    }

    private function insertOrUpdateDnsEntry(string $domainName, DnsEntry $dnsEntry): void
    {
        $client = $this->getTransIpApiClient();
        $dnsEntries = $client->domainDns()->getByDomainName($domainName);
        foreach ($dnsEntries as $existingDnsEntry) {
            /** @var DnsEntry $existingDnsEntry */
            if (
                $existingDnsEntry->getType() === $dnsEntry->getType() &&
                $existingDnsEntry->getName() === $dnsEntry->getName()) {
                $this->getTransIpApiClient()->domainDns()->updateEntry($domainName, $dnsEntry);

                return;
            }
        }

        $this->getTransIpApiClient()->domainDns()->addDnsEntryToDomain($domainName, $dnsEntry);
    }

    public function getTransIpApiClient(): TransipAPI
    {
        if ($this->client instanceof TransipAPI) {
            return $this->client;
        }

        $login = $this->config->get('transip_login', $this->config->get('login'));
        $privateKey = $this->config->get('transip_private_key', $this->config->get('private_key'));
        $generateWhitelistOnlyTokens = (bool) $this->config->get('transip_whitelist_only_token', true);

        $this->client = new TransipAPI(
            $login,
            $privateKey,
            $generateWhitelistOnlyTokens,
            '',
            '',
            null
        );

        return $this->client;
    }
}
