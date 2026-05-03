# Apache + PHP extensions for EHR1 Data app (local testing only).
FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql mysqli \
    && a2enmod rewrite headers

# App is mounted at /var/www/html/ehr1-data; document root stays /var/www/html.
