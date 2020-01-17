<?php

namespace RoyBongers\CertbotTransIpDns01\Tests;

use Mockery;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PurplePixie\PhpDns\DNSQuery;
use PurplePixie\PhpDns\DNSAnswer;
use PurplePixie\PhpDns\DNSResult;
use Symfony\Bridge\PhpUnit\DnsMock;
use RoyBongers\CertbotTransIpDns01\Certbot\CertbotDns01;
use RoyBongers\CertbotTransIpDns01\Certbot\Requests\AuthHookRequest;
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;

class AuthHookTest extends TestCase
{
    /** @var CertbotDns01 $acme2 */
    protected $acme2;

    /** @var ProviderInterface $provider */
    protected $provider;

    /** @var DNSQuery $dnsQuery */
    protected $dnsQuery;

    public function testAuthHookWithPrimaryDomain(): void
    {
        putenv('CERTBOT_DOMAIN=domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('createChallengeDnsRecord')->withArgs([
            'domain.com',
            '_acme-challenge',
            'AfricanOrEuropeanSwallow',
        ])->once();

        // mock DNSQuery class
        $dnsAnswer = $this->createDnsAnswer('domain.com', 'AfricanOrEuropeanSwallow');
        $this->dnsQuery->shouldReceive('Query')->andReturn($dnsAnswer);
        $this->dnsQuery->shouldReceive('hasError')->andReturnFalse();

        $this->expectNotToPerformAssertions();

        $this->acme2->authHook(new AuthHookRequest());
    }

    public function testAuthHookWithSubDomain(): void
    {
        putenv('CERTBOT_DOMAIN=sub.domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('createChallengeDnsRecord')->withArgs([
            'domain.com',
            '_acme-challenge.sub',
            'AfricanOrEuropeanSwallow',
        ])->once();

        // mock DNSQuery class
        $dnsAnswer = $this->createDnsAnswer('sub.domain.com', 'AfricanOrEuropeanSwallow');
        $this->dnsQuery->shouldReceive('Query')->andReturn($dnsAnswer);
        $this->dnsQuery->shouldReceive('hasError')->andReturnFalse();

        $this->expectNotToPerformAssertions();

        $this->acme2->authHook(new AuthHookRequest());
    }

    public function testItThrowsRuntimeExceptionWithUnmanageableDomain(): void
    {
        putenv('CERTBOT_DOMAIN=example.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->expectException(RuntimeException::class);

        $this->acme2->authHook(new AuthHookRequest());
    }

    public function testItThrowsRuntimeExceptionWhenQueryingNameserversTimeouts(): void
    {
        putenv('CERTBOT_DOMAIN=domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('createChallengeDnsRecord');

        // mock DNSQuery class
        $dnsAnswer = $this->createDnsAnswer('domain.com', 'HowDoYouKnowSoMuchAboutSwallows');
        $this->dnsQuery->shouldReceive('Query')->andReturn($dnsAnswer);
        $this->dnsQuery->shouldReceive('hasError')->andReturnFalse();

        $this->expectException(RuntimeException::class);

        $this->acme2->authHook(new AuthHookRequest());
    }

    private function createDnsAnswer(string $domain, string $data): DNSAnswer
    {
        $dnsResult = new DNSResult(
            'TXT',
            '',
            '',
            60,
            $data,
            $domain,
            '',
            []
        );
        $dnsAnswer = new DNSAnswer();
        $dnsAnswer->addResult($dnsResult);

        return $dnsAnswer;
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->provider = Mockery::mock(ProviderInterface::class);
        $this->provider->shouldReceive('getDomainNames')->andReturn(['domain.com', 'transip.nl']);

        $this->dnsQuery = Mockery::mock('overload:'.DNSQuery::class);

        $this->acme2 = new CertbotDns01($this->provider, 0, 3);

        DnsMock::register(CertbotDns01::class);
        DnsMock::withMockedHosts([
            'domain.com' => [
                [
                    'type'   => 'NS',
                    'target' => 'ns1.provider.com',
                ],
                [
                    'type'   => 'NS',
                    'target' => 'ns2.provider.nl',
                ],
                [
                    'type'   => 'NS',
                    'target' => 'ns3.provider.eu',
                ],
            ],
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
