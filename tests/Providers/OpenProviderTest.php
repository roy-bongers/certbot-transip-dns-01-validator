<?php

namespace RoyBongers\CertbotDns01\Tests\Providers;

use SimpleXMLElement;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Psr\Log\NullLogger;
use RoyBongers\CertbotDns01\Certbot\ChallengeRecord;
use RoyBongers\CertbotDns01\Config;
use RoyBongers\CertbotDns01\Providers\OpenProvider;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\HandlerStack;

class OpenProviderTest extends TestCase
{
    private $provider;
    private $responseMockHandler;
    private $historyContainer = [];

    public function testItFetchesDomainNames(): void
    {
        $domains = ['domain.nl', 'example.nl'];
        $responseXml = $this->getDomainNamesXmlResponse($domains);

        $this->responseMockHandler->append(new Response(200, ['Content-type' => 'text/xml'], $responseXml));

        $domainNames = $this->provider->getDomainNames();
        $this->assertEquals($domains, $domainNames);
    }

    public function testItCreatesChallengeRecord(): void
    {
        $domains = ['domain.nl', 'example.nl'];
        $headers = ['Content-type' => 'text/xml'];

        $this->responseMockHandler->append(new Response(200, $headers, $this->getDnsRecordsXmlResponse()));
        $this->responseMockHandler->append(new Response(200, $headers, $this->getDomainNamesXmlResponse($domains)));
        $this->responseMockHandler->append(new Response(200, $headers, $this->okResponse()));

        $this->provider->createChallengeDnsRecord(
            new ChallengeRecord(
                'domain.nl',
                '_acme-challenge',
                'AfricanOrEuropeanSwallow'
            )
        );

        /** @var Request $request */
        $request = $this->historyContainer[2]['request'];
        $xmlBody = $request->getBody()->getContents();
        $xml = new SimpleXMLElement($xmlBody);

        // assert credentials are in the request.
        $this->assertEquals('test', (string) $xml->credentials->username);
        $this->assertEquals('test', (string) $xml->credentials->password);

        // assert correct domain name.
        $this->assertEquals('domain', (string) $xml->modifyZoneDnsRequest->domain->name);
        $this->assertEquals('nl', (string) $xml->modifyZoneDnsRequest->domain->extension);

        // assert correct challenge record.
        $records = $xml->modifyZoneDnsRequest->records->array->item;
        $lastRecord = $records[$records->count() - 1];

        $this->assertEquals('_acme-challenge', (string) $lastRecord->name);
        $this->assertEquals('TXT', (string) $lastRecord->type);
        $this->assertEquals('"AfricanOrEuropeanSwallow"', (string) $lastRecord->value);
    }

    public function testItCleansChallengeRecord(): void
    {
        $domains = ['domain.nl', 'example.nl'];
        $headers = ['Content-type' => 'text/xml'];

        $this->responseMockHandler->append(new Response(200, $headers, $this->getDnsRecordsXmlResponse()));
        $this->responseMockHandler->append(new Response(200, $headers, $this->getDomainNamesXmlResponse($domains)));
        $this->responseMockHandler->append(new Response(200, $headers, $this->okResponse()));

        $this->provider->cleanChallengeDnsRecord(
            new ChallengeRecord(
                'domain.nl',
                '_acme-challenge',
                'AfricanOrEuropeanSwallow'
            )
        );
    }

    public function setUp(): void
    {
        parent::setUp();

        $config = Mockery::mock(Config::class);
        $config->shouldReceive('get')->andReturn('test');

        // Log guzzle history.
        $history = Middleware::history($this->historyContainer);

        // Allow mocking guzzle responses.
        $this->responseMockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->responseMockHandler);
        $handlerStack->push($history);

        $client = new Client(['handler' => $handlerStack]);

        $this->provider = new OpenProvider($config, new NullLogger(), $client);
    }

    private function okResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<openXML>
  <reply>
    <code>0</code>
    <desc></desc>
    <data>1</data>
  </reply>
</openXML>';
    }

    private function getDomainNamesXmlResponse(array $domains): string
    {
        $domainsString = '';
        foreach ($domains as $domain) {
            $extension = substr($domain, strpos($domain, '.') + 1);
            $name = substr($domain, 0, strpos($domain, '.'));
            $domainsString .= '
               <item>
                <domain>
                  <name>' . $name . '</name>
                  <extension>' . $extension . '</extension>
                </domain>
                <nameServers />
                <id>353146</id>
                <status>ACT</status>
              </item>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>
                <openXML>
                  <reply>
                    <code>0</code>
                    <data>
                      <results>
                        <array>
                          ' . $domainsString . '
                        </array>
                      </results>
                    </data>
                  </reply>
                </openXML>';
    }

    private function getDnsRecordsXmlResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<openXML>
  <reply>
    <data>
      <id>48890</id>
      <type>master</type>
      <name>demozone.com</name>
      <ip>com.demozone</ip>
      <active>1</active>
      <records>
        <array>
          <item>
            <name>*.demozone.com</name>
            <type>A</type>
            <value>89.255.0.43</value>
            <prio></prio>
            <ttl>86400</ttl>
            <creationDate>2010-07-13 17:07:22</creationDate>
            <modificationDate>2010-07-13 17:07:22</modificationDate>
          </item>
          <item>
            <name>demozone.com</name>
            <type>A</type>
            <value>89.255.0.43</value>
            <prio></prio>
            <ttl>86400</ttl>
            <creationDate>2010-07-13 17:07:22</creationDate>
            <modificationDate>2010-07-13 17:07:22</modificationDate>
          </item>
          <item>
            <name>demozone.com</name>
            <type>MX</type>
            <value>mail.openprovider.eu</value>
            <prio>10</prio>
            <ttl>86400</ttl>
            <creationDate>2010-07-13 17:07:22</creationDate>
            <modificationDate>2010-07-13 17:07:22</modificationDate>
          </item>
          <item>
            <name>demozone.com</name>
            <type>NS</type>
            <value>ns3.openprovider.eu</value>
            <prio></prio>
            <ttl>86400</ttl>
            <creationDate>2010-07-13 17:07:22</creationDate>
            <modificationDate>2010-07-13 17:07:22</modificationDate>
          </item>
          <item>
            <name>demozone.com</name>
            <type>NS</type>
            <value>ns1.openprovider.nl</value>
            <prio></prio>
            <ttl>86400</ttl>
            <creationDate>2010-07-13 17:07:22</creationDate>
            <modificationDate>2010-07-13 17:07:22</modificationDate>
          </item>
          <item>
            <name>demozone.com</name>
            <type>NS</type>
            <value>ns2.openprovider.be</value>
            <prio></prio>
            <ttl>86400</ttl>
            <creationDate>2010-07-13 17:07:22</creationDate>
            <modificationDate>2010-07-13 17:07:22</modificationDate>
          </item>
          <item>
            <name>demozone.com</name>
            <type>SOA</type>
            <value>ns1.openprovider.nl dns@openprovider.eu 2010071300</value>
            <prio></prio>
            <ttl>86400</ttl>
            <creationDate>2010-07-13 17:07:22</creationDate>
            <modificationDate>2010-07-13 17:07:22</modificationDate>
          </item>
        </array>
      </records>
    </data>
  </reply>
</openXML>';
    }
}
