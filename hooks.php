<?php
// Disable output buffering.
ob_implicit_flush(1);

// Define log file to write to.
if (is_writable('/var/log/')) {
    define('LOG_FILE', '/var/log/certbot-transip-dns-01.log');
} else {
    define('LOG_FILE', __DIR__ . '/log.txt');
}

/**
 * Append given string to log file.
 *
 * @param string $str The message to display and write to the log file.
 */
function log_msg($str)
{
    echo $str . PHP_EOL;
    flush();

    // Write to log file.
    if (defined('LOG_FILE')) {
        if (!file_exists(LOG_FILE)) {
            touch(LOG_FILE);
        }
        file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . ' ' . $str . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

log_msg(__DIR__ . '/log.txt');

// Check prerequisites.
if ('cli' !== PHP_SAPI) {
    log_msg('Can only be called from CLI');
    exit(1);
}

if (!isset($_SERVER['CERTBOT_DOMAIN'], $_SERVER['CERTBOT_VALIDATION'], $_SERVER['ACME_HOOK'])) {
    log_msg('Missing required environment variables: CERTBOT_DOMAIN, CERTBOT_VALIDATION, ACME_HOOK.');
    exit(1);
}

// Get environment variables.
$sleep = 30;
$domain = $_SERVER['CERTBOT_DOMAIN'];
$challenge = $_SERVER['CERTBOT_VALIDATION'];

log_msg(sprintf('Applying the %s hook for %s with value %s', $_SERVER['ACME_HOOK'], $domain, $challenge));

// Make sure TransIP API library is installed.
if (!file_exists(__DIR__ . '/Transip/DomainService.php')) {
    log_msg('TransIP API library missing, download from https://www.transip.nl/transip/api/ .');
    exit(1);
}
require_once('dns.php');
require_once('Transip/DomainService.php');

// Fetch all domain names that we can manage.
try {
    $domains = Transip_DomainService::getDomainNames();
} catch (SoapFault $e) {
    log_msg($e->getMessage());
    exit(1);
}

// Get different domain names.
$regex_domains = $domains;
if (1 !== preg_match('/^((.*)\.)?(' . implode('|', array_map('preg_quote', $domains)) . ')$/', $domain, $matches)) {
    log_msg(sprintf('Can\'t manage DNS for given domain (%s).', $domain));
    exit(1);
}
$base_domain = $matches[3];
$subdomain = $matches[2];
$challenge_key = '_acme-challenge' . ($subdomain ? '.' . $subdomain : '');

// Fetch all DNS entries.
try {
    $info = Transip_DomainService::getInfo($base_domain);
    $dnsEntries = $info->dnsEntries;
    if (!is_array($dnsEntries)) {
        $dnsEntries = array($dnsEntries);
    }
} catch (SoapFault $e) {
    log_msg($e->getMessage());
    exit(1);
}
log_msg('Fetched DNS info from TransIP API');

if ('challenge' === $_SERVER['ACME_HOOK']) {
    // Add challenge DNS entry.
    $dnsEntries[] = new Transip_DnsEntry($challenge_key, 60, Transip_DnsEntry::TYPE_TXT, $challenge);
    log_msg(sprintf('Adding challenge DNS record (%s 60 TXT %s)', $challenge_key, $challenge));
} elseif ('cleanup' === $_SERVER['ACME_HOOK']) {
    // Remove challenge DSN entries.
    foreach ($dnsEntries as $index => $dnsEntry) {
        if (false !== strpos($dnsEntry->name, '_acme-challenge') && $dnsEntry->content === $challenge) {
            log_msg(sprintf('Removing challenge DNS record(%s 60 TXT %s)', $dnsEntry->name, $dnsEntry->content));
            unset($dnsEntries[$index]);
        }
    }
    $dnsEntries = array_values($dnsEntries);
}

// Save new DNS records.
try {
    Transip_DomainService::setDnsEntries($base_domain, $dnsEntries);
} catch (SoapFault $e) {
    log_msg($e->getMessage());
    exit(1);
}
log_msg('Saved changed DNS entries');

// Sleep when creating a challenge so DNS records can be updated.
if ('challenge' === $_SERVER['ACME_HOOK']) {
    // Get all nameservers.
    $nameservers = array();
    foreach ($info->nameservers as $nameserver) {
        $nameservers[] = $nameserver->hostname;
    }

    $updated_records = 0;
    $total_nameservers = count($nameservers);
    log_msg('Total nameservers ' . $total_nameservers);

    // Loop until all nameservers have up-to-date records.
    while ($updated_records < $total_nameservers) {
        // Query each nameserver and make sure the TXT record exists.
        foreach ($nameservers as $ns_index => $nameserver) {
            $dns_query = new DNSQuery($nameserver);
            $dns_result = $dns_query->Query($challenge_key . '.' . $base_domain, 'TXT');

            if ((false === $dns_result) || (false !== $dns_query->error)) {
                log_msg($dns_query->lasterror);
                exit(1);
            }

            // Process results.
            foreach ($dns_result->results as $result) {
                if ($result->data === $challenge) {
                    // Update the amount of updated records.
                    $updated_records++;
                    // No need to check the already updated nameservers again.
                    unset($nameservers[$ns_index]);
                }
            }
        }

        if ($updated_records < $total_nameservers) {
            // Sleep if not all nameserver have updated yet.
            log_msg(sprintf(
                '%d of %d nameservers are ready. Retrying in %d seconds',
                $updated_records,
                $total_nameservers,
                $sleep
            ));
            sleep($sleep);
        } else {
            log_msg('All DNS records updated');
        }
    }
}
log_msg(sprintf('exiting %s hook', $_SERVER['ACME_HOOK']));
exit(0);
