# certbot-transip-dns-01-validator
Certbot DNS-01 validation for wildcard certificates (ACME-v2)

I created this script to request wildcard SSL certificates from [Let's Encrypt][1]. You are requred to do a DNS-01 
challenge for which you need to create a DNS (TXT) record.  [TransIP API][3] has an API which allows you to automate this. 
When you need to renew your certificate you also need to perform the DNS-01 challenge again. This should happen automatically.

Installation
------------

* Install [Certbot][2]. You need at least v0.22 for ACME-v2 which support wildcard certificates
* Download the [TransIP API][3] and extract the "Transip" folder in this project's directory
* Aquire an API key for TransIP in [your account][4] on their website
* Edit the Transip/ApiSettings.php and set your login and private key

Request a wildcard certificate
------------

```
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

Installing certbot v0.22.0
------------
A Debian PPA with this version is currently not yet available so I installed it from source: 
```
git clone https://github.com/certbot/certbot
cd certbot
git checkout v0.22.0
sudo ./certbot-auto --os-packages-only
./tools/venv.sh
source ./venv/bin/activate
certbot --version
```
You'll enter some kind of virtual environment. Now you can request your certificate. To leave this just type:
```
deactivate
```

[1]: https://letsencrypt.org/
[2]: https://certbot.eff.org/
[3]: https://www.transip.nl/transip/api/
[4]: https://www.transip.nl/cp/account/api/
