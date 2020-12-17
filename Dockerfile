FROM php:latest
WORKDIR /opt/certbot-dns-transip

COPY . .
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y certbot zlib1g-dev libzip-dev libxml2-dev unzip && \
  docker-php-ext-install soap zip && \
  ln -s /usr/local/bin/php /usr/bin/php && \
  composer install --no-dev --prefer-dist --no-progress

ENTRYPOINT ["certbot"]
