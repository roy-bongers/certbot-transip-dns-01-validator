<?php

namespace RoyBongers\Tests\CertbotDns01\Providers;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\TransIp\TransIp;
use Transip\Api\Library\Entity\Domain;
use Transip\Api\Library\Entity\Domain\DnsEntry;
use Transip\Api\Library\Repository\Domain\DnsRepository;
use Transip\Api\Library\Repository\DomainRepository;
use Transip\Api\Library\TransipAPI;

class TransIpTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private TransIp $transIp;
    private DnsRepository $dnsService;
    private DomainRepository $domainService;

    public function testItCreatesChallengeDnsRecord(): void
    {
        $expectedDnsEntry = new DnsEntry(
            [
                'name' => '_acme-challenge',
                'expire' => 60,
                'type' => 'TXT',
                'content' => 'AfricanOrEuropeanSwallow',
            ]
        );

        $this->dnsService->shouldReceive('addDnsEntryToDomain')
            ->with('domain.com', Mockery::on(fn (DnsEntry $dnsEntry) => $dnsEntry == $expectedDnsEntry))
            ->once();

        $this->transIp->createChallengeDnsRecord(
            new ChallengeRecord(
                'domain.com',
                '_acme-challenge',
                'AfricanOrEuropeanSwallow'
            )
        );
    }

    public function testItCleansChallengeDnsRecord(): void
    {
        $challengeDnsEntry = new DnsEntry(
            [
                'name' => '_acme-challenge',
                'expire' => 60,
                'type' => 'TXT',
                'content' => 'AfricanOrEuropeanSwallow',
            ]
        );

        // return a list of DNS records.
        $this->dnsService->shouldReceive('getByDomainName')
            ->with('domain.com')
            ->andReturn(
                $this->generateDnsRecords($challengeDnsEntry)
            )
            ->once();

        // assert the challenge record is being removed.
        $this->dnsService->shouldReceive('removeDnsEntry')
            ->with('domain.com', Mockery::on(fn (DnsEntry $dnsEntry) => $dnsEntry === $challengeDnsEntry))
            ->once();

        $this->transIp->cleanChallengeDnsRecord(
            new ChallengeRecord(
                'domain.com',
                '_acme-challenge',
                'AfricanOrEuropeanSwallow'
            )
        );
    }

    public function testItFetchesDomainNames(): void
    {
        $domainNames = ['domain.com', 'example.nl'];
        $domainNameObjects = array_map(
            function (string $domain) {
                return new Domain(['name' => $domain]);
            },
            $domainNames
        );

        $this->domainService->shouldReceive('getAll')->andReturn($domainNameObjects);

        $this->assertEquals($domainNames, $this->transIp->getDomainNames());
    }

    private function generateDnsRecords(DnsEntry $additionalDnsEntry = null): array
    {
        $dnsEntries = [
            [
                'name' => '*',
                'expire' => 86400,
                'type' => DnsEntry::TYPE_CNAME,
                'content' => '@',
            ],
            [
                'name' => '@',
                'expire' => 86400,
                'type' => DnsEntry::TYPE_A,
                'content' => '123.45.67.89',
            ],
            [
                'name' => '@',
                'expire' => 86400,
                'type' => DnsEntry::TYPE_MX,
                'content' => '10 mx.domain.com',
            ],
            [
                'name' => '@',
                'expire' => 86400,
                'type' => DnsEntry::TYPE_TXT,
                'content' => 'v=spf1 include=domain.com  ~all',
            ],
            [
                'name' => '@',
                'expire' => 86400,
                'type' => DnsEntry::TYPE_CAA,
                'content' => '0 issue "letsencrypt.org"',
            ],
            [
                'name' => 'www',
                'expire' => 86400,
                'type' => DnsEntry::TYPE_CNAME,
                'content' => '@',
            ],
            [
                'name' => 'subdomain',
                'expire' => 3600,
                'type' => DnsEntry::TYPE_A,
                'content' => '98.76.54.32',
            ],
        ];

        $dnsEntries = array_map(
            function ($dnsEntry) {
                return new DnsEntry($dnsEntry);
            },
            $dnsEntries
        );

        if ($additionalDnsEntry instanceof DnsEntry) {
            $dnsEntries[] = $additionalDnsEntry;
        }

        shuffle($dnsEntries);

        return $dnsEntries;
    }

    public function setUp(): void
    {
        parent::setUp();

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('get')->andReturn('test');

        $this->dnsService = Mockery::mock(DnsRepository::class);
        $this->domainService = Mockery::mock(DomainRepository::class);

        // official TransipApi client
        $transIpClient = Mockery::mock(TransipAPI::class);
        $transIpClient->shouldReceive('domainDns')->andReturn($this->dnsService);
        $transIpClient->shouldReceive('domains')->andReturn($this->domainService);

        // TransIp provider
        $this->transIp = Mockery::mock(TransIp::class, [$config, new NullLogger()])->makePartial();
        $this->transIp->shouldReceive('getTransipApiClient')->andReturn($transIpClient);
    }
}
