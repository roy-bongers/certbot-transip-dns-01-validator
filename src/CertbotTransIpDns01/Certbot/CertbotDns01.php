<?php

namespace RoyBongers\CertbotTransIpDns01\Certbot;

use Monolog\Logger;
use PurplePixie\PhpDns\DNSQuery;
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;

class CertbotDns01
{
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

    public function authHook() {
        $challengeRecord = $this->getChallengeDnsRecordName();
        $challenge = $this->request->getChallenge();

        $this->logger->info(
            sprintf("Creating DNS record for %s with challenge '%s'", $challengeRecord, $challenge)
        );
        $this->provider->createChallengeDnsRecord($challengeRecord, $challenge);
        $this->waitForNameServers();
    }

    public function cleanupHook() {
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

    protected function waitForNameServers(): void
    {
        $nameservers = $this->getNameServers();
        $updatedRecords = 0;
        $totalNameservers = count($nameservers);

        $this->logger->info(sprintf('Total nameservers %d', $totalNameservers));

        while ($updatedRecords < $totalNameservers) {
            // Query each nameserver and make sure the TXT record exists.
            foreach ($nameservers as $index => $nameserver) {
                $dnsQuery = new DNSQuery($nameserver);
                $dnsResults = $dnsQuery->Query($this->getChallengeDnsRecordName(), 'TXT');

                if ((false === $dnsResults) || (false !== $dnsQuery->hasError())) {
                    $this->logger->error($dnsQuery->getLasterror());
                    exit(1);
                }

                // Process results.
                foreach ($dnsResults as $dnsResult) {
                    if ($dnsResult->getData() === $this->request->getChallenge()) {
                        // Update the amount of updated records.
                        $updatedRecords++;
                        // No need to check the already updated nameservers again.
                        unset($nameservers[$index]);
                    }
                }
            }

            if ($updatedRecords < $totalNameservers) {
                // Sleep if not all nameserver have updated yet.
                $this->logger->info(sprintf(
                    '%d of %d nameservers are ready. Retrying in %d seconds',
                    $updatedRecords,
                    $totalNameservers,
                    $this->sleep
                ));
                sleep($this->sleep);
            } else {
                $this->logger->info('All DNS records updated');
            }
        }
    }
}
