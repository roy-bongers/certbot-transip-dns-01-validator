<?php
namespace RoyBongers\CertbotTransIpDns01\Tests\Certbot;

use Mockery;
use PHPUnit\Framework\TestCase;
use RoyBongers\CertbotTransIpDns01\Certbot\CertbotDns01;
use RoyBongers\CertbotTransIpDns01\Certbot\Requests\CleanupHookRequest;
use RoyBongers\CertbotTransIpDns01\Providers\Interfaces\ProviderInterface;

class CleanupHookTest extends TestCase
{
    /** @var CertbotDns01 $acme2 */
    protected $acme2;

    /** @var ProviderInterface $provider */
    protected $provider;

    public function testCleanupHookWithPrimaryDomain(): void
    {
        putenv('CERTBOT_DOMAIN=domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('cleanChallengeDnsRecord')->withArgs([
            'domain.com',
            '_acme-challenge',
            'AfricanOrEuropeanSwallow'
        ])->once();

        $this->expectNotToPerformAssertions();

        $this->acme2->cleanupHook(new CleanupHookRequest());
    }

    public function testCleanupHookWithSubDomain(): void
    {
        putenv('CERTBOT_DOMAIN=sub.domain.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->provider->shouldReceive('cleanChallengeDnsRecord')->withArgs([
            'domain.com',
            '_acme-challenge.sub',
            'AfricanOrEuropeanSwallow'
        ])->once();

        $this->expectNotToPerformAssertions();

        $this->acme2->cleanupHook(new CleanupHookRequest());
    }

    public function testItThrowsRuntimeExceptionWithUnmanageableDomain(): void
    {
        putenv('CERTBOT_DOMAIN=example.com');
        putenv('CERTBOT_VALIDATION=AfricanOrEuropeanSwallow');

        $this->expectException(\RuntimeException::class);

        $this->acme2->cleanupHook(new CleanupHookRequest());
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->provider = Mockery::mock(ProviderInterface::class);
        $this->provider->shouldReceive('getDomainNames')->andReturn(['domain.com', 'transip.nl']);

        $this->acme2 = new CertbotDns01($this->provider);
    }

    public function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
