<?php

namespace RoyBongers\CertbotTransIpDns01\Certbot;

use RoyBongers\CertbotTransIpDns01\Certbot\Requests\AuthHookRequest;
use RoyBongers\CertbotTransIpDns01\Certbot\Requests\CleanupHookRequest;
use RuntimeException;
use Monolog\Logger;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use PurplePixie\PhpDns\DNSQuery;
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;

class CertbotDns01 implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var int $sleep number of seconds to sleep between nameserver polling rounds */
    private $sleep;

    /** @var int $maxTries maximum number of times the nameservers will be queried before throwing an exception */
    private $maxTries;

    /** @var ProviderInterface $provider */
    private $provider;

    /** @var Logger $logger */
    protected $logger;

    public function __construct(ProviderInterface $provider, int $sleep = 30, int $maxTries = 15)
    {
        $this->provider = $provider;
        $this->logger = new NullLogger();
        $this->sleep = $sleep;
        $this->maxTries = $maxTries;
    }

    public function authHook(AuthHookRequest $request): void
    {
        $domain = $this->getBaseDomain($request->getDomain());
        $subDomain = $this->getSubDomain($domain, $request->getDomain());
        $challengeName = $this->getChallengeName($subDomain);
        $challengeValue = $request->getChallenge();

        $this->logger->info(sprintf("Creating TXT record for %s with challenge '%s'", $challengeName, $challengeValue));
        $this->provider->createChallengeDnsRecord($domain, $challengeName, $challengeValue);
        $this->waitForNameServers($domain, $challengeName, $challengeValue);
    }

    public function cleanupHook(CleanupHookRequest $request): void
    {
        $domain = $this->getBaseDomain($request->getDomain());
        $subDomain = $this->getSubDomain($domain, $request->getDomain());
        $challengeName = $this->getChallengeName($subDomain);
        $challengeValue = $request->getChallenge();

        $this->logger->info(sprintf("Cleaning up record %s with value '%s'", $challengeName, $challengeValue));
        $this->provider->cleanChallengeDnsRecord($domain, $challengeName, $challengeValue);
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
    private function waitForNameServers(string $domain, string $challengeRecord, string $challenge): void
    {
        $tries = 0;
        $updatedRecords = 0;

        $nameservers = $this->getNameServers($domain);
        $totalNameservers = count($nameservers);

        $this->logger->info(sprintf('Waiting until nameservers (%s) are up-to-date', implode(', ', $nameservers)));

        while ($updatedRecords < $totalNameservers) {
            $updatedRecords = 0;

            // Query each nameserver and make sure the TXT record exists.
            foreach ($nameservers as $index => $nameserver) {
                if ($this->nameserverIsUpdated($nameserver, $challengeRecord . '.' . $domain, $challenge)) {
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

    private function nameserverIsUpdated(string $nameserver, string $record, string $challenge): bool
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
            if ($dnsResult->getData() === $challenge) {
                return true;
            }
        }

        return false;
    }

    private function getChallengeName(string $subDomain): string
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
