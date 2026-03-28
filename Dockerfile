FROM php:8.2-fpm

# Cài đặt các thư viện hệ thống cần thiết
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip

# Cài đặt PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Lấy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Cài đặt dependencies và tối ưu cho Production
RUN composer install --no-dev --optimize-autoloader

# Phân quyền cho Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Cấu hình cache cho Production (Route, Configuration, Views)
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Bổ sung Health Check cho container
HEALTHCHECK --interval=30s --timeout=5s \
    CMD curl -f http://localhost:9000/api/health || exit 1

EXPOSE 9000
CMD ["php-fpm"]
