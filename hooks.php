<?php
if ('cli' !== PHP_SAPI) {
	echo 'Can only be called from CLI' . PHP_EOL;
	exit(1);
}

if (!isset($_SERVER['CERTBOT_DOMAIN'], $_SERVER['CERTBOT_VALIDATION'], $_SERVER['ACME_HOOK'])) {
	echo 'Missing required environment variables: CERTBOT_DOMAIN, CERTBOT_VALIDATION, ACME_HOOK.' . PHP_EOL;
	exit(1);
}

require_once('dns.php');
require_once('Transip/DomainService.php');

// get environment variables.
$sleep = 30;
$domain = $_SERVER['CERTBOT_DOMAIN'];
$challenge = $_SERVER['CERTBOT_VALIDATION'];

// fetch all domain names that we can managen.
try {
	$domains = Transip_DomainService::getDomainNames();
}
catch (SoapFault $e) {
	echo $e->getMessage() . PHP_EOL;
	exit(1);
}

// get different domain names.
$regex_domains = $domains;
if (1 !== preg_match('/^((.*)\.)?(' . implode('|', array_map('preg_quote', $domains)) . ')$/', $domain, $matches)) {
	echo 'Can\'t manage DNS for given domain (' . $domain . ').' . PHP_EOL;
	exit(1);
}
$base_domain = $matches[3];
$subdomain = $matches[2];
$challenge_key = '_acme-challenge' . ($subdomain ? '.'.$subdomain : '');

// fetch alle DNS entries.
try {
	$info = Transip_DomainService::getInfo($base_domain);
	$dnsEntries = $info->dnsEntries;
	if (!is_array($dnsEntries)) {
		$dnsEntries = array($dnsEntries);
	}
}
catch (SoapFault $e) {
	echo $e->getMessage() . PHP_EOL;
	exit(1);
}

if ('challenge' === $_SERVER['ACME_HOOK']) {
	// add challenge DNS entry.
	$dnsEntries[] = new Transip_DnsEntry($challenge_key, 60, Transip_DnsEntry::TYPE_TXT, $challenge);
}
elseif ('cleanup' === $_SERVER['ACME_HOOK']) {
	// remove challange DSN entries.
	$dnsEntries = array_filter($dnsEntries, function($dnsEntry) {
		return false === strpos($dnsEntry->name,'_acme-challenge');
	});
}

// save new DNS records
try {
	Transip_DomainService::setDnsEntries($base_domain, $dnsEntries);
}
catch (SoapFault $e) {
	echo $e->getMessage() . PHP_EOL;
	exit(1);
}

// sleep when creating a challenge so DNS records can be updated.
if ('challenge' === $_SERVER['ACME_HOOK']) {
	// get all nameservers.
	$nameservers = array();
	foreach ($info->nameservers as $nameserver) {
		$nameservers[] = $nameserver->hostname;
	}

	// loop until all nameservers have up-to-date records.
	$updated_records = 0;
	while ($updated_records < count($nameservers)) {
		// query each nameserver and make sure the TXT record exists.
		foreach ($nameservers as $nameserver) {
			$dns_query = new DNSQuery($nameserver);
			$dns_result = $dns_query->Query($challenge_key . '.' . $base_domain, 'TXT');

			if ((false === $dns_result) || (false !== $dns_query->error)) {
				echo $dns_query->lasterror . PHP_EOL;
				exit(1);
			}

			// process results.
			foreach ($dns_result->results as $result) {
				if ($result->data === $challenge) {
					$updated_records++;
				}
			}
		}

		if ($updated_records < count($nameservers)) {
			echo sprintf('Result not ready, retrying in %d seconds', $sleep) . PHP_EOL;
			sleep($sleep);
			$updated_records = 0;
		}
	}
}
exit(0);
