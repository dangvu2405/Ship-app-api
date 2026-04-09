#!/bin/sh
set -e
cd /var/www

# Khi mount volume mã nguồn, thư mục vendor có thể trống — cài dependency trước khi chạy php-fpm
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Đảm bảo thư mục runtime có quyền ghi khi bind mount source từ host
mkdir -p storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

exec docker-php-entrypoint php-fpm
