FROM php:8.3-fpm

# Cài extension cần thiết
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# Cài composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy code
COPY . .

# Cài dependency
RUN composer install

# Set quyền
RUN chown -R www-data:www-data /var/www

CMD ["php-fpm"]