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


# Tạo file cấu hình nginx
RUN printf 'server {\n    listen 8080;\n    server_name _;\n    root /var/www/public;\n    index index.php index.html;\n\n    location / {\n        try_files $uri $uri/ /index.php?$query_string;\n    }\n\n    location ~ \\.php$ {\n        fastcgi_pass   127.0.0.1:9000;\n        fastcgi_index  index.php;\n        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;\n        include        fastcgi_params;\n    }\n\n    location ~ /\\.ht {\n        deny all;\n    }\n}\n' > /etc/nginx/conf.d/default.conf

# Tạo file cấu hình supervisor
RUN echo "[supervisord]\nnodaemon=true\n\n[program:php-fpm]\ncommand=php-fpm\nnumprocs=1\nautostart=true\nautorestart=true\nstdout_logfile=/dev/stdout\nstderr_logfile=/dev/stderr\n\n[program:nginx]\ncommand=nginx -g 'daemon off;'\nautostart=true\nautorestart=true\nstdout_logfile=/dev/stdout\nstderr_logfile=/dev/stderr\n" > /etc/supervisor/conf.d/supervisord.conf

EXPOSE 8080

# Start nginx, php-fpm, migrate, then keep container running
CMD php artisan migrate --force && \
    /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf