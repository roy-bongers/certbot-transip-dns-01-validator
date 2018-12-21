<?php
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
    try {
        $domains = Transip_DomainService::getDomainNames();
    }
    catch (SoapFault $e) {
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
    $challenge_key = '_acme-challenge' . ($subdomain ? '.'.$subdomain : '');

    // Fetch all DNS entries.
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
        // Add challenge DNS entry.
        $dnsEntries[] = new Transip_DnsEntry($challenge_key, 60, Transip_DnsEntry::TYPE_TXT, $challenge);
    }
    elseif ('cleanup' === $_SERVER['ACME_HOOK']) {
        // Remove challange DSN entries.
        $dnsEntries = array_filter($dnsEntries, function($dnsEntry) {
            return false === strpos($dnsEntry->name,'_acme-challenge');
        });
        $dnsEntries = array_values($dnsEntries);
    }

    // Save new DNS records.
    try {
        Transip_DomainService::setDnsEntries($base_domain, $dnsEntries);
    }
    catch (SoapFault $e) {
        echo $e->getMessage() . PHP_EOL;
        exit(1);
    }

    // Sleep when creating a challenge so DNS records can be updated.
    if ($_SERVER['ACME_HOOK'] === 'challenge') {
        // Get all nameservers.
        $nameservers = array();
        foreach ($info->nameservers as $nameserver) {
            $nameservers[] = $nameserver->hostname;
        }

        // Loop until all nameservers have up-to-date records.
        $updated_records = 0;
        $needed_updated_records = count($nameservers);

        while ($updated_records < $needed_updated_records) {
            // Set the index of the nameserver so we can unset it.
            $ns_idx=0;

            // Query each nameserver and make sure the TXT record exists.
            foreach ($nameservers as $nameserver) {
                $dns_query = new DNSQuery($nameserver);
                $dns_result = $dns_query->Query($challenge_key . '.' . $base_domain, 'TXT');

                if ($dns_query->error) {
                    echo $dns_query->lasterror . PHP_EOL;
                    exit(1);
                }

                // Process results.
                foreach ($dns_result->results as $result) {
                    if ($result->data === $challenge) {
                        // Update the amount of updated records.
                        $updated_records++;
                        // Remove the nameservers from the nameservers to check as there's no need to check it anymore.
                        unset($nameservers[$ns_idx]);
                    }
                }

                $ns_idx++;
            }

            if ($updated_records < $needed_updated_records) {
		// Sleep if not all nameserver have updated yet.
                $time = date("d/m/Y H:i:s");
                print $time . ' - ' . $updated_records . ' of ' . $needed_updated_records . ' nameservers are ready. ';
                print 'Retrying in ' . $sleep . ' Seconds...' . PHP_EOL;
                sleep($sleep);
            }
        }
    }

    exit(0);
?>
