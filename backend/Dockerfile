FROM php:8.2-fpm-alpine

RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN apk add --no-cache nginx

COPY . /var/www/html/
COPY nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /run/nginx && \
    chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD sh -c "php-fpm -D && sleep 2 && nginx -g 'daemon off;'"