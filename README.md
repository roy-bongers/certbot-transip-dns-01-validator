# certbot-transip-dns-01-validator
Certbot DNS-01 validation for wildcard certificates (ACME-v2)

I created this script to request wildcard SSL certificates from [Let's Encrypt][1]. You are required to do a DNS-01
challenge for which you need to create a DNS (TXT) record. [TransIP][3] has an API which allows you to automate this.
When you need to renew your certificate you also need to perform the DNS-01 challenge again. This should happen automatically.

## Requirements
Version 2.x has the following requirements. If you use older PHP versions you have to use the latest 1.x release.
Upgrading? See the [upgrade guide](#upgrade-guide).
* PHP 7.1 with XML and SOAP extensions enabled
* At least [Certbot][2] v0.22 for ACME-v2 support
* The [composer][3] package manager

## Installation
* Run `composer install --no-dev`
* Copy `config/transip.php.example` to `config/transip.php`
* Acquire an API key for TransIP in [your account][4] on their website
* Edit `config/transip.php` and set your login and private key.
* Make sure you set the access to this file to only allow your user to read the contents of this file (on linux `chmod og-rwx config/transip.php`)

## Request a wildcard certificate

Use this command to request the certificate. Replace "/path/to/" with the actual path on your system.
```shell
certbot certonly --manual --preferred-challenges=dns \
--manual-auth-hook /path/to/auth-hook \
--manual-cleanup-hook /path/to/cleanup-hook \
-d 'domain.com' -d '*.domain.com'
```

If you need to do some testing add the staging flag from Let's Encrypt:
```
--test-cert
```

## Upgrade guide
Version 2.0 is a complete rewrite of the code base and breaks with the original version.
 * Checkout the latest master branch
 * Follow the installation guide for 2.x
 * Remove the `Transip` folder. It is not used any more
 * You are ready to go!

## Contributors

When creating an issue please include a detailed description of what you are trying to execute and any output you receive. Feel free to fork the project and create a pull request. Make sure your code complies with the [PSR-1][5] and [PSR-2][6] coding standards.

[1]: https://letsencrypt.org/
[2]: https://certbot.eff.org/
[3]: https://www.transip.nl/transip/api/
[4]: https://www.transip.nl/cp/account/api/
[5]: https://www.php-fig.org/psr/psr-1/
[6]: https://www.php-fig.org/psr/psr-2/
[7]: https://getcomposer.org/download/
