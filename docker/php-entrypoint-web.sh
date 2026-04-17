#!/bin/sh
set -e
cd /var/www

http_port="${PORT:-8080}"
if ! echo "$http_port" | grep -Eq '^[0-9]+$'; then
  echo "[entrypoint] Invalid PORT, falling back to 8080"
  http_port=8080
fi

sed "s/__HTTP_PORT__/${http_port}/g" /etc/nginx/templates/single-container.conf.template > /etc/nginx/conf.d/default.conf

if [ ! -f vendor/autoload.php ]; then
  echo "[entrypoint] Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

mkdir -p storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwX storage bootstrap/cache || true

nginx -t
exec /usr/bin/supervisord -c /etc/supervisord.conf -n
