<?php

namespace RoyBongers\CertbotDns01\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use SimpleXMLElement;
use Psr\Log\LoggerInterface;
use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;

class OpenProvider implements ProviderInterface
{
    /** @var string|null $username */
    private $username;

    /** @var string|null $passwordHash */
    private $passwordHash;

    /** @var ClientInterface $client */
    private $client;

    /** @var LoggerInterface $logger */
    private $logger;

    /** @var array $domainNames */
    private $domainNames = [];

    public function __construct(Config $config, LoggerInterface $logger, Client $client)
    {
        $this->logger = $logger;
        $this->client = $client;

        $this->username = $config->get('openprovider_username');
        $this->passwordHash = $config->get('openprovider_hash');
    }

    /**
     * @inheritDoc
     */
    public function createChallengeDnsRecord(ChallengeRecord $challengeRecord): void
    {
        $dnsRecords = $this->fetchDnsRecordsFromOpenProvider($challengeRecord->getDomain());
        $dnsRecords[] = [
            'name' => $challengeRecord->getRecordName(),
            'type' => 'TXT',
            'value' => '"' . $challengeRecord->getValidation() . '"',
            'ttl' => 60,
        ];

        $this->setDnsRecordsOnOpenProvider($dnsRecords, $challengeRecord->getDomain());
    }

    /**
     * @inheritDoc
     */
    public function cleanChallengeDnsRecord(ChallengeRecord $challengeRecord): void
    {
        $dnsEntries = $this->fetchDnsRecordsFromOpenProvider($challengeRecord->getDomain());
        foreach ($dnsEntries as $index => $dnsEntry) {
            if ($dnsEntry['name'] === $challengeRecord->getRecordName() &&
                $dnsEntry['value'] === $challengeRecord->getValidation()
            ) {
                $this->logger->debug(
                    sprintf('Removing challenge DNS record(%s 60 TXT %s)', $dnsEntry->name, $dnsEntry->content)
                );
                unset($dnsEntries[$index]);
            }
        }
        $dnsEntries = array_values($dnsEntries);
        $this->setDnsRecordsOnOpenProvider($dnsEntries, $challengeRecord->getDomain());
    }

    /**
     * @inheritDoc
     */
    public function getDomainNames(): iterable
    {
        return array_keys($this->fetchDomainNamesFromOpenProvider());
    }

    private function fetchDomainNamesFromOpenProvider(): array
    {
        if ($this->domainNames) {
            return $this->domainNames;
        }

        $data['searchDomainRequest'] = [
            'limit' => 1000,
        ];

        $response = $this->performApiRequest($data);
        $xml = new SimpleXMLElement($response->getBody()->getContents());
        $domainRecordsResponse = $xml->reply->data->results->array ?? [];

        $domainRecords = json_decode(json_encode($domainRecordsResponse), true)['item'];

        foreach ($domainRecords as $item) {
            $domain = $item['domain'];
            $this->domainNames[$domain['name'] . '.' . $domain['extension']] = $domain;
        }

        return $this->domainNames;
    }

    private function fetchDnsRecordsFromOpenProvider(string $domainName)
    {
        $data['retrieveZoneDnsRequest'] = [
            'name' => $domainName,
            'withRecords' => 1,
        ];

        $response = $this->performApiRequest($data);
        $xml = new SimpleXMLElement($response->getBody()->getContents());
        $dnsRecordResponse = $xml->reply->data->records->array ?? [];
        $records = json_decode(json_encode($dnsRecordResponse), true)['item'];

        return $this->cleanDnsRecords($records, $domainName);
    }

    private function setDnsRecordsOnOpenProvider(array $dnsRecords, string $domainName): void
    {
        $domainNames = $this->fetchDomainNamesFromOpenProvider();
        $domain = $domainNames[$domainName];

        $data = [
            'modifyZoneDnsRequest' => [
                'domain' => [
                    'name' => $domain['name'],
                    'extension' => $domain['extension'],
                ],
                'records' => [
                    'array' => $dnsRecords
                ],
            ],
        ];

        $this->performApiRequest($data);
    }

    private function cleanDnsRecords(array $dnsRecords, string $domainName): array
    {
        $results = [];
        foreach ($dnsRecords as $dnsRecord) {
            // remove NS and SOA records (can't be updated via API).
            if (in_array($dnsRecord['type'], ['NS', 'SOA'])) {
                continue;
            }

            // API returns full DNS name's e.g. "A www.domain.com 127.0.0.1". We strip the domain part so we keep "www".
            $name = substr($dnsRecord['name'], 0, strrpos($dnsRecord['name'], $domainName));
            $name = rtrim($name, '.');
            if (empty($name)) {
                $name = null;
            }

            $priority = empty($dnsRecord['prio']) ? null : (int)$dnsRecord['prio'];

            // set default PRIO to MX record if empty.
            if ($dnsRecord['type'] === 'MX' && empty($priority)) {
                $priority = 10;
            }

            // API adds quotes around the TXT record's value.
            $value = $dnsRecord['value'];
            if ($dnsRecord['type'] === 'TXT') {
                $value = trim($value, '"');
            }

            $results[] = [
                'name' => $name,
                'type' => $dnsRecord['type'],
                'prio' => $priority,
                'value' => $value,
            ];
        }
        return array_values($results);
    }

    private function performApiRequest(array $data)
    {
        $data = array_merge(['credentials' => [
            'username' => $this->username,
            'hash' => $this->passwordHash,
        ]], $data);

        $xml = new SimpleXMLElement('<openXML/>');
        $this->array2xml($data, $xml);

        return $this->client->post(
            'https://api.openprovider.eu',
            [
                'headers' => ['Content-type' => 'text/xml'],
                'body' => $xml->asXML(),
            ]
        );
    }

    private function array2xml(array $data, SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                }
                $subNode = $xml->addChild($key);
                $this->array2xml($value, $subNode);
            } else {
                $xml->addChild($key, $value);
            }
        }
    }
}
