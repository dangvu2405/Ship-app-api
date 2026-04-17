# Multi-stage:
#   - `fpm`: chỉ PHP-FPM (dùng với docker-compose + service nginx riêng).
#   - `web` (mặc định): Nginx + PHP-FPM — HTTP trên 0.0.0.0:$PORT (Render / Fly / Railway).
#
# Build:
#   docker build -t ship-api .                 → image `web`
#   docker build -t ship-api-fpm --target fpm .
#
# Biến môi trường: PORT (PaaS gán động). Mặc định 8080 nếu không có.

FROM php:8.2-fpm AS base

RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chown -R www-data:www-data storage bootstrap/cache

RUN if [ -f .env ] || [ -f .env.production ]; then php artisan route:cache 2>/dev/null || true; fi

# ─── FPM only (docker-compose service `app`) ───────────────────────────────
FROM base AS fpm

COPY docker/php-entrypoint.sh /usr/local/bin/php-entrypoint.sh
RUN chmod +x /usr/local/bin/php-entrypoint.sh

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/php-entrypoint.sh"]

# ─── HTTP + FPM (một container — cloud) ───────────────────────────────────
FROM base AS web

RUN apt-get update && apt-get install -y nginx supervisor \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx/single-container.conf.template /etc/nginx/templates/single-container.conf.template
COPY docker/supervisord-web.conf /etc/supervisord.conf
COPY docker/php-entrypoint-web.sh /usr/local/bin/php-entrypoint.sh
COPY docker/healthcheck-web.sh /usr/local/bin/healthcheck-web.sh
RUN chmod +x /usr/local/bin/php-entrypoint.sh /usr/local/bin/healthcheck-web.sh

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD /usr/local/bin/healthcheck-web.sh

ENTRYPOINT ["/usr/local/bin/php-entrypoint.sh"]
