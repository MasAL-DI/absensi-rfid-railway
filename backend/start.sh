#!/bin/sh
php-fpm -D -y /usr/local/etc/php-fpm.conf
sleep 2
echo "Starting Nginx..."
nginx -g 'daemon off;'