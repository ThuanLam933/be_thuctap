FROM php:8.3-cli

# Cài package cần thiết
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
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

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000