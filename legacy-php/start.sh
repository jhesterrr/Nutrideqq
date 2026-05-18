#!/bin/sh
# Use PORT provided by Railway environment, fallback to 80
PORT=${PORT:-80}

# Dynamically set the Nginx port before starting
echo "Starting nginx on port ${PORT}..."
sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/nginx.conf

# Start php-fpm in the background
php-fpm -D

# Start nginx in the foreground
nginx -g 'daemon off;'
