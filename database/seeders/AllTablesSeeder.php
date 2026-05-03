<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Extra rows for tables present in `database.md` (ship_db baseline).
 * Legacy paths (employees, payroll_periods, payroll_details, …) were removed.
 */
class AllTablesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $company = Company::query()->first();
        $user = User::query()->first();
        $driver = Driver::query()->first();
        $vehicle = Vehicle::query()->first();
        $customer = Customer::query()->first();

        if ($company === null || $user === null) {
            return;
        }

        if ($customer === null && Schema::hasTable('customers')) {
            DB::table('customers')->insertOrIgnore([
                'company_id' => $company->id,
                'type' => 'company',
                'name' => 'Seeded Customer',
                'tax_code' => 'SEED-CUST-'.Str::upper(Str::random(6)),
                'phone' => '0900000001',
                'email' => 'seed.customer@example.test',
                'address' => 'Seed address',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $customer = Customer::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->orderByDesc('id')
                ->first();
        }

        $roleId = Schema::hasTable('roles') ? DB::table('roles')->value('id') : null;
        $permissionId = Schema::hasTable('permissions') ? DB::table('permissions')->value('id') : null;

        if ($roleId && $permissionId && Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        if ($roleId && Schema::hasTable('user_roles')) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $user->id, 'role_id' => $roleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        if (Schema::hasTable('user_companies')) {
            DB::table('user_companies')->updateOrInsert(
                ['user_id' => $user->id, 'company_id' => $company->id],
                ['is_default' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        if ($driver !== null && $vehicle !== null && Schema::hasTable('vehicle_assignments')) {
            DB::table('vehicle_assignments')->updateOrInsert(
                [
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'from_date' => $now->toDateString(),
                ],
                [
                    'company_id' => (int) $driver->company_id,
                    'to_date' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if ($driver !== null && Schema::hasTable('attendances')) {
            DB::table('attendances')->updateOrInsert(
                ['driver_id' => $driver->id, 'date' => $now->toDateString()],
                [
                    'check_in' => '08:00:00',
                    'check_out' => '17:00:00',
                    'work_hours' => 8,
                    'overtime_hours' => 0,
                    'status' => 'present',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if ($driver !== null && $vehicle !== null && $customer !== null && Schema::hasTable('trips')) {
            $tripCode = 'TRIP-SEED-'.$now->format('YmdHis');
            DB::table('trips')->insertOrIgnore([
                'code' => $tripCode,
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'company_id' => (int) $driver->company_id,
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
        }

        $tripId = DB::table('trips')->orderByDesc('id')->value('id');
        if ($tripId && $customer !== null && Schema::hasTable('invoices')) {
            DB::table('invoices')->insertOrIgnore([
                'code' => 'INV-SEED-'.$now->format('YmdHis'),
                'company_id' => $company->id,
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

        $month = (int) $now->format('m');
        $year = (int) $now->format('Y');

        if (Schema::hasTable('payrolls')) {
            DB::table('payrolls')->updateOrInsert(
                ['company_id' => $company->id, 'month' => $month, 'year' => $year],
                [
                    'status' => 'draft',
                    'locked_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'paid_at' => null,
                    'paid_by' => null,
                    'notes' => 'Seeded payroll',
                    'snapshot_json' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $payrollId = Schema::hasTable('payrolls')
            ? DB::table('payrolls')
                ->where('company_id', $company->id)
                ->where('month', $month)
                ->where('year', $year)
                ->value('id')
            : null;

        if ($payrollId && $driver !== null && Schema::hasTable('payroll_lines')) {
            DB::table('payroll_lines')->updateOrInsert(
                ['payroll_id' => $payrollId, 'driver_id' => $driver->id],
                [
                    'company_id' => (int) $driver->company_id,
                    'base_salary' => 8000000,
                    'trip_bonus' => 500000,
                    'overtime_pay' => 0,
                    'night_shift_allowance' => 0,
                    'public_holiday_pay' => 0,
                    'allowance' => 500000,
                    'deduction' => 300000,
                    'leave_unpaid_deduction' => 0,
                    'violation_deduction' => 0,
                    'fuel_excess_deduction' => 0,
                    'tax' => 200000,
                    'net_salary' => 8500000,
                    'working_days' => 22,
                    'leave_days_paid' => 0,
                    'leave_days_unpaid' => 0,
                    'overtime_hours' => 0,
                    'trips_completed_count' => 0,
                    'total_distance_km' => 0,
                    'meta_json' => json_encode(['seeded' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if ($payrollId && $driver !== null && Schema::hasTable('payroll_adjustments')) {
            DB::table('payroll_adjustments')->insertOrIgnore([
                'company_id' => (int) $driver->company_id,
                'payroll_id' => $payrollId,
                'original_payroll_id' => null,
                'driver_id' => $driver->id,
                'type' => 'addition',
                'category' => 'manual',
                'amount' => 100000,
                'reason' => 'Seeded adjustment',
                'source_type' => null,
                'source_id' => null,
                'approved_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('login_logs')) {
            DB::table('login_logs')->insertOrIgnore([
                'user_id' => $user->id,
                'ip' => '127.0.0.1',
                'device' => 'Seeder Device',
                'login_at' => $now,
                'logout_at' => null,
                'status' => 'active',
                'action' => 'login',
                'performed_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insertOrIgnore([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'action' => 'create',
                'table_name' => 'users',
                'resource' => null,
                'record_id' => $user->id,
                'old_data' => null,
                'new_data' => json_encode(['seeded' => true]),
                'metadata' => null,
                'ip_address' => '127.0.0.1',
                'request_id' => null,
                'user_agent' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('export_logs')) {
            DB::table('export_logs')->insertOrIgnore([
                'user_id' => $user->id,
                'type' => 'payroll',
                'file_name' => 'payroll-seeded.xlsx',
                'file_path' => 'exports/payroll-seeded.xlsx',
                'record_count' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('report_caches')) {
            DB::table('report_caches')->insertOrIgnore([
                'type' => 'payroll_summary',
                'month' => $month,
                'year' => $year,
                'data_json' => json_encode(['total_records' => 1]),
                'expires_at' => $now->copy()->addDay(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('personal_access_tokens')) {
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

            if (Schema::hasTable('refresh_tokens') && $personalToken) {
                DB::table('refresh_tokens')->updateOrInsert(
                    ['token' => hash('sha256', 'seed-refresh-token')],
                    [
                        'user_id' => $user->id,
                        'access_token_id' => $personalToken->id,
                        'expires_at' => $now->copy()->addDays(30),
                        'is_revoked' => false,
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Seeder Agent',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        if (Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => hash('sha256', 'seed-password-reset'), 'created_at' => $now]
            );
        }

        if (Schema::hasTable('sessions')) {
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
        }

        if (Schema::hasTable('cache')) {
            DB::table('cache')->updateOrInsert(
                ['key' => 'seed:sample'],
                ['value' => serialize(['ok' => true]), 'expiration' => time() + 3600]
            );
        }

        if (Schema::hasTable('cache_locks')) {
            DB::table('cache_locks')->updateOrInsert(
                ['key' => 'seed:lock'],
                ['owner' => (string) Str::uuid(), 'expiration' => time() + 300]
            );
        }

        if (Schema::hasTable('jobs')) {
            DB::table('jobs')->insertOrIgnore([
                'queue' => 'default',
                'payload' => json_encode(['displayName' => 'SeedJob']),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => time(),
                'created_at' => time(),
            ]);
        }

        if (Schema::hasTable('job_batches')) {
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
        }

        if (Schema::hasTable('failed_jobs')) {
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
}
