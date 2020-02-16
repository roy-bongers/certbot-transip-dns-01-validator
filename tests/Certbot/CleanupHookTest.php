<?php

namespace RoyBongers\CertbotDns01\Tests\Certbot;

use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RoyBongers\CertbotDns01\Certbot\Dns01ManualHookHandler;
use RoyBongers\CertbotDns01\Certbot\Requests\ManualHookRequest;
use RoyBongers\CertbotDns01\Providers\Interfaces\ProviderInterface;

class CleanupHookTest extends TestCase
{
    /** @var Dns01ManualHookHandler $hookHandler */
    private $hookHandler;

    /** @var ProviderInterface $provider */
    private $provider;

    public function testCleanupHookWithPrimaryDomain(): void
    {
        putenv('CERTBOT_DOMAIN=domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('cleanChallengeDnsRecord')->withArgs([
            'domain.com',
            '_acme-challenge',
            'AfricanOrEuropeanSwallow',
        ])->once();

        $this->expectNotToPerformAssertions();

        $this->hookHandler->cleanupHook(new ManualHookRequest());
    }

    public function testCleanupHookWithSubDomain(): void
    {
        putenv('CERTBOT_DOMAIN=sub.domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('cleanChallengeDnsRecord')->withArgs([
            'domain.com',
            '_acme-challenge.sub',
            'AfricanOrEuropeanSwallow',
        ])->once();

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

    public function setUp(): void
    {
        parent::setUp();

        $this->provider = Mockery::mock(ProviderInterface::class);
        $this->provider->shouldReceive('getDomainNames')->andReturn(['domain.com', 'transip.nl']);

        $this->hookHandler = new Dns01ManualHookHandler($this->provider, new NullLogger());
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
