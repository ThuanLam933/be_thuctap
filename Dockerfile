FROM php:8.3-fpm

# Cài package cần thiết
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev nginx supervisor \
    && docker-php-ext-install pdo pdo_mysql mbstring bcmath gd

# Cài composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy code
COPY . .

# Cài dependency (production)
RUN composer install --no-dev --optimize-autoloader

# Fix permission
RUN chmod -R 777 storage bootstrap/cache

# Laravel optimize
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Copy nginx config
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Supervisor config
COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080

# Start nginx, php-fpm, migrate, then keep container running
CMD php artisan migrate --force && \
    /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf