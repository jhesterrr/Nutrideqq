#!/bin/sh
# Use PORT provided by Railway environment, fallback to 80
PORT=${PORT:-80}

# Dynamically set the Nginx port before starting
echo "Starting nginx on port ${PORT}..."
sed -i "s/listen 80;/listen ${PORT};/g" /etc/nginx/nginx.conf

# Configure php-fpm to preserve environment variables (disable clear_env)
echo "Configuring php-fpm environment access..."
if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
    echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf
fi

# Start php-fpm in the background
php-fpm -D

# Start nginx in the foreground
nginx -g 'daemon off;'
