<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Position;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AllTablesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $company = Company::query()->first() ?? Company::factory()->create();
        $office = Office::query()->first() ?? Office::factory()->create(['company_id' => $company->id]);
        $position = Position::query()->first() ?? Position::factory()->create();

        $driver = Driver::query()->where('status', 'active')->first()
            ?? Driver::factory()->create([
                'office_id' => $office->id,
                'position_id' => $position->id,
                'status' => 'active',
            ]);

        $user = User::query()->first() ?? User::factory()->create(['driver_id' => $driver->id]);
        $vehicle = Vehicle::query()->first() ?? Vehicle::factory()->create(['office_id' => $office->id]);
        $customer = Customer::query()->first() ?? Customer::factory()->create();

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

        DB::table('vehicle_assignments')->updateOrInsert(
            ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'from_date' => $now->toDateString()],
            [
                'company_id' => $company->id,
                'to_date' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        DB::table('vehicle_expenses')->insertOrIgnore([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
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
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
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

        if (Schema::hasTable('payrolls') && Schema::hasTable('payroll_lines')) {
            DB::table('payrolls')->updateOrInsert(
                ['company_id' => $company->id, 'month' => (int) $now->format('m'), 'year' => (int) $now->format('Y')],
                [
                    'status' => 'draft',
                    'locked_at' => null,
                    'approved_at' => null,
                    'approved_by' => null,
                    'notes' => 'Seeded payroll',
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
                DB::table('payroll_lines')->updateOrInsert(
                    ['payroll_id' => $payrollId, 'driver_id' => $driver->id],
                    [
                        'company_id' => $company->id,
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
                        'trips_completed_count' => 1,
                        'total_distance_km' => 120.5,
                        'meta_json' => json_encode(['seeded' => true]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
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
