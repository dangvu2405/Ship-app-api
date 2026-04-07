FROM php:8.2-fpm

# Cài đặt các thư viện hệ thống cần thiết
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    && rm -rf /var/lib/apt/lists/*

# Cài đặt PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Lấy Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Production image: cài dependency (không mount volume)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Phân quyền cho Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Route cache chỉ khi có file .env hợp lệ trong build (tránh fail CI khi thiếu APP_KEY)
RUN if [ -f .env ] || [ -f .env.production ]; then php artisan route:cache 2>/dev/null || true; fi

COPY docker/php-entrypoint.sh /usr/local/bin/php-entrypoint.sh
RUN chmod +x /usr/local/bin/php-entrypoint.sh

# PHP-FPM lắng nghe cổng 9000 (FastCGI, không phải HTTP) — kiểm tra TCP
HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/php-entrypoint.sh"]
