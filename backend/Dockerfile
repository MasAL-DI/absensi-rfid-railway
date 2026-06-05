FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    apache2 \
    libapache2-mod-php8.2 \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true
RUN a2enmod mpm_prefork php8.2 rewrite

COPY . /var/www/html/
COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]