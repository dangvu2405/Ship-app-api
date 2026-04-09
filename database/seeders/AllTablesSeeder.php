<?php

namespace Database\Seeders;

use App\Models\Allowance;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AllTablesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $company = Company::query()->first() ?? Company::factory()->create();
        $office = Office::query()->first() ?? Office::factory()->create(['company_id' => $company->id]);
        $position = Position::query()->first() ?? Position::factory()->create();

        $employee = Employee::query()->first() ?? Employee::factory()->create([
            'office_id' => $office->id,
            'position_id' => $position->id,
            'type' => 'office',
        ]);

        $driverEmployee = Employee::query()->where('type', 'driver')->first()
            ?? Employee::factory()->create([
                'office_id' => $office->id,
                'position_id' => $position->id,
                'type' => 'driver',
            ]);

        $user = User::query()->first() ?? User::factory()->create(['employee_id' => $employee->id]);
        $vehicle = Vehicle::query()->first() ?? Vehicle::factory()->create(['office_id' => $office->id]);
        $customer = Customer::query()->first() ?? Customer::factory()->create();
        $allowance = Allowance::query()->first() ?? Allowance::factory()->create();
        $deduction = Deduction::query()->first() ?? Deduction::factory()->create();

        // Ensure auth pivots have at least 1 record
        $roleId = DB::table('roles')->value('id');
        $permissionId = DB::table('permissions')->value('id');

        if ($roleId && $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        if ($roleId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $user->id, 'role_id' => $roleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        // Payroll period + attendance summary + salary config tables
        DB::table('payroll_periods')->updateOrInsert(
            [
                'company_id' => $company->id,
                'code' => sprintf('P%04d%02d', (int) $now->format('Y'), (int) $now->format('m')),
            ],
            [
                'period_type' => 'monthly',
                'start_date' => $now->copy()->startOfMonth()->toDateString(),
                'end_date' => $now->copy()->endOfMonth()->toDateString(),
                'cutoff_date' => $now->copy()->endOfMonth()->toDateString(),
                'pay_date' => $now->copy()->endOfMonth()->addDays(5)->toDateString(),
                'status' => 'approved',
                'timezone' => config('app.timezone', 'UTC'),
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $payrollPeriodId = DB::table('payroll_periods')
            ->where('company_id', $company->id)
            ->where('code', sprintf('P%04d%02d', (int) $now->format('Y'), (int) $now->format('m')))
            ->value('id');

        DB::table('employee_salary_configs')->updateOrInsert(
            [
                'employee_id' => $employee->id,
                'effective_from' => $now->copy()->startOfMonth()->toDateString(),
            ],
            [
                'effective_to' => null,
                'base_salary' => 8000000,
                'currency' => 'VND',
                'pay_frequency' => 'monthly',
                'notes' => 'Seeded salary config',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if ($payrollPeriodId) {
            DB::table('attendance_summaries')->updateOrInsert(
                [
                    'payroll_period_id' => $payrollPeriodId,
                    'employee_id' => $employee->id,
                ],
                [
                    'working_days' => 22,
                    'actual_days' => 21,
                    'leave_paid_days' => 1,
                    'leave_unpaid_days' => 0,
                    'overtime_hours' => 4,
                    'status' => 'approved',
                    'approved_at' => $now,
                    'approved_by' => $user->id,
                    'source' => 'computed',
                    'meta_json' => json_encode(['seeded' => true]),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        // Pivot-like and payroll-related business tables
        DB::table('employee_allowances')->updateOrInsert(
            ['employee_id' => $employee->id, 'allowance_id' => $allowance->id],
            ['amount' => 500000, 'updated_at' => $now, 'created_at' => $now]
        );

        DB::table('employee_deductions')->updateOrInsert(
            ['employee_id' => $employee->id, 'deduction_id' => $deduction->id],
            ['amount' => 300000, 'updated_at' => $now, 'created_at' => $now]
        );

        DB::table('attendances')->updateOrInsert(
            ['employee_id' => $employee->id, 'date' => $now->toDateString()],
            [
                'check_in' => '08:00:00',
                'check_out' => '17:00:00',
                'work_hours' => 8,
                'overtime_hours' => 0,
                'status' => 'present',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('vehicle_assignments')->updateOrInsert(
            ['vehicle_id' => $vehicle->id, 'driver_id' => $driverEmployee->id, 'from_date' => $now->toDateString()],
            ['to_date' => null, 'updated_at' => $now, 'created_at' => $now]
        );

        DB::table('vehicle_expenses')->insertOrIgnore([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverEmployee->id,
            'type' => 'fuel',
            'amount' => 250000,
            'note' => 'Seeded fuel expense',
            'expense_date' => $now->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tripCode = 'TRIP-' . $now->format('YmdHis');
        DB::table('trips')->insertOrIgnore([
            'code' => $tripCode,
            'customer_id' => $customer->id,
            'driver_id' => $driverEmployee->id,
            'vehicle_id' => $vehicle->id,
            'start_point' => 'Warehouse A',
            'end_point' => 'Destination B',
            'distance_km' => 120.5,
            'start_time' => $now,
            'end_time' => $now->copy()->addHours(3),
            'price' => 3500000,
            'status' => 'completed',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $tripId = DB::table('trips')->where('code', $tripCode)->value('id') ?? DB::table('trips')->value('id');
        if ($tripId) {
            DB::table('invoices')->insertOrIgnore([
                'code' => 'INV-' . $now->format('YmdHis'),
                'trip_id' => $tripId,
                'customer_id' => $customer->id,
                'subtotal' => 3500000,
                'vat_rate' => 10,
                'vat_amount' => 350000,
                'total_amount' => 3850000,
                'status' => 'issued',
                'issued_at' => $now,
                'paid_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('payrolls')->updateOrInsert(
            ['company_id' => $company->id, 'month' => (int) $now->format('m'), 'year' => (int) $now->format('Y')],
            [
                'payroll_period_id' => $payrollPeriodId,
                'status' => 'draft',
                'locked_at' => null,
                'calculated_at' => $now,
                'calculated_by' => $user->id,
                'approved_at' => null,
                'approved_by' => null,
                'paid_at' => null,
                'notes' => 'Seeded payroll',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $payrollId = DB::table('payrolls')
            ->where('company_id', $company->id)
            ->where('month', (int) $now->format('m'))
            ->where('year', (int) $now->format('Y'))
            ->value('id');

        if ($payrollId) {
            DB::table('payroll_details')->updateOrInsert(
                ['payroll_id' => $payrollId, 'employee_id' => $employee->id],
                [
                    'base_salary' => 8000000,
                    'working_days' => 26,
                    'overtime' => 0,
                    'bonus' => 500000,
                    'allowance' => 500000,
                    'deduction' => 300000,
                    'fuel_cost' => 0,
                    'tax' => 200000,
                    'net_salary' => 8500000,
                    'meta_json' => json_encode(['seeded' => true]),
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $payrollDetailId = DB::table('payroll_details')
                ->where('payroll_id', $payrollId)
                ->where('employee_id', $employee->id)
                ->value('id');

            if ($payrollDetailId) {
                DB::table('payroll_adjustments')->insertOrIgnore([
                    'payroll_detail_id' => $payrollDetailId,
                    'type' => 'addition',
                    'reason' => 'Seeded adjustment',
                    'amount' => 100000,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('login_logs')->insertOrIgnore([
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
            'device' => 'Seeder Device',
            'login_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('audit_logs')->insertOrIgnore([
            'user_id' => $user->id,
            'action' => 'create',
            'table_name' => 'users',
            'record_id' => $user->id,
            'old_data' => null,
            'new_data' => json_encode(['seeded' => true]),
            'ip_address' => '127.0.0.1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('export_logs')->insertOrIgnore([
            'user_id' => $user->id,
            'type' => 'payroll',
            'file_name' => 'payroll-seeded.xlsx',
            'file_path' => 'exports/payroll-seeded.xlsx',
            'record_count' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('report_caches')->insertOrIgnore([
            'type' => 'payroll_summary',
            'month' => (int) $now->format('m'),
            'year' => (int) $now->format('Y'),
            'data_json' => json_encode(['total_records' => 1]),
            'expires_at' => $now->copy()->addDay(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Auth/token + Laravel system tables
        $personalToken = DB::table('personal_access_tokens')->where('name', 'seed-token')->first();
        if (! $personalToken) {
            DB::table('personal_access_tokens')->insert([
                'tokenable_type' => User::class,
                'tokenable_id' => $user->id,
                'name' => 'seed-token',
                'token' => hash('sha256', Str::uuid()->toString()),
                'abilities' => json_encode(['*']),
                'last_used_at' => null,
                'expires_at' => $now->copy()->addDays(7),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $personalToken = DB::table('personal_access_tokens')->where('name', 'seed-token')->first();
        }

        DB::table('refresh_tokens')->updateOrInsert(
            ['token' => hash('sha256', 'seed-refresh-token')],
            [
                'user_id' => $user->id,
                'access_token_id' => $personalToken?->id,
                'expires_at' => $now->copy()->addDays(30),
                'is_revoked' => false,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder Agent',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => hash('sha256', 'seed-password-reset'), 'created_at' => $now]
        );

        DB::table('sessions')->updateOrInsert(
            ['id' => Str::uuid()->toString()],
            [
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder Session',
                'payload' => base64_encode(serialize(['_token' => Str::random(40)])),
                'last_activity' => time(),
            ]
        );

        DB::table('cache')->updateOrInsert(
            ['key' => 'seed:sample'],
            ['value' => serialize(['ok' => true]), 'expiration' => time() + 3600]
        );

        DB::table('cache_locks')->updateOrInsert(
            ['key' => 'seed:lock'],
            ['owner' => (string) Str::uuid(), 'expiration' => time() + 300]
        );

        DB::table('jobs')->insertOrIgnore([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'SeedJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        DB::table('job_batches')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'name' => 'seed-batch',
            'total_jobs' => 1,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => null,
            'cancelled_at' => null,
            'created_at' => time(),
            'finished_at' => time(),
        ]);

        DB::table('failed_jobs')->insertOrIgnore([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'FailedSeedJob']),
            'exception' => 'Seeded exception log',
            'failed_at' => $now,
        ]);
    }
}
