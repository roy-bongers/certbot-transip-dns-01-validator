<?php

namespace RoyBongers\Tests\CertbotDns01\Certbot;

use Hamcrest\Matchers;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use PurplePixie\PhpDns\DNSAnswer;
use PurplePixie\PhpDns\DNSQuery;
use PurplePixie\PhpDns\DNSResult;
use PurplePixie\PhpDns\DNSTypes;
use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Certbot\Dns01ManualHookHandler;
use RoyBongers\CertbotDns01\Certbot\Requests\ManualHookRequest;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;
use RuntimeException;
use Symfony\Bridge\PhpUnit\DnsMock;

class AuthHookTest extends TestCase
{
    private Dns01ManualHookHandler $acme2;
    private ProviderInterface $provider;
    private DNSQuery $dnsQuery;

    public function testAuthHookWithPrimaryDomain(): void
    {
        putenv('CERTBOT_DOMAIN=domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $expectedChallengeRecord = new ChallengeRecord(
            'domain.com',
            '_acme-challenge',
            'AfricanOrEuropeanSwallow'
        );
        $this->provider->shouldReceive('createChallengeDnsRecord')
            ->with(Matchers::equalTo($expectedChallengeRecord))
            ->once();

        $this->provider->shouldReceive('getNameservers')
            ->andReturn($this->createNameserverResponse());

        // mock DNSQuery class
        $dnsAnswer = $this->createDnsAnswer('domain.com', 'AfricanOrEuropeanSwallow');
        $this->dnsQuery->shouldReceive('Query')->andReturn($dnsAnswer);
        $this->dnsQuery->shouldReceive('hasError')->andReturnFalse();

        $this->expectNotToPerformAssertions();

        $this->acme2->authHook(new ManualHookRequest());
    }

    public function testAuthHookWithSubDomain(): void
    {
        putenv('CERTBOT_DOMAIN=sub.domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $expectedChallengeRecord = new ChallengeRecord(
            'domain.com',
            '_acme-challenge.sub',
            'AfricanOrEuropeanSwallow'
        );

        $this->provider->shouldReceive('createChallengeDnsRecord')
            ->with(Matchers::equalTo($expectedChallengeRecord))
            ->once();

        $this->provider->shouldReceive('getNameservers')
            ->andReturn($this->createNameserverResponse());

        // mock DNSQuery class
        $dnsAnswer = $this->createDnsAnswer('sub.domain.com', 'AfricanOrEuropeanSwallow');
        $this->dnsQuery->shouldReceive('Query')->andReturn($dnsAnswer);
        $this->dnsQuery->shouldReceive('hasError')->andReturnFalse();

        $this->expectNotToPerformAssertions();

        $this->acme2->authHook(new ManualHookRequest());
    }

    public function testItThrowsRuntimeExceptionWithUnmanageableDomain(): void
    {
        putenv('CERTBOT_DOMAIN=example.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->expectException(RuntimeException::class);

        $this->acme2->authHook(new ManualHookRequest());
    }

    public function testItThrowsRuntimeExceptionWhenQueryingNameserversTimeouts(): void
    {
        putenv('CERTBOT_DOMAIN=domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('createChallengeDnsRecord');

        $this->provider->shouldReceive('getNameservers')
            ->andReturn($this->createNameserverResponse());

        // mock DNSQuery class
        $dnsAnswer = $this->createDnsAnswer('domain.com', 'HowDoYouKnowSoMuchAboutSwallows');
        $this->dnsQuery->shouldReceive('Query')->andReturn($dnsAnswer);
        $this->dnsQuery->shouldReceive('hasError')->andReturnFalse();

        $this->expectException(RuntimeException::class);

        $this->acme2->authHook(new ManualHookRequest());
    }

    private function createDnsAnswer(string $domain, string $data): DNSAnswer
    {
        $dnsResult = new DNSResult(
            'TXT',
            (new DNSTypes())->getByName('TXT'),
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

    private function createNameserverResponse(): array
    {
        return [
            'ns0.transip.net',
            'ns1.transip.nl',
            'ns2.transip.eu',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Mockery::mock(ProviderInterface::class);
        $this->provider->shouldReceive('getDomainNames')->andReturn(['domain.com', 'transip.nl']);

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('get')->andReturn([]);

        $this->dnsQuery = Mockery::mock('overload:' . DNSQuery::class);

        $this->acme2 = new Dns01ManualHookHandler($this->provider, new NullLogger(), $config, 0, 3);

        DnsMock::register(Dns01ManualHookHandler::class);
        DnsMock::withMockedHosts(
            [
                'domain.com' => [
                    [
                        'type' => 'NS',
                        'target' => 'ns1.provider.com',
                    ],
                    [
                        'type' => 'NS',
                        'target' => 'ns2.provider.nl',
                    ],
                    [
                        'type' => 'NS',
                        'target' => 'ns3.provider.eu',
                    ],
                ],
            ]
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
