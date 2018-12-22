<?php
// Disable output buffering.
ob_implicit_flush(1);

echo 'Starting script' . PHP_EOL;

// Check prerequisites.
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

// Get environment variables.
$sleep = 30;
$domain = $_SERVER['CERTBOT_DOMAIN'];
$challenge = $_SERVER['CERTBOT_VALIDATION'];

// Fetch all domain names that we can manage.
echo 'Fetching domain names' . PHP_EOL;
try {
    $domains = Transip_DomainService::getDomainNames();
} catch (SoapFault $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

// Get different domain names.
$regex_domains = $domains;
if (1 !== preg_match('/^((.*)\.)?(' . implode('|', array_map('preg_quote', $domains)) . ')$/', $domain, $matches)) {
    echo 'Can\'t manage DNS for given domain (' . $domain . ').' . PHP_EOL;
    exit(1);
}
$base_domain = $matches[3];
$subdomain = $matches[2];
$challenge_key = '_acme-challenge' . ($subdomain ? '.' . $subdomain : '');

// Fetch alle DNS entries.
echo 'Getting domain info' . PHP_EOL;
try {
    $info = Transip_DomainService::getInfo($base_domain);
    $dnsEntries = $info->dnsEntries;
    if (!is_array($dnsEntries)) {
        $dnsEntries = array($dnsEntries);
    }
} catch (SoapFault $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

echo 'Altering DNS entries' . PHP_EOL;
if ('challenge' === $_SERVER['ACME_HOOK']) {
    // Add challenge DNS entry.
    $dnsEntries[] = new Transip_DnsEntry($challenge_key, 60, Transip_DnsEntry::TYPE_TXT, $challenge);
} elseif ('cleanup' === $_SERVER['ACME_HOOK']) {
    // Remove challange DSN entries.
    $dnsEntries = array_filter($dnsEntries, function ($dnsEntry) {
        return false === strpos($dnsEntry->name, '_acme-challenge');
    });
    $dnsEntries = array_values($dnsEntries);
}

// Save new DNS records
echo 'Saving DNS records' . PHP_EOL;
try {
    Transip_DomainService::setDnsEntries($base_domain, $dnsEntries);
} catch (SoapFault $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

// Sleep when creating a challenge so DNS records can be updated.
if ('challenge' === $_SERVER['ACME_HOOK']) {
    // Get all nameservers.
    $nameservers = array();
    foreach ($info->nameservers as $nameserver) {
        $nameservers[] = $nameserver->hostname;
    }

    $updated_records = 0;
    $total_nameservers = count($nameservers);
    echo 'Total nameservers ' . $total_nameservers . PHP_EOL;

    // Loop until all nameservers have up-to-date records.
    while ($updated_records < $total_nameservers) {
        echo 'While loop' . PHP_EOL;
        // Query each nameserver and make sure the TXT record exists.
        foreach ($nameservers as $ns_index => $nameserver) {
            $dns_query = new DNSQuery($nameserver);
            $dns_result = $dns_query->Query($challenge_key . '.' . $base_domain, 'TXT');

            if ((false === $dns_result) || (false !== $dns_query->error)) {
                var_dump($dns_result);
                echo $dns_query->lasterror . PHP_EOL;
                exit(1);
            }

            // Process results.
            foreach ($dns_result->results as $result) {
                if ($result->data === $challenge) {
                    // Update the amount of updated records.
                    $updated_records++;
                    // No need to check updated nameservers again.
                    echo 'Unsetting' . $ns_index . PHP_EOL;
                    echo 'Currently updated records ' . $updated_records . PHP_EOL;
                    unset($nameservers[$ns_index]);
                }
                echo 'Looping results' . PHP_EOL;
            }
        }
        echo 'looped all nameservers' . PHP_EOL;

        if ($updated_records < $total_nameservers) {
            // Sleep if not all nameserver have updated yet.
            $time = date('Y-m-d H:i:s');
            echo $time . ' - ' . $updated_records . ' of ' . $total_nameservers . ' nameservers are ready. ';
            echo sprintf('Retrying in %d seconds', $sleep) . PHP_EOL;
            flush();
            sleep($sleep);
            $updated_records = 0;
        } else {
            echo 'All records updated.' . PHP_EOL;
        }
    }
    echo 'Exiting while loop...' . PHP_EOL;
}
echo 'Exiting script..' . PHP_EOL;
exit(0);
