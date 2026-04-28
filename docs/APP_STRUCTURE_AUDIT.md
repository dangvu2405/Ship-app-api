# App Structure Audit

## Scope

This audit tracks the first restructuring pass for `app/` to standardize module boundaries without changing API routes/contracts.

## Current Module Mapping (Applied)

### HTTP Controllers

- `App\Http\Controllers\Api\CompanyController` -> `App\Http\Controllers\Api\Company\CompanyController`
- `App\Http\Controllers\Api\UserController` -> `App\Http\Controllers\Api\User\UserController`

### Services

- `App\Services\UserService` -> `App\Services\User\UserService`
- `App\Services\TripService` -> `App\Services\Trip\TripService`
- `App\Services\PayrollQueryService` -> `App\Services\Payroll\PayrollQueryService`
- `App\Services\PayrollWorkflowService` -> `App\Services\Payroll\PayrollWorkflowService`
- `App\Services\PayrollAdjustmentService` -> `App\Services\Payroll\PayrollAdjustmentService`

## Entry Points Updated

- `routes/api.php` now points to:
  - `App\Http\Controllers\Api\Company\CompanyController`
  - `App\Http\Controllers\Api\User\UserController`

## Dependency Imports Updated

- `TripController` imports `App\Services\Trip\TripService`
- `PayrollController` imports:
  - `App\Services\Payroll\PayrollQueryService`
  - `App\Services\Payroll\PayrollWorkflowService`
- `PayrollAdjustmentController` imports `App\Services\Payroll\PayrollAdjustmentService`
- `UserController` imports `App\Services\User\UserService`

## Remaining Work Candidates

- Continue moving remaining `Api` controllers into module sub-namespaces (`Driver`, `Trip`, `Payroll`, etc.).
- Group form requests by action consistency for shared/reused request objects.
- Evaluate extracting shared query/index concern from HTTP trait into explicit support layer when all controller namespaces are stable.

## Tree Structure Snapshot (`tree -L 5`)

### Root Statistics

- Total: `950 directories, 2706 files`
- Note: `vendor/` contains most third-party dependency files, so tree volume is high.

### Top-Level Structure

```text
ship-app-api/
├── app/
├── bootstrap/
├── config/
├── database/
├── docs/
├── docker/
├── nginx/
├── public/
├── resources/
├── routes/
├── scripts/
├── storage/
├── tests/
├── vendor/
├── artisan
├── composer.json
└── phpunit.xml
```

### `app/` Structure (Audit Focus)

```text
app/
├── Console/
├── DTOs/
├── Events/
├── Exceptions/
├── Exports/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── Company/
│   │   │   ├── User/
│   │   │   └── ...other controllers
│   │   └── Controller.php
│   ├── Middleware/
│   ├── Requests/
│   │   ├── Attendance/
│   │   ├── Auth/
│   │   ├── Company/
│   │   ├── Customer/
│   │   ├── Department/
│   │   ├── Driver/
│   │   ├── Invoice/
│   │   ├── Leave/
│   │   ├── Office/
│   │   ├── Overtime/
│   │   ├── Payroll/
│   │   ├── PayrollAdjustment/
│   │   ├── Position/
│   │   ├── PublicHoliday/
│   │   ├── Report/
│   │   ├── Role/
│   │   ├── Schedule/
│   │   ├── Trip/
│   │   ├── TripBonusRule/
│   │   ├── User/
│   │   ├── Vehicle/
│   │   ├── VehicleAssignment/
│   │   ├── VehicleExpense/
│   │   ├── Violation/
│   │   ├── Workforce/
│   │   └── WorkScheduleTemplate/
│   ├── Resources/
│   └── Traits/
├── Interfaces/
├── Jobs/
├── Listeners/
├── Mail/
├── Models/
│   └── Concerns/
├── Observers/
├── Providers/
├── Services/
│   ├── Payroll/
│   ├── Trip/
│   └── User/
├── Support/
├── Tenancy/
└── Traits/
```

### Database Structure

```text
database/
├── factories/
├── migrations/
├── seeders/
├── database.sqlite
└── testing.sqlite
```
