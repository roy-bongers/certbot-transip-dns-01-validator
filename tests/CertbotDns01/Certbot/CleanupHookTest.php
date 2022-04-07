<?php

namespace RoyBongers\Tests\CertbotDns01\Certbot;

use Hamcrest\Matchers;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Certbot\Dns01ManualHookHandler;
use RoyBongers\CertbotDns01\Certbot\Requests\ManualHookRequest;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;

class CleanupHookTest extends TestCase
{
    private Dns01ManualHookHandler $hookHandler;
    private ProviderInterface $provider;

    public function testCleanupHookWithPrimaryDomain(): void
    {
        putenv('CERTBOT_DOMAIN=domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $expectedChallengeRecord = new ChallengeRecord(
            'domain.com',
            '_acme-challenge',
            'AfricanOrEuropeanSwallow'
        );

        $this->provider->shouldReceive('cleanChallengeDnsRecord')
            ->with(Matchers::equalTo($expectedChallengeRecord))
            ->once();

        $this->expectNotToPerformAssertions();

        $this->hookHandler->cleanupHook(new ManualHookRequest());
    }

    public function testCleanupHookWithSubDomain(): void
    {
        putenv('CERTBOT_DOMAIN=sub.domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $expectedChallengeRecord = new ChallengeRecord(
            'domain.com',
            '_acme-challenge.sub',
            'AfricanOrEuropeanSwallow'
        );

        $this->provider->shouldReceive('cleanChallengeDnsRecord')
            ->with(Matchers::equalTo($expectedChallengeRecord))
            ->once();

        $this->expectNotToPerformAssertions();

        $this->hookHandler->cleanupHook(new ManualHookRequest());
    }

    public function testItThrowsRuntimeExceptionWithUnmanageableDomain(): void
    {
        putenv('CERTBOT_DOMAIN=example.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->expectException(\RuntimeException::class);

        $this->hookHandler->cleanupHook(new ManualHookRequest());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = Mockery::mock(ProviderInterface::class);
        $this->provider->shouldReceive('getDomainNames')->andReturn(['domain.com', 'transip.nl']);

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('get')->andReturn([]);

        $this->hookHandler = new Dns01ManualHookHandler($this->provider, new NullLogger(), $config);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
