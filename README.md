# certbot-transip-dns-01-validator
Certbot DNS-01 validation for wildcard certificates (ACME-v2)

I created this script to request wildcard SSL certificates from [Let's Encrypt][1]. You are required to do a DNS-01
challenge for which you need to create a DNS (TXT) record. [TransIP][3] has an API which allows you to automate this.
When you need to renew your certificate you also need to perform the DNS-01 challenge again. This should happen automatically.

## Requirements
* PHP with XML and SOAP extensions enabled
* At least [Certbot][2] v0.22 for ACME-v2 support

## Installation
* Run `composer install --no-dev`
* Copy `config/transip.example.php` to `config/transip.php`
* Acquire an API key for TransIP in [your account][4] on their website
* Edit `config/transip.php` and set your login and private key. Make sure you set the access to this file to only allow your user to read the contents of this file (on linux `chmod og-rwx config/transip.php`)

## Request a wildcard certificate

Use this command to request the certificate. Replace "/path/to/" with the actual path on your computer.
```shell
certbot --server https://acme-v02.api.letsencrypt.org/directory \
certonly --manual --preferred-challenges=dns \
--manual-auth-hook /path/to/auth-hook \
--manual-cleanup-hook /path/to/cleanup-hook \
-d 'domain.com' -d '*.domain.com'
```

If you need to do some testing use the staging environment from Let's Encrypt:
```
--server https://acme-staging-v02.api.letsencrypt.org/directory
```

## Contributors

When creating an issue please include a detailed description of what you are trying to execute and any output you receive. Feel free to fork the project and create a pull request. Make sure your code complies with the [PSR-1][5] and [PSR-2][6] coding standards.

[1]: https://letsencrypt.org/
[2]: https://certbot.eff.org/
[3]: https://www.transip.nl/transip/api/
[4]: https://www.transip.nl/cp/account/api/
[5]: https://www.php-fig.org/psr/psr-1/
[6]: https://www.php-fig.org/psr/psr-2/
