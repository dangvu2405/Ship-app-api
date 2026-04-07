#!/bin/sh
set -e
cd /var/www

# Khi mount volume mã nguồn, thư mục vendor có thể trống — cài dependency trước khi chạy php-fpm
if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

exec docker-php-entrypoint php-fpm
