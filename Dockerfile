FROM php:latest
WORKDIR /opt/certbot-dns-transip
RUN apt-get update && apt-get install -y certbot zlib1g-dev libzip-dev libxml2-dev unzip
RUN docker-php-ext-install soap zip
RUN ln -s /usr/local/bin/php /usr/bin/php

COPY . .
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev

ENTRYPOINT ["certbot"]
