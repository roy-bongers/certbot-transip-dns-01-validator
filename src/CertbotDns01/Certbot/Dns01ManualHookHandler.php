<?php

namespace RoyBongers\CertbotDns01\Certbot;

use Monolog\Logger;
use RuntimeException;
use Psr\Log\LoggerInterface;
use PurplePixie\PhpDns\DNSQuery;
use RoyBongers\CertbotDns01\Certbot\Requests\ManualHookRequest;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;

class Dns01ManualHookHandler
{
    /** @var int $sleep number of seconds to sleep between nameserver polling rounds */
    private $sleep;

    /** @var int $maxTries maximum number of times the nameservers will be queried before throwing an exception */
    private $maxTries;

    /** @var ProviderInterface $provider */
    private $provider;

    /** @var Logger $logger */
    protected $logger;

    public function __construct(
        ProviderInterface $provider,
        LoggerInterface $logger,
        int $sleep = 30,
        int $maxTries = 15
    ) {
        $this->provider = $provider;
        $this->logger = $logger;
        $this->sleep = $sleep;
        $this->maxTries = $maxTries;
    }

    public function authHook(ManualHookRequest $request): void
    {
        $challengeRecord = $this->getChallengeRecord($request);

        $this->logger->info(sprintf(
            "Creating TXT record for %s with challenge '%s'",
            $challengeRecord->getRecordName(),
            $challengeRecord->getValidation()
        ));

        $this->provider->createChallengeDnsRecord($challengeRecord);
        $this->waitForNameServers($challengeRecord);
    }

    public function cleanupHook(ManualHookRequest $request): void
    {
        $challengeRecord = $this->getChallengeRecord($request);

        $this->logger->info(sprintf(
            "Cleaning up record %s with value '%s'",
            $challengeRecord->getRecordName(),
            $challengeRecord->getValidation()
        ));

        $this->provider->cleanChallengeDnsRecord($challengeRecord);
    }

    private function getChallengeRecord(ManualHookRequest $request): ChallengeRecord
    {
        $domain = $this->getBaseDomain($request->getDomain());
        $subDomain = $this->getSubDomain($domain, $request->getDomain());
        $challengeName = $this->getRecordName($subDomain);
        $validation = $request->getValidation();

        return new ChallengeRecord($domain, $challengeName, $validation);
    }

    private function getBaseDomain(string $domain): string
    {
        $domainGuesses = $this->getDomainGuesses($domain);

        foreach ($this->provider->getDomainNames() as $domainName) {
            foreach ($domainGuesses as $guess) {
                if ($domainName === $guess) {
                    return $domainName;
                }
            }
        }

        throw new RuntimeException(sprintf('Can\'t manage DNS for given domain (%s).', reset($domainGuesses)));
    }

    private function getDomainGuesses(string $fullyQualifiedDomainName): array
    {
        $guesses = [];
        while (false !== strpos($fullyQualifiedDomainName, '.')) {
            $guesses[] = $fullyQualifiedDomainName;
            $fullyQualifiedDomainName = substr($fullyQualifiedDomainName, strpos($fullyQualifiedDomainName, '.') + 1);
        }

        return $guesses;
    }

    /**
     * For some reason when a nameserver is just updated the new record appears and disappears again for
     * some time when polling continuously. Therefore we poll every nameserver until all are updated and
     * even then we wait another 30 seconds to be really sure they are all ok.
     */
    private function waitForNameServers(ChallengeRecord $challengeRecord): void
    {
        $tries = 0;
        $updatedRecords = 0;

        $dnsRecord = $challengeRecord->getRecordName() . '.' . $challengeRecord->getDomain();
        $nameservers = $this->getNameServers($challengeRecord->getDomain());
        $totalNameservers = count($nameservers);

        $this->logger->info(sprintf('Waiting until nameservers (%s) are up-to-date', implode(', ', $nameservers)));

        while ($updatedRecords < $totalNameservers) {
            $updatedRecords = 0;

            // Query each nameserver and make sure the TXT record exists.
            foreach ($nameservers as $index => $nameserver) {
                if ($this->nameserverIsUpdated($nameserver, $dnsRecord, $challengeRecord->getValidation())) {
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
                $tries++;
                if ($tries > $this->maxTries) {
                    throw new RuntimeException(sprintf(
                        'Could not successfully query nameservers within %d tries (%d seconds)',
                        $this->maxTries,
                        $this->sleep * $this->maxTries
                    ));
                }
            } else {
                $this->logger->info('All nameservers are updated!');
                $this->logger->debug(sprintf('Sleeping another %d seconds to be sure', $this->sleep));
            }

            // sleep another round...
            sleep($this->sleep);
        }
    }

    private function nameserverIsUpdated(string $nameserver, string $record, string $validation): bool
    {
        $dnsQuery = new DNSQuery($nameserver);
        $dnsResults = $dnsQuery->Query($record, 'TXT');
        $this->logger->debug(sprintf('Querying TXT %s @%s', $record, $nameserver));

        if (false === $dnsResults) {
            $this->logger->error('Empty DNS result');
            return false;
        }

        if (false !== $dnsQuery->hasError()) {
            $this->logger->error($dnsQuery->getLasterror());
            return false;
        }

        foreach ($dnsResults as $dnsResult) {
            $this->logger->debug(sprintf('DNS result: %s', $dnsResult->getData()));
            if ($dnsResult->getData() === $validation) {
                return true;
            }
        }

        return false;
    }

    private function getRecordName(string $subDomain): string
    {
        return rtrim('_acme-challenge.' . $subDomain, '.');
    }

    private function getNameServers(string $domain): array
    {
        return array_column(dns_get_record($domain, DNS_NS), 'target');
    }

    private function getSubDomain(string $baseDomain, string $domain): string
    {
        return rtrim(substr($domain, 0, strrpos($domain, $baseDomain)), '.');
    }
}
