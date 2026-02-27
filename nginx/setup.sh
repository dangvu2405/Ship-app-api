#!/bin/bash

# Script tự động setup Nginx + PHP-FPM cho Laravel API

set -e

echo "🚀 Setting up Nginx + PHP-FPM for Laravel API..."

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

# Get project directory
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo -e "${GREEN}Project directory: $PROJECT_DIR${NC}"

# Detect PHP version
PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
echo -e "${GREEN}Detected PHP version: $PHP_VERSION${NC}"

# Check if PHP-FPM is installed
if ! command -v php-fpm${PHP_VERSION} &> /dev/null; then
    echo -e "${YELLOW}PHP-FPM not found. Installing...${NC}"
    apt update
    apt install -y php${PHP_VERSION}-fpm php${PHP_VERSION}-cli php${PHP_VERSION}-common \
        php${PHP_VERSION}-mysql php${PHP_VERSION}-xml php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-curl php${PHP_VERSION}-zip php${PHP_VERSION}-gd
fi

# Check PHP-FPM status
if systemctl is-active --quiet php${PHP_VERSION}-fpm; then
    echo -e "${GREEN}PHP-FPM is running${NC}"
else
    echo -e "${YELLOW}Starting PHP-FPM...${NC}"
    systemctl start php${PHP_VERSION}-fpm
    systemctl enable php${PHP_VERSION}-fpm
fi

# Update nginx config with correct PHP version and paths
CONFIG_FILE="$PROJECT_DIR/nginx/nginx.conf"
if [ -f "$CONFIG_FILE" ]; then
    # Replace PHP version in config
    sed -i "s/php8.2-fpm/php${PHP_VERSION}-fpm/g" "$CONFIG_FILE"
    sed -i "s/php8.1-fpm/php${PHP_VERSION}-fpm/g" "$CONFIG_FILE"
    
    # Replace project path
    sed -i "s|/home/vumoi/company_ship/ship-app-api|$PROJECT_DIR|g" "$CONFIG_FILE"
    
    echo -e "${GREEN}Updated config with PHP version $PHP_VERSION and project path${NC}"
fi

# Copy nginx config
echo -e "${YELLOW}Copying Nginx configuration...${NC}"
cp "$PROJECT_DIR/nginx/nginx.conf" /etc/nginx/sites-available/laravel-api

# Create symlink
if [ ! -L /etc/nginx/sites-enabled/laravel-api ]; then
    ln -s /etc/nginx/sites-available/laravel-api /etc/nginx/sites-enabled/
    echo -e "${GREEN}Created symlink${NC}"
fi

# Fix Laravel permissions
echo -e "${YELLOW}Fixing Laravel permissions...${NC}"
cd "$PROJECT_DIR"

# Storage and cache must be writable
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Create storage link if not exists
if [ ! -L public/storage ]; then
    sudo -u www-data php artisan storage:link
    echo -e "${GREEN}Created storage symlink${NC}"
fi

# Test nginx config
echo -e "${YELLOW}Testing Nginx configuration...${NC}"
if nginx -t; then
    echo -e "${GREEN}Nginx configuration is valid${NC}"
else
    echo -e "${RED}Nginx configuration has errors!${NC}"
    exit 1
fi

# Reload services
echo -e "${YELLOW}Reloading services...${NC}"
systemctl reload php${PHP_VERSION}-fpm
systemctl reload nginx

echo ""
echo -e "${GREEN}✅ Setup completed successfully!${NC}"
echo ""
echo "📍 Backend API: http://localhost/api"
echo "📍 Health check: http://localhost/health"
echo ""
echo "To check status:"
echo "  sudo systemctl status php${PHP_VERSION}-fpm"
echo "  sudo systemctl status nginx"
echo ""
echo "To view logs:"
echo "  sudo tail -f /var/log/nginx/laravel-api-error.log"
echo "  sudo tail -f /var/log/php${PHP_VERSION}-fpm.log"
