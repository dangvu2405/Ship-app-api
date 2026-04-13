<?php

namespace Database\Seeders;

use App\Models\Allowance;
use App\Models\Attendance;
use App\Models\AttendanceSummary;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Deduction;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeSalaryConfig;
use App\Models\ExportLog;
use App\Models\Invoice;
use App\Models\LoginLog;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\Permission;
use App\Models\Position;
use App\Models\ReportCache;
use App\Models\Role;
use App\Models\Trip;
use App\Models\TripBonusRule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleExpense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkDataSeeder extends Seeder
{
    /**
     * Seed 100 records for each table.
     */
    public function run(): void
    {
        $count = 100;
        $this->command->info('Starting bulk data seeding...');

        // 1. Companies (100)
        $this->command->info('Seeding Companies...');
        $companies = Company::factory()->count($count)->create();
        $this->command->info('✓ Companies seeded: ' . $companies->count());

        // 2. Offices (100)
        $this->command->info('Seeding Offices...');
        $offices = collect();
        foreach (range(1, $count) as $i) {
            $office = Office::factory()->create([
                'company_id' => $companies->random()->id,
            ]);
            $offices->push($office);
        }
        $this->command->info('✓ Offices seeded: ' . $offices->count());

        // 3. Departments (100)
        $this->command->info('Seeding Departments...');
        $departments = collect();
        foreach (range(1, $count) as $i) {
            $dept = Department::factory()->create([
                'office_id' => $offices->random()->id,
            ]);
            $departments->push($dept);
        }
        $this->command->info('✓ Departments seeded: ' . $departments->count());

        // 4. Positions (100)
        $this->command->info('Seeding Positions...');
        $positions = Position::factory()->count($count)->create();
        $this->command->info('✓ Positions seeded: ' . $positions->count());

        // 5. Employees (100)
        $this->command->info('Seeding Employees...');
        $employees = collect();
        for ($i = 0; $i < $count; $i++) {
            $employee = Employee::factory()->create([
                'office_id' => $offices->random()->id,
                'department_id' => $departments->random()->id,
                'position_id' => $positions->random()->id,
            ]);
            $employees->push($employee);
        }
        $this->command->info('✓ Employees seeded: ' . $employees->count());

        // 6. Drivers (100)
        $this->command->info('Seeding Drivers...');
        $driverEmployees = collect();
        for ($i = 0; $i < $count; $i++) {
            $driverEmployee = Employee::factory()->create([
                'type' => 'driver',
                'office_id' => $offices->random()->id,
                'department_id' => $departments->random()->id,
                'position_id' => $positions->random()->id,
                'status' => 'active',
            ]);
            $driverEmployees->push($driverEmployee);
            $employees->push($driverEmployee);
        }

        $drivers = collect();
        foreach ($driverEmployees->take($count) as $employee) {
            $driver = Driver::factory()->create(['employee_id' => $employee->id]);
            $drivers->push($driver);
        }
        $this->command->info('✓ Drivers seeded: ' . $drivers->count());

        // 7. Users (100)
        $this->command->info('Seeding Users...');
        $users = collect();
        foreach ($employees->take($count) as $employee) {
            $user = User::factory()->create(['employee_id' => $employee->id]);
            $users->push($user);
        }
        $this->command->info('✓ Users seeded: ' . $users->count());

        // 8. Roles (100)
        $this->command->info('Seeding Roles...');
        $existingRoles = Role::query()->count();
        for ($i = $existingRoles + 1; $i <= $count; $i++) {
            Role::query()->firstOrCreate(
                ['name' => 'role_' . $i],
                ['description' => 'Auto seeded role ' . $i]
            );
        }
        $roles = Role::query()->get();
        $this->command->info('✓ Roles seeded: ' . $roles->count());

        // 9. Permissions (100)
        $this->command->info('Seeding Permissions...');
        $existingPermissions = Permission::query()->count();
        for ($i = $existingPermissions + 1; $i <= $count; $i++) {
            Permission::query()->firstOrCreate(
                ['code' => 'resource_' . $i . '.manage'],
                ['name' => 'Permission ' . $i, 'description' => 'Auto seeded permission ' . $i]
            );
        }
        $permissions = Permission::query()->get();
        $this->command->info('✓ Permissions seeded: ' . $permissions->count());

        // 10. Role-Permission pivot (100)
        $this->command->info('Seeding Role-Permissions...');
        for ($i = 0; $i < $count; $i++) {
            $role = $roles->random();
            $permission = $permissions->random();
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $this->command->info('✓ Role-Permissions seeded: 100');

        // 11. User-Role pivot (100)
        $this->command->info('Seeding User-Roles...');
        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $role = $roles->random();
            $user->roles()->syncWithoutDetaching([$role->id]);
        }
        $this->command->info('✓ User-Roles seeded: 100');

        // 12. Allowances (100)
        $this->command->info('Seeding Allowances...');
        $allowances = Allowance::factory()->count($count)->create();
        $this->command->info('✓ Allowances seeded: ' . $allowances->count());

        // 13. Deductions (100)
        $this->command->info('Seeding Deductions...');
        $deductions = Deduction::factory()->count($count)->create();
        $this->command->info('✓ Deductions seeded: ' . $deductions->count());

        // 14. Employee Allowances (100)
        $this->command->info('Seeding Employee Allowances...');
        $employeeAllowances = collect();
        while ($employeeAllowances->count() < $count) {
            $employee = $employees->random();
            $allowance = $allowances->random();
            if (! $employee->employeeAllowances()->where('allowance_id', $allowance->id)->exists()) {
                $empAllowance = EmployeeAllowance::factory()->create([
                    'employee_id' => $employee->id,
                    'allowance_id' => $allowance->id,
                ]);
                $employeeAllowances->push($empAllowance);
            }
        }
        $this->command->info('✓ Employee Allowances seeded: ' . $employeeAllowances->count());

        // 15. Employee Deductions (100)
        $this->command->info('Seeding Employee Deductions...');
        $employeeDeductions = collect();
        while ($employeeDeductions->count() < $count) {
            $employee = $employees->random();
            $deduction = $deductions->random();
            if (! $employee->employeeDeductions()->where('deduction_id', $deduction->id)->exists()) {
                $empDeduction = EmployeeDeduction::factory()->create([
                    'employee_id' => $employee->id,
                    'deduction_id' => $deduction->id,
                ]);
                $employeeDeductions->push($empDeduction);
            }
        }
        $this->command->info('✓ Employee Deductions seeded: ' . $employeeDeductions->count());

        // 16. Vehicles (100)
        $this->command->info('Seeding Vehicles...');
        $vehicles = collect();
        for ($i = 0; $i < $count; $i++) {
            $vehicle = Vehicle::factory()->create([
                'office_id' => $offices->random()->id,
            ]);
            $vehicles->push($vehicle);
        }
        $this->command->info('✓ Vehicles seeded: ' . $vehicles->count());

        // 17. Vehicle Assignments (100)
        $this->command->info('Seeding Vehicle Assignments...');
        $vehicleAssignments = collect();
        for ($i = 0; $i < $count; $i++) {
            $vehicleAssignment = VehicleAssignment::factory()->create([
                'vehicle_id' => $vehicles->random()->id,
                'driver_id' => $driverEmployees->random()->id,
            ]);
            $vehicleAssignments->push($vehicleAssignment);
        }
        $this->command->info('✓ Vehicle Assignments seeded: ' . $vehicleAssignments->count());

        // 18. Vehicle Expenses (100)
        $this->command->info('Seeding Vehicle Expenses...');
        $vehicleExpenses = collect();
        for ($i = 0; $i < $count; $i++) {
            $vehicleExpense = VehicleExpense::factory()->create([
                'vehicle_id' => $vehicles->random()->id,
                'driver_id' => fake()->optional(0.8)->randomElement($driverEmployees->pluck('id')->toArray()),
            ]);
            $vehicleExpenses->push($vehicleExpense);
        }
        $this->command->info('✓ Vehicle Expenses seeded: ' . $vehicleExpenses->count());

        // 19. Customers (100)
        $this->command->info('Seeding Customers...');
        $customers = Customer::factory()->count($count)->create();
        $this->command->info('✓ Customers seeded: ' . $customers->count());

        // 20. Trips (100)
        $this->command->info('Seeding Trips...');
        $trips = collect();
        for ($i = 0; $i < $count; $i++) {
            $trip = Trip::factory()->create([
                'customer_id' => $customers->random()->id,
                'driver_id' => $driverEmployees->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ]);
            $trips->push($trip);
        }
        $this->command->info('✓ Trips seeded: ' . $trips->count());

        // 21. Invoices (100)
        $this->command->info('Seeding Invoices...');
        $invoices = collect();
        for ($i = 0; $i < $count; $i++) {
            $invoice = Invoice::factory()->create([
                'trip_id' => fake()->optional(0.7)->randomElement($trips->pluck('id')->toArray()),
                'customer_id' => $customers->random()->id,
            ]);
            $invoices->push($invoice);
        }
        $this->command->info('✓ Invoices seeded: ' . $invoices->count());

        // 22. Trip Bonus Rules (100)
        $this->command->info('Seeding Trip Bonus Rules...');
        $tripBonusRules = TripBonusRule::factory()->count($count)->create();
        $this->command->info('✓ Trip Bonus Rules seeded: ' . $tripBonusRules->count());

        // 23. Payroll periods (100)
        $this->command->info('Seeding Payroll Periods...');
        $payrollPeriods = collect();
        for ($i = 0; $i < $count; $i++) {
            $company = $companies->get($i % $companies->count());
            $startDate = now()->copy()->startOfMonth()->subMonths($i);

            $period = PayrollPeriod::query()->create([
                'company_id' => $company->id,
                'code' => sprintf('PP-%d-%04d', $company->id, $i + 1),
                'period_type' => 'monthly',
                'start_date' => $startDate->toDateString(),
                'end_date' => $startDate->copy()->endOfMonth()->toDateString(),
                'cutoff_date' => $startDate->copy()->endOfMonth()->toDateString(),
                'pay_date' => $startDate->copy()->endOfMonth()->addDays(5)->toDateString(),
                'status' => 'approved',
                'timezone' => config('app.timezone', 'UTC'),
                'created_by' => $users->random()->id,
                'updated_by' => $users->random()->id,
            ]);

            $payrollPeriods->push($period);
        }
        $this->command->info('✓ Payroll Periods seeded: ' . $payrollPeriods->count());

        // 24. Payrolls (100)
        $this->command->info('Seeding Payrolls...');
        $payrolls = collect();
        for ($i = 0; $i < $count; $i++) {
            $company = $companies->get($i % $companies->count());
            $period = $payrollPeriods->get($i % $payrollPeriods->count());

            $payroll = Payroll::query()->create([
                'company_id' => $company->id,
                'payroll_period_id' => $period->id,
                'month' => (($i % 12) + 1),
                'year' => 2020 + intdiv($i, 12),
                'status' => fake()->randomElement(['draft', 'approved', 'paid', 'locked']),
                'locked_at' => null,
                'calculated_at' => now(),
                'calculated_by' => $users->random()->id,
                'approved_at' => null,
                'approved_by' => null,
                'paid_at' => null,
                'notes' => 'Bulk seeded payroll',
                'created_by' => $users->random()->id,
                'updated_by' => $users->random()->id,
            ]);
            $payrolls->push($payroll);
        }
        $this->command->info('✓ Payrolls seeded: ' . $payrolls->count());

        // 25. Payroll Details (100)
        $this->command->info('Seeding Payroll Details...');
        $payrollDetails = collect();
        $employeePool = $employees->values();
        for ($i = 0; $i < $count; $i++) {
            $payrollDetail = PayrollDetail::query()->create([
                'payroll_id' => $payrolls->get($i % $payrolls->count())->id,
                'employee_id' => $employeePool->get($i % $employeePool->count())->id,
                'base_salary' => fake()->numberBetween(5000000, 20000000),
                'working_days' => fake()->numberBetween(18, 26),
                'overtime' => fake()->numberBetween(0, 3000000),
                'bonus' => fake()->numberBetween(0, 2000000),
                'allowance' => fake()->numberBetween(0, 1500000),
                'deduction' => fake()->numberBetween(0, 1000000),
                'fuel_cost' => fake()->numberBetween(0, 500000),
                'tax' => fake()->numberBetween(0, 1500000),
                'net_salary' => fake()->numberBetween(5000000, 25000000),
                'meta_json' => ['bulk_seeded' => true],
                'created_by' => $users->random()->id,
                'updated_by' => $users->random()->id,
            ]);
            $payrollDetails->push($payrollDetail);
        }
        $this->command->info('✓ Payroll Details seeded: ' . $payrollDetails->count());

        // 26. Payroll Adjustments (100)
        $this->command->info('Seeding Payroll Adjustments...');
        $payrollAdjustments = collect();
        for ($i = 0; $i < $count; $i++) {
            $payrollAdjustment = PayrollAdjustment::factory()->create([
                'payroll_detail_id' => $payrollDetails->random()->id,
            ]);
            $payrollAdjustments->push($payrollAdjustment);
        }
        $this->command->info('✓ Payroll Adjustments seeded: ' . $payrollAdjustments->count());

        // 27. Attendances (100)
        $this->command->info('Seeding Attendances...');
        $attendances = collect();
        $attendanceEmployees = $employees->values();
        for ($i = 0; $i < $count; $i++) {
            $employee = $attendanceEmployees->get($i % $attendanceEmployees->count());
            $attendance = Attendance::query()->create([
                'employee_id' => $employee->id,
                'date' => now()->copy()->subDays($i)->toDateString(),
                'check_in' => '08:00:00',
                'check_out' => '17:00:00',
                'work_hours' => 8,
                'overtime_hours' => 0,
                'status' => 'present',
            ]);
            $attendances->push($attendance);
        }
        $this->command->info('✓ Attendances seeded: ' . $attendances->count());

        // 28. Attendance summaries (100)
        $this->command->info('Seeding Attendance Summaries...');
        $attendanceSummaries = collect();
        for ($i = 0; $i < $count; $i++) {
            $attendanceSummary = AttendanceSummary::query()->create([
                'payroll_period_id' => $payrollPeriods->get($i % $payrollPeriods->count())->id,
                'employee_id' => $employeePool->get($i % $employeePool->count())->id,
                'working_days' => 22,
                'actual_days' => 21,
                'leave_paid_days' => 1,
                'leave_unpaid_days' => 0,
                'overtime_hours' => fake()->numberBetween(0, 20),
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $users->random()->id,
                'source' => 'computed',
                'meta_json' => ['bulk_seeded' => true],
                'created_by' => $users->random()->id,
                'updated_by' => $users->random()->id,
            ]);

            $attendanceSummaries->push($attendanceSummary);
        }
        $this->command->info('✓ Attendance Summaries seeded: ' . $attendanceSummaries->count());

        // 29. Employee salary configs (100)
        $this->command->info('Seeding Employee Salary Configs...');
        $employeeSalaryConfigs = collect();
        for ($i = 0; $i < $count; $i++) {
            $salaryConfig = EmployeeSalaryConfig::query()->create([
                'employee_id' => $employeePool->get($i % $employeePool->count())->id,
                'effective_from' => now()->copy()->startOfMonth()->subMonths($i)->toDateString(),
                'effective_to' => null,
                'base_salary' => fake()->numberBetween(5000000, 25000000),
                'currency' => 'VND',
                'pay_frequency' => 'monthly',
                'notes' => 'Bulk seeded salary config',
                'created_by' => $users->random()->id,
                'updated_by' => $users->random()->id,
            ]);
            $employeeSalaryConfigs->push($salaryConfig);
        }
        $this->command->info('✓ Employee Salary Configs seeded: ' . $employeeSalaryConfigs->count());

        // 30. Login Logs (100)
        $this->command->info('Seeding Login Logs...');
        $loginLogs = collect();
        for ($i = 0; $i < $count; $i++) {
            $loginLog = LoginLog::factory()->create([
                'user_id' => fake()->optional(0.9)->randomElement($users->pluck('id')->toArray()),
            ]);
            $loginLogs->push($loginLog);
        }
        $this->command->info('✓ Login Logs seeded: ' . $loginLogs->count());

        // 31. Audit Logs (100)
        $this->command->info('Seeding Audit Logs...');
        $auditLogs = collect();
        for ($i = 0; $i < $count; $i++) {
            $auditLog = AuditLog::factory()->create([
                'user_id' => fake()->optional(0.8)->randomElement($users->pluck('id')->toArray()),
            ]);
            $auditLogs->push($auditLog);
        }
        $this->command->info('✓ Audit Logs seeded: ' . $auditLogs->count());

        // 32. Export Logs (100)
        $this->command->info('Seeding Export Logs...');
        $exportLogs = collect();
        for ($i = 0; $i < $count; $i++) {
            $exportLog = ExportLog::factory()->create([
                'user_id' => fake()->optional(0.9)->randomElement($users->pluck('id')->toArray()),
            ]);
            $exportLogs->push($exportLog);
        }
        $this->command->info('✓ Export Logs seeded: ' . $exportLogs->count());

        // 33. Report Caches (100)
        $this->command->info('Seeding Report Caches...');
        $reportCaches = ReportCache::factory()->count($count)->create();
        $this->command->info('✓ Report Caches seeded: ' . $reportCaches->count());

        // 34. personal_access_tokens (100)
        $this->command->info('Seeding Personal Access Tokens...');
        DB::table('personal_access_tokens')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'tokenable_type' => User::class,
                'tokenable_id' => $users->get(($i - 1) % $users->count())->id,
                'name' => 'bulk-token-' . $i,
                'token' => hash('sha256', (string) Str::uuid()),
                'abilities' => json_encode(['*']),
                'last_used_at' => null,
                'expires_at' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        $personalAccessTokenIds = DB::table('personal_access_tokens')->pluck('id')->values();

        // 35. refresh_tokens (100)
        $this->command->info('Seeding Refresh Tokens...');
        DB::table('refresh_tokens')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'user_id' => $users->get(($i - 1) % $users->count())->id,
                'token' => hash('sha256', 'refresh-' . Str::uuid()),
                'access_token_id' => $personalAccessTokenIds->get(($i - 1) % $personalAccessTokenIds->count()),
                'expires_at' => now()->addDays(60),
                'is_revoked' => false,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'BulkSeeder',
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );

        // 36. password_reset_tokens (100)
        $this->command->info('Seeding Password Reset Tokens...');
        DB::table('password_reset_tokens')->insert(
            $users->take($count)->map(fn (User $user): array => [
                'email' => $user->email,
                'token' => hash('sha256', 'pwd-reset-' . Str::uuid()),
                'created_at' => now(),
            ])->all()
        );

        // 37. sessions (100)
        $this->command->info('Seeding Sessions...');
        DB::table('sessions')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'id' => (string) Str::uuid(),
                'user_id' => $users->get(($i - 1) % $users->count())->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'BulkSeeder Session',
                'payload' => base64_encode(serialize(['_token' => Str::random(40)])),
                'last_activity' => time(),
            ])->all()
        );

        // 38. cache (100)
        $this->command->info('Seeding Cache...');
        DB::table('cache')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'key' => 'bulk:cache:' . $i,
                'value' => serialize(['i' => $i, 'ok' => true]),
                'expiration' => time() + 3600,
            ])->all()
        );

        // 39. cache_locks (100)
        $this->command->info('Seeding Cache Locks...');
        DB::table('cache_locks')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'key' => 'bulk:lock:' . $i,
                'owner' => (string) Str::uuid(),
                'expiration' => time() + 600,
            ])->all()
        );

        // 40. jobs (100)
        $this->command->info('Seeding Jobs...');
        DB::table('jobs')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'BulkJob' . $i]),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time(),
            ])->all()
        );

        // 41. job_batches (100)
        $this->command->info('Seeding Job Batches...');
        DB::table('job_batches')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'id' => (string) Str::uuid(),
                'name' => 'bulk-batch-' . $i,
                'total_jobs' => 1,
                'pending_jobs' => 0,
                'failed_jobs' => 0,
                'failed_job_ids' => '[]',
                'options' => null,
                'cancelled_at' => null,
                'created_at' => time(),
                'finished_at' => time(),
            ])->all()
        );

        // 42. failed_jobs (100)
        $this->command->info('Seeding Failed Jobs...');
        DB::table('failed_jobs')->insert(
            collect(range(1, $count))->map(fn (int $i): array => [
                'uuid' => (string) Str::uuid(),
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'BulkFailedJob' . $i]),
                'exception' => 'Bulk seeded exception #' . $i,
                'failed_at' => now(),
            ])->all()
        );

        $this->command->info('✓ All data seeded successfully!');
    }
}
