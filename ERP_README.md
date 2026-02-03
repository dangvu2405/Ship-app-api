# Enterprise ERP Mini System - Documentation

## Overview

This is a complete Enterprise ERP Mini System built with Laravel 12+ for HR, Fleet, Payroll, and Authentication management.

## Tech Stack

- **Laravel 12+**
- **MySQL 8**
- **PHP 8.2+**
- **Eloquent ORM**
- **Laravel Excel** (for CSV export)
- **Laravel Breeze** (for authentication)

## Installation

### 1. Install Dependencies

```bash
composer install
npm install
```

### 2. Configure Environment

Copy `.env.example` to `.env` and configure:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 3. Generate Application Key

```bash
php artisan key:generate
```

### 4. Run Migrations

```bash
php artisan migrate
```

### 5. Seed Database

```bash
php artisan db:seed
```

## Database Schema

### Organization Module
- `companies` - Company information
- `offices` - Office locations
- `departments` - Department tree structure
- `positions` - Job positions with base salary

### HR Module
- `employees` - Employee master data (office/driver)
- `drivers` - Driver-specific information
- `attendances` - Attendance records
- `allowances` - Allowance types
- `employee_allowances` - Employee allowance assignments
- `deductions` - Deduction types
- `employee_deductions` - Employee deduction assignments

### Fleet Module
- `vehicles` - Vehicle information
- `vehicle_assignments` - Vehicle-driver assignments
- `vehicle_expenses` - Vehicle expenses (fuel, maintenance, etc.)
- `trip_bonus_rules` - Trip bonus calculation rules

### Operations Module
- `customers` - Customer information
- `trips` - Trip records
- `invoices` - Invoice records

### Payroll Module
- `payrolls` - Payroll periods
- `payroll_details` - Employee payroll snapshots
- `payroll_adjustments` - Payroll adjustments

### System Module
- `users` - System users
- `roles` - User roles
- `permissions` - System permissions
- `user_roles` - User-role assignments
- `role_permissions` - Role-permission assignments
- `login_logs` - Login history
- `audit_logs` - Audit trail

### Reporting Module
- `report_caches` - Cached reports
- `export_logs` - Export history

## Usage

### Payroll Service

Generate payroll for a company:

```php
use App\Services\PayrollService;

$payrollService = new PayrollService();
$payroll = $payrollService->generatePayroll($companyId, $month, $year);
```

Approve payroll:

```php
$payrollService->approvePayroll($payrollId);
```

Lock payroll:

```php
$payrollService->lockPayroll($payrollId);
```

### Payroll Calculation Logic

**For Office Staff:**
- Base Salary = (position.base_salary × working_days) / 22
- Bonus = 0
- Fuel Cost = 0

**For Drivers:**
- Base Salary = position.base_salary (full month)
- Bonus = total_km × bonus_rate (based on trip_bonus_rules)
- Fuel Cost = sum of vehicle_expenses (type = 'fuel')

**Common Calculations:**
- Allowance = sum of employee_allowances
- Deduction = sum of employee_deductions
- Tax = taxable_income × 10%
- Net Salary = base + overtime + bonus + allowance - deduction - fuel_cost - tax

### Export Payroll to Excel

```php
use App\Exports\PayrollExport;
use Maatwebsite\Excel\Facades\Excel;

$payroll = Payroll::find($payrollId);
return Excel::download(new PayrollExport($payroll), 'payroll.xlsx');
```

### Role & Permission Middleware

**Register in `bootstrap/app.php`:**

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'permission' => \App\Http\Middleware\PermissionMiddleware::class,
    ]);
})
```

**Usage in Routes:**

```php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Admin routes
});

Route::middleware(['auth:sanctum', 'permission:payroll.approve'])->group(function () {
    // Payroll approval routes
});
```

## API Endpoints Structure

### Authentication
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/user` - Get current user

### Employees
- `GET /api/employees` - List employees
- `POST /api/employees` - Create employee
- `GET /api/employees/{id}` - Get employee
- `PUT /api/employees/{id}` - Update employee
- `DELETE /api/employees/{id}` - Delete employee

### Payroll
- `GET /api/payrolls` - List payrolls
- `POST /api/payrolls` - Generate payroll
- `GET /api/payrolls/{id}` - Get payroll
- `POST /api/payrolls/{id}/approve` - Approve payroll
- `POST /api/payrolls/{id}/lock` - Lock payroll
- `GET /api/payrolls/{id}/export` - Export payroll

### Trips
- `GET /api/trips` - List trips
- `POST /api/trips` - Create trip
- `GET /api/trips/{id}` - Get trip
- `PUT /api/trips/{id}` - Update trip

## Default Seeder Data

After running `php artisan db:seed`, you'll have:

- **Company**: ABC Transport Company
- **Office**: Head Office
- **Departments**: HR, Fleet Management
- **Positions**: Manager, Driver, Staff
- **Employees**: 
  - John Manager (office)
  - Mike Driver (driver)
  - Jane Staff (office)
- **Users**:
  - admin / password
  - driver1 / password
- **Roles**: admin, manager, driver
- **Permissions**: Various payroll, employee, trip permissions
- **Allowances**: Transport, Meal
- **Deductions**: Social Insurance, Health Insurance
- **Vehicles**: Sample vehicle
- **Trip Bonus Rules**: 3 tiers based on km

## Features

✅ Complete database schema with relationships
✅ Soft deletes on all tables
✅ Foreign keys with proper cascade/nullOnDelete
✅ Indexes on foreign keys and unique fields
✅ Payroll calculation service
✅ Role & Permission system
✅ Audit logging
✅ Excel export functionality
✅ Seeder with sample data

## Development

### Running Tests

```bash
php artisan test
```

### Code Style

```bash
./vendor/bin/pint
```

## Notes

- All tables use `softDeletes()` for data retention
- All timestamps (`created_at`, `updated_at`) are included
- Foreign keys use appropriate cascade/restrict/nullOnDelete
- Payroll calculations are snapshot-based (stored in payroll_details)
- Tax calculation is simplified (10% flat rate) - customize as needed

## License

MIT
