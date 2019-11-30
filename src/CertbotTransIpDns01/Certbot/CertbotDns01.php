<?php

namespace RoyBongers\CertbotTransIpDns01\Certbot;

use Monolog\Logger;
use PurplePixie\PhpDns\DNSQuery;
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;

class CertbotDns01
{
    /** @var int $sleep number of seconds to sleep between nameserver polling rounds */
    private $sleep = 30;

    /** @var ProviderInterface $provider */
    protected $provider;

    /** @var CertbotRequest $request */
    protected $request;

    /** @var Logger $logger */
    protected $logger;

    public function __construct(ProviderInterface $provider, Logger $logger)
    {
        $this->provider = $provider;
        $this->logger = $logger;

        $this->request = new CertbotRequest();
    }

    public function authHook(): void
    {
        $challengeRecord = $this->getChallengeDnsRecordName();
        $challenge = $this->request->getChallenge();

        $this->logger->info(sprintf("Creating DNS record for %s with challenge '%s'", $challengeRecord, $challenge));
        $this->provider->createChallengeDnsRecord($challengeRecord, $challenge);
        $this->waitForNameServers();
    }

    public function cleanupHook(): void
    {
        $challengeRecord = $this->getChallengeDnsRecordName();
        $challenge = $this->request->getChallenge();

        $this->logger->info(sprintf("Cleaning up record %s with value '%s'", $challengeRecord, $challenge));
        $this->provider->cleanChallengeDnsRecord($challengeRecord, $challenge);
    }

    protected function getChallengeDnsRecordName(): string
    {
        return '_acme-challenge.'.$this->request->getDomain();
    }

    protected function getNameServers(): array
    {
        return array_column(dns_get_record($this->request->getDomain(), DNS_NS), 'target');
    }

    /**
     * For some reason when a nameserver is just updated the new record appears and disappears again for
     * some time when polling continuously. Therefore we poll every nameserver until all are updated and
     * even then we wait another 30 seconds to be really sure they are all ok.
     */
    protected function waitForNameServers(): void
    {
        $nameservers = $this->getNameServers();
        $updatedRecords = 0;
        $totalNameservers = count($nameservers);

        $this->logger->info('Waiting until nameservers are up-to-date');
        $this->logger->debug(sprintf('Polling %d nameservers, (%s)', $totalNameservers, implode(', ', $nameservers)));

        while ($updatedRecords < $totalNameservers) {
            $updatedRecords = 0;

            // Query each nameserver and make sure the TXT record exists.
            foreach ($nameservers as $index => $nameserver) {
                if ($this->nameserverIsUpdated($nameserver, $this->getChallengeDnsRecordName(), $this->request->getChallenge())) {
                    $this->logger->debug(sprintf("Nameserver '%s' is up-to-date", $nameserver));
                    $updatedRecords++;
                }
            }

            if ($updatedRecords < $totalNameservers) {
                $this->logger->debug(sprintf(
                    '%d of %d nameservers are ready. Retrying in %d seconds',
                    $updatedRecords,
                    $totalNameservers,
                    $this->sleep
                ));
            } else {
                $this->logger->info('All DNS records are updated!');
                $this->logger->debug(sprintf('Sleeping another %d seconds to be sure', $this->sleep));
            }

            // sleep another round...
            sleep($this->sleep);
        }
    }

    protected function nameserverIsUpdated(string $nameserver, string $record, string $challenge): bool
    {
        $dnsQuery = new DNSQuery($nameserver);
        $dnsResults = $dnsQuery->Query($record, 'TXT');

        if ((false === $dnsResults) || (false !== $dnsQuery->hasError())) {
            $this->logger->error($dnsQuery->getLasterror());
        }

        foreach ($dnsResults as $dnsResult) {
            if ($dnsResult->getData() === $challenge) {
                return true;
            }
        }

        return false;
    }
}
