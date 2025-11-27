# Multi-stage build for PHP-FPM with Composer and extensions
FROM php:8.3-fpm-alpine AS base

ARG TZ=Europe/Paris
ENV TZ=${TZ} \
    APP_ENV=dev \
    PHP_MEMORY_LIMIT=512M

RUN apk add --no-cache bash tzdata git autoconf build-base libzip-dev oniguruma-dev icu-dev linux-headers openssl-dev mysql-client curl-dev cyrus-sasl-dev yaml-dev shadow libpng-dev freetype-dev libjpeg-turbo-dev

# Timezone
RUN cp /usr/share/zoneinfo/${TZ} /etc/localtime && echo ${TZ} > /etc/timezone

# Install PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql intl opcache mbstring zip

# PECL MongoDB
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Xdebug only in dev
ARG ENABLE_XDEBUG=1
RUN if [ "$ENABLE_XDEBUG" = "1" ]; then pecl install xdebug \
    && docker-php-ext-enable xdebug; fi

# GD (optional if you need image processing)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

WORKDIR /var/www

RUN php -v

# Install Composer manually (lighter than multi-stage mismatch)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy composer files first for layer cache
COPY composer.json composer.lock* ./
RUN git config --global --add safe.directory /var/www && \
    composer install --no-scripts --no-interaction --no-progress --prefer-dist || \
    composer install --no-scripts --no-interaction --no-progress --prefer-dist --ignore-platform-req=ext-mongodb

# Copy source after dependencies
COPY --chown=www-data:www-data app/ ./app/
COPY --chown=www-data:www-data public/ ./public/
COPY --chown=www-data:www-data frontend/ ./frontend/
COPY --chown=www-data:www-data storage/ ./storage/
RUN composer dump-autoload -o

# PHP ini overrides
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-custom.ini

# PHP-FPM configuration (clear_env = no pour env vars)
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

RUN usermod -u 1000 www-data && groupmod -g 1000 www-data || true

EXPOSE 9000
CMD ["php-fpm"]
