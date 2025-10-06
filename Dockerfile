#############################
# Stage 1: Base Composer
#############################
FROM composer:2.8 AS composer_base

#############################
# Stage 2: Vendor install (prod or dev)
#############################
FROM composer_base AS vendor_builder

ARG APP_ENV=dev
ENV APP_ENV=${APP_ENV}

WORKDIR /app

# Copier uniquement les fichiers Composer pour profiter du cache Docker
COPY src/backend/composer.json src/backend/composer.lock* ./

# Installation dépendances (prod ou dev selon APP_ENV)
RUN if [ "$APP_ENV" = "prod" ]; then \
			composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader; \
		else \
			composer install --prefer-dist --no-progress --no-interaction; \
		fi

#############################
# Stage 3: Runtime PHP-FPM
#############################
FROM php:8.2-fpm AS runtime

ARG APP_ENV=dev
ENV APP_ENV=${APP_ENV}

# Dépendances système
RUN apt-get update \
		&& apt-get install -y --no-install-recommends git unzip zip \
		&& rm -rf /var/lib/apt/lists/*

# Extensions PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copier Composer binaire
COPY --from=composer_base /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/src/backend

# Copier d'abord le vendor installé (depuis vendor_builder)
COPY --from=vendor_builder /app/vendor ./vendor
COPY --from=vendor_builder /app/composer.json /app/composer.lock ./

# Puis copier le reste du code (seulement backend)
COPY src/backend/ .

# Permissions (optionnel selon stratégies)
RUN chown -R www-data:www-data /var/www/html/src/backend

# Healthcheck simple (optionnel)
# HEALTHCHECK CMD php -v || exit 1

# Par défaut
CMD ["php-fpm"]
