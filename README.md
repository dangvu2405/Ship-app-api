# Ship-app Backend API (CETA Specification)

A standardized, multi-tenant backend API for the Ship-app Logistics Management System, built with Laravel 11.

## 🚀 Key Features

- **Standardized API Layer**: Centralized `CetaSpecController` for consistent CRUD and action-driven state transitions.
- **Multi-tenancy**: Native support for multiple companies (tenants) via `TenantContext` and global scopes.
- **RESTful Architecture**: Strict adherence to REST principles (e.g., `PATCH` for state transitions).
- **Granular Permissions**: RBAC matrix implemented via middleware and database-driven `user_permissions`.
- **Domain Logic Services**: Decoupled business logic for Trips, Finance, Fleet, and Reports.

## 🛠️ Technology Stack

- **Framework**: Laravel 11 (PHP 8.3+)
- **Authentication**: Laravel Sanctum (Token-based)
- **Database**: MySQL (Production), SQLite (Testing/Development)
- **Logistics Core**: Custom CETA (Clean Enterprise Transportation Architecture) specification.

## 📂 Project Structure

- `app/Http/Controllers/Api/CetaSpecController.php`: Generic controller handling resources dynamic CRUD.
- `app/Services/`: Domain services containing business logic.
    - `Trip/TripService.php`: Trip state transitions and assignments.
    - `Finance/FinanceService.php`: Pricing, debt, and cost approvals.
    - `Fleet/FleetService.php`: Resource availability and assignments.
    - `Report/ReportService.php`: Summary reports and dispatch data.
- `app/Tenancy/`: Tenant resolution and context management.
- `database/migrations/`: Structured migrations for schema hardening.

## ⚙️ Setup & Development

### Docker (Backend API)

Docker Compose is located within the `ship-app-api` directory.

```bash
cd ship-app-api
cp docker/env.docker.example .env
# Generate APP_KEY if needed
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

- **API URL**: `http://localhost:8080/api`
- **Database**: `localhost:3306` (user: root, pass: root, db: ship_db)

### Manual Setup

1. Install dependencies: `composer install`
2. Configure `.env`
3. Run migrations: `php artisan migrate`
4. Start server: `php artisan serve`

## 🧹 Optimization

Run the optimization script to clean up project size:

```bash
./scripts/optimize_project_size.sh
```

## 📝 Documentation

- **Database Schema**: [database.md](database.md)
- **API Specification**: [spec.md](spec.md)
- **Audit Logs**: [docs/PROJECT_SIZE_AUDIT.md](docs/PROJECT_SIZE_AUDIT.md)

## License

Private and Confidential. © 2026 Ship-app Team.
