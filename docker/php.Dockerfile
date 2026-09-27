# Single-stage PHP image: just enough to run Laravel in development.
#
# Deliberately NOT a production image. There is no nginx, no PHP-FPM and no
# supervisord here — this exists so `docker compose up` gives a working local
# app with the least moving parts possible. Production deployment is a
# different target entirely; see docs/DEPLOY-WASMER.md.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev libpng-dev libonig-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" pdo_mysql zip intl gd bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Laravel boots faster with these; the CLI server handles the concurrency.
RUN { \
      echo 'realpath_cache_size=4096K'; \
      echo 'realpath_cache_ttl=600'; \
      echo 'memory_limit=512M'; \
    } > /usr/local/etc/php/conf.d/app.ini

EXPOSE 8000

# artisan serve is single-threaded, which makes one slow page look like a
# hung server. PHP_CLI_SERVER_WORKERS forks real workers instead.
ENV PHP_CLI_SERVER_WORKERS=8

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
