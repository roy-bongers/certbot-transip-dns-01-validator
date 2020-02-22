<p align="center">
    <a href="https://github.com/roy-bongers/certbot-transip-dns-01-validator/actions?query=branch%3Amaster"><img alt="Workflow status" src="https://img.shields.io/github/workflow/status/roy-bongers/certbot-transip-dns-01-validator/Run%20PHPUnit%20&%20PHPCS%20syle%20check" /></a>
    <a href="https://github.com/roy-bongers/certbot-transip-dns-01-validator/releases"><img alt="Latest GitHub tag" src="https://img.shields.io/github/v/tag/roy-bongers/certbot-transip-dns-01-validator" /></a>
    <a href="https://github.com/roy-bongers/certbot-transip-dns-01-validator/blob/master/LICENSE"><img alt="License GPL-3.0" src="https://img.shields.io/github/license/roy-bongers/certbot-transip-dns-01-validator" /></a>
</p>

# certbot-transip-dns-01-validator
Certbot DNS-01 validation for wildcard certificates (ACME-v2)

I created this script to request wildcard SSL certificates from [Let’s Encrypt][1]. You are required to do a DNS-01
challenge for which you need to create a DNS (TXT) record. [TransIP][3] has an API which allows you to automate this.
When you need to renew your certificate you also need to perform the DNS-01 challenge again. This should happen
automatically.

## Requirements
Version 2 has the following requirements. If you use older PHP versions you have to use the latest 1.x release.
Upgrading? See the [upgrade guide](#upgrade-guide).
* PHP 7.2 with XML and SOAP extensions enabled
* [Certbot][2] >= v0.22
* The [composer][3] package manager

## Installation
* Run `composer install --no-dev`
* Copy `config/config.php.example` to `config/config.php`
* Acquire an API key for TransIP in [your account][4] on their website
* Edit `config/config.php` and set your login and private key.
* Make sure you set the access to this file to only allow your user to read the contents of this file (on linux
 `chmod og-rwx config/config.php`)

## Request a wildcard certificate

Use this command to request the certificate. Replace "/path/to/" with the actual path on your system.
It takes a couple of minutes for the nameservers to be updated. Please be patient until the validation completes.
```shell
certbot certonly --manual --preferred-challenges=dns \
--manual-auth-hook /path/to/auth-hook --manual-cleanup-hook /path/to/cleanup-hook \
-d 'domain.com' -d '*.domain.com'
```

If you need to do some testing add the staging flag to the certbot command:
```
--test-cert
```

To automatically renew your certificate add the Certbot renew command in a cron job so it runs at least monthly.
```shell
/usr/bin/certbot renew
````

## Docker
There is also a docker container which you can use. You can either bind mount the `config` folder or use `ENV` variables.
These variables are available: `TRANSIP_LOGIN`, `TRANSIP_PRIVATE_KEY`, `LOGLEVEL`, `LOGFILE`.
Only the first two variables are required.

For information about values see `config/config.php.example`. Multiline values (the private key) can be a bit harder
to set. Make sure the entire private key is stored in the `TRANSIP_PRIVATE_KEY` variable!

The application runs in the `/opt/certbot-dns-transip` directory and the certificates are created in `/etc/letsencrypt`.

```shell script
docker run -ti \
--mount type=bind,source="${PWD}"/letsencrypt,target="/etc/letsencrypt" \
--mount type=bind,source="${PWD}"/config,target="/opt/certbot-dns-transip/config" \
--mount type=bind,source="${PWD}"/logs,target="/opt/certbot-dns-transip/logs" \
rbongers/certbot-dns-transip \
certonly --manual --preferred-challenge=dns  \
--manual-auth-hook=/opt/certbot-dns-transip/auth-hook \
--manual-cleanup-hook=/opt/certbot-dns-transip/cleanup-hook \
-d 'domain.com' -d '*.domain.com'
```

And to renew certificates:
```shell script
docker run -ti \
--mount type=bind,source="${PWD}"/letsencrypt,target="/etc/letsencrypt" \
--mount type=bind,source="${PWD}"/config,target="/opt/certbot-dns-transip/config" \
--mount type=bind,source="${PWD}"/logs,target="/opt/certbot-dns-transip/logs" \
rbongers/certbot-dns-transip \
renew
```

## Supported platforms
The code is tested on a Debian based Linux distribution (Ubuntu LTS) and currently supported PHP versions (>= 7.2).
It probably works fine on other systems and versions of PHP but no guarantees are made.

## Upgrade guide
Version 2.0 is a complete rewrite of the code base and breaks with the original version. Follow these steps to upgrade:
 1. Checkout the latest master branch
 1. Follow the [installation guide](#installation)
 1. Remove the `Transip` folder after copying your login and private key to `config/config.php`
 1. You are ready to go!
 
## Contributors
When creating an issue please include a detailed description of what you are trying to execute and any output you
receive. Feel free to fork the project and create a pull request. Make sure your code complies with the [PSR-12][5]
coding standards.

[1]: https://letsencrypt.org/
[2]: https://certbot.eff.org/
[3]: https://www.transip.nl/transip/api/
[4]: https://www.transip.nl/cp/account/api/
[5]: https://www.php-fig.org/psr/psr-12/
[7]: https://getcomposer.org/download/
