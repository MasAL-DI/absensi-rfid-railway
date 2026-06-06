#!/bin/sh
echo "Starting PHP-FPM..."
php-fpm -D
sleep 3
echo "Starting Nginx..."
nginx -g 'daemon off;'