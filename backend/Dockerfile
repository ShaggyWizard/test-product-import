# syntax=docker/dockerfile:1

# ---------- base: PHP-FPM с нужными расширениями ----------
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache libzip libpng icu-libs rabbitmq-c postgresql-libs \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libzip-dev libpng-dev icu-dev \
        rabbitmq-c-dev postgresql-dev linux-headers \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql zip gd intl opcache \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del .build-deps \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

WORKDIR /var/www/app

# ---------- vendor: зависимости без dev ----------
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------- dev: для локальной разработки и тестов ----------
FROM base AS dev
RUN apk add --no-cache git unzip
# исходники монтируются томом, зависимости ставит `make install`

# ---------- prod: итоговый образ ----------
FROM base AS prod
COPY --from=vendor /var/www/app/vendor ./vendor
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && mkdir -p var/uploads var/cache public/media \
    && chown -R www-data:www-data var public/media
USER www-data
