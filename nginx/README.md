# Nginx Configuration cho Laravel API

## 📁 Cấu trúc files

```
ship-app-api/
├── docker/
│   └── nginx/
│       └── default.conf          # Nginx config cho Docker
├── nginx/
│   ├── nginx.conf               # Nginx config cho Production (Non-Docker)
│   ├── setup.sh                 # Script tự động setup
│   └── README.md                # File này
└── ...
```

## 🚀 Quick Start

### Option 1: Docker (Khuyên dùng cho Development)

```bash
cd /home/vumoi/company_ship/ship-app-api

# Start với Docker Compose
docker-compose up -d

# Truy cập: http://localhost:8080/api
```

### Option 2: Production với PHP-FPM (Non-Docker)

```bash
cd /home/vumoi/company_ship/ship-app-api

# Chạy script tự động setup
sudo ./nginx/setup.sh

# Hoặc setup thủ công:
# 1. Cài đặt PHP-FPM
sudo apt install php8.2-fpm

# 2. Copy cấu hình
sudo cp nginx/nginx.conf /etc/nginx/sites-available/laravel-api
sudo ln -s /etc/nginx/sites-available/laravel-api /etc/nginx/sites-enabled/

# 3. Fix permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
php artisan storage:link

# 4. Test và reload
sudo nginx -t
sudo systemctl restart php8.2-fpm
sudo systemctl reload nginx
```

## 📝 Cấu hình

### Docker Configuration
- File: `docker/nginx/default.conf`
- Port: 8080 (mapped từ container port 80)
- PHP-FPM: `app:9000` (Docker service)

### Production Configuration
- File: `nginx/nginx.conf`
- Port: 80
- PHP-FPM: Unix socket `/var/run/php/php8.2-fpm.sock`

## 🔧 Các tính năng

- ✅ PHP-FPM support
- ✅ Laravel routing
- ✅ Static file caching
- ✅ CORS headers
- ✅ Security headers
- ✅ Storage file serving
- ✅ Health check endpoint
- ✅ Deny access to sensitive files

## 🛠️ Troubleshooting

### Docker

```bash
# Kiểm tra logs
docker-compose logs nginx
docker-compose logs app

# Restart services
docker-compose restart nginx
docker-compose restart app
```

### Production

```bash
# Kiểm tra PHP-FPM
sudo systemctl status php8.2-fpm
sudo tail -f /var/log/php8.2-fpm.log

# Kiểm tra Nginx
sudo nginx -t
sudo tail -f /var/log/nginx/laravel-api-error.log

# Fix permissions
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 📚 Tài liệu tham khảo

- [Laravel Deployment](https://laravel.com/docs/deployment)
- [Nginx PHP-FPM](https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/)
- [Docker Compose](https://docs.docker.com/compose/)
