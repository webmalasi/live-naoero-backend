# PHP 8.4 for the Naoero Laravel backend: FPM with the PostgreSQL drivers,
# used for the web app, the queue worker and the scheduler.
FROM php:8.4-fpm-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev unzip git \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl zip bcmath opcache pcntl gd \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-naoero.ini
COPY fpm-pool.conf /usr/local/etc/php-fpm.d/zz-naoero.conf

WORKDIR /var/www/html
