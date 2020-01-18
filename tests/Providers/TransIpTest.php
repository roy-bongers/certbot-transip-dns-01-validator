<?php

namespace RoyBongers\CertbotTransIpDns01\Tests\Providers;

use Mockery;
use Transip_DnsEntry;
use Transip_DnsService;
use Transip_DomainService;
use PHPUnit\Framework\TestCase;
use RoyBongers\CertbotTransIpDns01\Providers\TransIp;

class TransIpTest extends TestCase
{
    /** @var TransIp $transIp */
    private $transIp;

    /** @var Transip_DnsService $dnsService */
    private $dnsService;

    /** @var Transip_DomainService $domainService */
    private $domainService;

    public function testItCreatesChallengeDnsRecord(): void
    {
        $this->domainService->shouldReceive('getInfo')->andReturn(
            (object)[
                'dnsEntries' => $this->generateDnsRecords(),
            ]
        );

        $expectedDnsEntry = new Transip_DnsEntry('_acme-challenge', 60, 'TXT', 'AfricanOrEuropeanSwallow');
        $expectedDnsRecordCount = count($this->domainService->getInfo('domain.com')->dnsEntries) + 1;

        $this->dnsService->shouldReceive('setDnsEntries')->withArgs(
            function ($domain, $dnsEntries) use ($expectedDnsRecordCount, $expectedDnsEntry) {
                $this->assertEquals('domain.com', $domain);
                $this->assertEquals($expectedDnsRecordCount, count($dnsEntries));
                $this->assertContainsEquals($expectedDnsEntry, $dnsEntries);
                return true;
            }
        )->once();

        $this->transIp->createChallengeDnsRecord('domain.com', '_acme-challenge', 'AfricanOrEuropeanSwallow');
    }

    public function testItCleansChallengeDnsRecord(): void
    {
        $challengeDnsEntry = new Transip_DnsEntry('_acme-challenge', 60, 'TXT', 'AfricanOrEuropeanSwallow');
        $this->domainService->shouldReceive('getInfo')->andReturn(
            (object)[
                'dnsEntries' => $this->generateDnsRecords($challengeDnsEntry),
            ]
        );

        $expectedDnsRecordCount = count($this->domainService->getInfo('domain.com')->dnsEntries) - 1;

        $this->dnsService->shouldReceive('setDnsEntries')->withArgs(
            function ($domain, $dnsEntries) use ($expectedDnsRecordCount, $challengeDnsEntry) {
                $this->assertEquals('domain.com', $domain);
                $this->assertEquals($expectedDnsRecordCount, count($dnsEntries));
                $this->assertNotContainsEquals($challengeDnsEntry, $dnsEntries);
                $this->assertEquals(array_keys($dnsEntries), range(0, count($dnsEntries) - 1));
                return true;
            }
        )->once();

        $this->transIp->cleanChallengeDnsRecord('domain.com', '_acme-challenge', 'AfricanOrEuropeanSwallow');
    }

    private function generateDnsRecords(Transip_DnsEntry $additionalDnsEntry = null): array
    {
        $dnsEntries = [
            new Transip_DnsEntry('*', 86400, 'CNAME', '@'),
            new Transip_DnsEntry('@', 86400, 'A', '123.45.67.89'),
            new Transip_DnsEntry('@', 86400, 'MX', '10 mx.domain.com'),
            new Transip_DnsEntry('@', 86400, 'TXT', 'v=spf1 include=domain.com  ~all'),
            new Transip_DnsEntry('@', 86400, 'CAA', '0 issue "letsencrypt.org"'),
            new Transip_DnsEntry('www', 86400, 'CNAME', '@'),
            new Transip_DnsEntry('subdomain', 3600, 'A', '98.76.54.32'),
        ];

        if ($additionalDnsEntry instanceof Transip_DnsEntry) {
            $dnsEntries[] = $additionalDnsEntry;
        }

        shuffle($dnsEntries);
        return $dnsEntries;
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->transIp = new TransIp();
        $this->dnsService = Mockery::mock('overload:' . Transip_DnsService::class);
        $this->domainService = Mockery::mock('overload:' . Transip_DomainService::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
