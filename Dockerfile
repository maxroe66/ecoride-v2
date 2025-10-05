FROM php:8.2-fpm

# Installe les extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copie le code source dans le conteneur
COPY . /var/www/html

WORKDIR /var/www/html

# Permissions
RUN chown -R www-data:www-data /var/www/html
