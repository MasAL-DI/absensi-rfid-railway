#!/bin/sh
php-fpm -D
sleep 2
echo "Starting Nginx..."
nginx -g 'daemon off;'