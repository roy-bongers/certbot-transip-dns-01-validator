<?php

namespace RoyBongers\CertbotDns01\Certbot;

use Psr\Log\LoggerInterface;
use PurplePixie\PhpDns\DNSQuery;
use RoyBongers\CertbotDns01\Certbot\Requests\ManualHookRequest;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;
use RuntimeException;

class Dns01ManualHookHandler
{
    /**
     * @param int $sleep    Number of seconds to sleep between nameserver polling rounds
     * @param int $maxTries Maximum number of times the nameservers will be queried before throwing an exception
     */
    public function __construct(
        private readonly ProviderInterface $provider,
        protected LoggerInterface $logger,
        private readonly int $sleep = 30,
        private readonly int $maxTries = 15
    ) {
    }

    /**
     * Perform the manual auth hook.
     */
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

    /**
     * Perform the manual cleanup hook.
     */
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

    /**
     * Returns an ChallengeRecord instance which has all properties needed to perform the validation.
     */
    private function getChallengeRecord(ManualHookRequest $request): ChallengeRecord
    {
        $domain = $this->getBaseDomain($request->getDomain());
        $subDomain = $this->getSubDomain($domain, $request->getDomain());
        $challengeName = $this->getRecordName($subDomain);
        $validation = $request->getValidation();

        return new ChallengeRecord($domain, $challengeName, $validation);
    }

    /**
     * Search for the primary domain (zone) where the DNS records are stored. It loops through a list of options
     * starting with the full domain including subdomains. If that domain can't be managed by the provider a
     * subdomain part is stripped off, and we search again.
     */
    private function getBaseDomain(string $domain): string
    {
        $domainGuesses = $this->getDomainGuesses($domain);

        foreach ($this->provider->getDomainNames() as $domainName) {
            if (in_array($domainName, $domainGuesses, true)) {
                return $domainName;
            }
        }

        throw new RuntimeException(sprintf('Can\'t manage DNS for given domain (%s).', reset($domainGuesses)));
    }

    /**
     * Return a list of domain names that we can use to search for the primary domain.
     */
    private function getDomainGuesses(string $fullyQualifiedDomainName): array
    {
        $guesses = [];
        while (str_contains($fullyQualifiedDomainName, '.')) {
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

        $dnsRecord = $challengeRecord->getFullRecordName();
        $nameservers = $this->provider->getNameservers($challengeRecord->getDomain());
        $totalNameservers = count($nameservers);

        $this->logger->info(sprintf('Waiting until nameservers (%s) are up-to-date', implode(', ', $nameservers)));

        // keep looping until all nameservers are updated.
        while ($updatedRecords < $totalNameservers) {
            $updatedRecords = 0;

            // query each nameserver and make sure the TXT record exists.
            foreach ($nameservers as $nameserver) {
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

    /**
     * Perform a DNS query and check of the nameserver already has the up-to-date record.
     */
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

    private function getSubDomain(string $baseDomain, string $domain): string
    {
        return rtrim(substr($domain, 0, strrpos($domain, $baseDomain)), '.');
    }
}
