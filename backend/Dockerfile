FROM php:8.2-fpm-alpine

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN apk add --no-cache nginx

COPY . /var/www/html/
COPY nginx.conf /etc/nginx/nginx.conf
COPY start.sh /start.sh

RUN chmod +x /start.sh && \
    mkdir -p /run/nginx /var/run && \
    chown -R www-data:www-data /var/www/html && \
    sed -i 's|listen = 127.0.0.1:9000|listen = 0.0.0.0:9000|' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's|;listen.owner = nobody|listen.owner = www-data|' /usr/local/etc/php-fpm.d/www.conf && \
    sed -i 's|;listen.group = nobody|listen.group = www-data|' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 80

CMD ["/start.sh"]