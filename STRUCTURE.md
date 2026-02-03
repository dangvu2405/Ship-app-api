# Cấu trúc dự án Laravel

## Tổng quan cấu trúc

```
ship-app-api/
│
├── app/                          # Core application code
│   ├── Console/                  # Artisan commands
│   │   └── Commands/
│   ├── Events/                   # Event classes
│   ├── Exceptions/               # Exception handlers
│   ├── Http/                     # HTTP layer
│   │   ├── Controllers/          # Controllers
│   │   ├── Middleware/           # Custom middleware
│   │   ├── Requests/             # Form requests (validation)
│   │   └── Resources/            # API Resources
│   ├── Jobs/                     # Queue jobs
│   ├── Listeners/                # Event listeners
│   ├── Mail/                     # Mail classes
│   ├── Models/                   # Eloquent models
│   ├── Notifications/            # Notification classes
│   ├── Policies/                # Authorization policies
│   ├── Providers/                # Service providers
│   ├── Rules/                    # Custom validation rules
│   └── Services/                 # Business logic services
│
├── bootstrap/                    # Bootstrap files
│   ├── app.php                  # Application bootstrap
│   ├── cache/                   # Cache files
│   └── providers.php            # Service providers
│
├── config/                      # Configuration files
│   ├── app.php
│   ├── database.php
│   ├── auth.php
│   └── ...
│
├── database/                    # Database files
│   ├── factories/               # Model factories
│   ├── migrations/              # Database migrations
│   ├── seeders/                 # Database seeders
│   └── database.sqlite          # SQLite database (nếu dùng)
│
├── public/                      # Public entry point
│   ├── index.php               # Entry point
│   ├── .htaccess
│   └── assets/                 # Compiled assets
│
├── resources/                   # Views & assets
│   ├── css/
│   ├── js/
│   ├── views/                   # Blade templates
│   │   ├── layouts/
│   │   ├── components/
│   │   └── ...
│   └── lang/                    # Language files
│       └── en/
│
├── routes/                      # Route definitions
│   ├── web.php                 # Web routes
│   ├── api.php                 # API routes
│   └── console.php             # Console routes
│
├── storage/                     # Storage files
│   ├── app/                    # App storage
│   ├── framework/              # Framework files
│   └── logs/                   # Log files
│
├── tests/                       # Tests
│   ├── Feature/                # Feature tests
│   └── Unit/                   # Unit tests
│
├── vendor/                      # Composer dependencies
├── .env                        # Environment variables
├── .env.example               # Example env file
├── artisan                    # Artisan CLI
├── composer.json              # PHP dependencies
├── package.json               # NPM dependencies
└── vite.config.js             # Vite configuration
```

## Mô tả các thư mục chính

### app/
Chứa toàn bộ code ứng dụng của bạn.

- **Console/Commands/**: Các lệnh Artisan tùy chỉnh
- **Events/**: Event classes để xử lý các sự kiện trong ứng dụng
- **Exceptions/**: Custom exception handlers
- **Http/**: Layer xử lý HTTP requests
  - **Controllers/**: Logic điều khiển request/response
  - **Middleware/**: Middleware để xử lý requests
  - **Requests/**: Form request validation
  - **Resources/**: API resources để transform data
- **Jobs/**: Queue jobs cho background processing
- **Listeners/**: Event listeners
- **Mail/**: Mail classes
- **Models/**: Eloquent models
- **Notifications/**: Notification classes
- **Policies/**: Authorization policies
- **Providers/**: Service providers
- **Rules/**: Custom validation rules
- **Services/**: Business logic services (tùy chọn)

### routes/
Định nghĩa các routes của ứng dụng.

- **web.php**: Web routes (có session, CSRF protection)
- **api.php**: API routes (stateless, prefix `/api`)
- **console.php**: Artisan command routes

### database/
Quản lý database.

- **migrations/**: Schema definitions
- **seeders/**: Data seeding
- **factories/**: Model factories cho testing

### resources/
Views và assets.

- **views/**: Blade templates
- **css/**: CSS files
- **js/**: JavaScript files
- **lang/**: Language files

### config/
Tất cả các file cấu hình của ứng dụng.

### public/
Entry point công khai của ứng dụng. Tất cả requests đều đi qua `index.php`.

### storage/
Lưu trữ files, cache, logs.

### tests/
Test files cho ứng dụng.

## Cách sử dụng

### Tạo Controller
```bash
php artisan make:controller UserController
```

### Tạo Model
```bash
php artisan make:model Product
```

### Tạo Migration
```bash
php artisan make:migration create_products_table
```

### Tạo API Resource
```bash
php artisan make:resource UserResource
```

### Tạo Form Request
```bash
php artisan make:request StoreUserRequest
```

### Tạo Middleware
```bash
php artisan make:middleware CheckRole
```

### Tạo Service
```bash
php artisan make:service UserService
```

## Best Practices

1. **Controllers**: Chỉ nên chứa logic điều khiển, không nên chứa business logic
2. **Services**: Đặt business logic vào Services
3. **Models**: Chỉ định nghĩa relationships và accessors/mutators
4. **Requests**: Sử dụng Form Requests cho validation
5. **Resources**: Sử dụng API Resources để transform data cho API responses
