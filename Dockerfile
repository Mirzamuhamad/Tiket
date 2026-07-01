FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql zip \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html
COPY docker/entrypoint.sh /usr/local/bin/tiket-entrypoint

RUN chmod +x /usr/local/bin/tiket-entrypoint \
    && mkdir -p /var/www/html/uploads/tickets \
    && chown -R www-data:www-data /var/www/html/uploads

ENTRYPOINT ["tiket-entrypoint"]
CMD ["apache2-foreground"]
