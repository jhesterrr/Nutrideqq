FROM php:8.2-fpm

# Install dependencies
RUN apt-get update && apt-get install -y nginx unzip curl libzip-dev && \
    docker-php-ext-install pdo pdo_mysql mysqli zip

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy nginx config
COPY nginx.conf /etc/nginx/nginx.conf

# Copy PHP config
COPY php.ini /usr/local/etc/php/php.ini

# Copy app files
COPY . /var/www/html/

# Install PHP dependencies
WORKDIR /var/www/html
RUN composer install --no-interaction --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Start both php-fpm and nginx
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
