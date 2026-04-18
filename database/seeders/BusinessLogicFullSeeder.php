<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BusinessLogicFullSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 1) Ensure RBAC + reference masters
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(SpecReferenceDataSeeder::class);

        $admin = $this->ensureAdminUser();
        $drivers = Driver::query()->where('status', 'active')->orderBy('id')->get();

        if ($drivers->isEmpty()) {
            $this->command?->warn('BusinessLogicFullSeeder skipped: no active drivers found.');

            return;
        }

        $companyId = (int) $drivers->first()->company_id;
        $officeId = (int) $drivers->first()->office_id;

        // 2) Seed operational resources
        $vehicleIds = $this->seedVehicles($officeId, 5);
        $customerId = $this->seedCustomer($companyId);
        $this->seedTripBonusRules($companyId, $now->toDateString());
        $tripIds = $this->seedTrips($drivers->pluck('id')->all(), $vehicleIds, $customerId);
        $invoiceIds = $this->seedInvoices($customerId, $tripIds);
        $this->seedVehicleAssignments($drivers->pluck('id')->all(), $vehicleIds);
        $this->seedVehicleExpenses($drivers->pluck('id')->all(), $vehicleIds);

        // 3) Payroll core data
        $this->seedPayrollAndLines($companyId, $now->month, $now->year);
        $this->seedPayrollStatusHistory($admin->id);
        $this->seedNightShiftPolicy($companyId, $admin->id);
        $this->seedViolationDispute($drivers->first()->id, $admin->id);

        // 4) Histories + logs + support tables
        $this->seedTripStatusHistory($tripIds, $admin->id);
        $this->seedInvoiceStatusHistory($invoiceIds, $admin->id);
        $this->seedNotifications($admin->id);
        $this->seedChatMessages($admin->id);
        $this->seedLoginAndExportLogs($admin->id);
        $this->seedReportCache();
        $this->seedAuthTokens($admin->id, $admin->email);
        $this->seedQueueTables();

        $this->command?->info('BusinessLogicFullSeeder completed: filled empty tables with business-logical data.');
    }

    private function ensureAdminUser(): User
    {
        $admin = User::query()
            ->where('email', 'admin@example.com')
            ->orWhere('email', 'admin@abctransport.com')
            ->orWhere('username', 'admin')
            ->first();

        if (! $admin) {
            $driverId = Driver::query()->value('id');
            $admin = User::query()->create([
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password123'),
                'driver_id' => $driverId,
                'status' => 'active',
            ]);
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        if ($adminRoleId) {
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $admin->id, 'role_id' => $adminRoleId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        return $admin;
    }

    /** @return array<int,int> */
    private function seedVehicles(int $officeId, int $count): array
    {
        $ids = [];
        $companyId = (int) DB::table('offices')->where('id', $officeId)->value('company_id');
        for ($i = 1; $i <= $count; $i++) {
            $plate = sprintf('51A-%05d', 30000 + $i);
            DB::table('vehicles')->updateOrInsert(
                ['plate_number' => $plate],
                [
                    'company_id' => $companyId,
                    'office_id' => $officeId,
                    'type' => 'truck',
                    'brand' => 'Hino',
                    'model' => '500 Series',
                    'year' => 2021,
                    'capacity' => 12000,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
            $ids[] = (int) DB::table('vehicles')->where('plate_number', $plate)->value('id');
        }

        return $ids;
    }

    private function seedCustomer(int $companyId): int
    {
        DB::table('customers')->updateOrInsert(
            ['name' => 'Cong ty Khach Hang Logistics A', 'company_id' => $companyId],
            [
                'type' => 'company',
                'company_id' => $companyId,
                'tax_code' => '0312345678',
                'phone' => '02838889999',
                'email' => 'finance@khachhang-a.vn',
                'address' => 'KCN Song Than, Binh Duong',
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]
        );

        return (int) DB::table('customers')
            ->where('name', 'Cong ty Khach Hang Logistics A')
            ->where('company_id', $companyId)
            ->value('id');
    }

    private function seedTripBonusRules(int $companyId, string $effectiveFrom): void
    {
        $rules = [
            ['min_km' => 0, 'max_km' => 200, 'bonus_per_km' => 1200],
            ['min_km' => 201, 'max_km' => 500, 'bonus_per_km' => 1400],
            ['min_km' => 501, 'max_km' => null, 'bonus_per_km' => 1700],
        ];

        foreach ($rules as $rule) {
            DB::table('trip_bonus_rules')->updateOrInsert(
                [
                    'company_id' => $companyId,
                    'effective_from' => $effectiveFrom,
                    'min_km' => $rule['min_km'],
                    'max_km' => $rule['max_km'],
                ],
                [
                    'bonus_per_km' => $rule['bonus_per_km'],
                    'effective_to' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /** @param array<int,int> $driverIds @param array<int,int> $vehicleIds @return array<int,int> */
    private function seedTrips(array $driverIds, array $vehicleIds, int $customerId): array
    {
        $tripIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $code = 'TRP-LOGIC-'.now()->format('Ym').sprintf('-%03d', $i);
            $start = Carbon::now()->startOfMonth()->addDays($i)->setTime(7, 30);
            $end = (clone $start)->addHours(6);

            DB::table('trips')->updateOrInsert(
                ['code' => $code],
                [
                    'company_id' => (int) DB::table('drivers')->where('id', $driverIds[($i - 1) % count($driverIds)])->value('company_id'),
                    'customer_id' => $customerId,
                    'driver_id' => $driverIds[($i - 1) % count($driverIds)],
                    'vehicle_id' => $vehicleIds[($i - 1) % count($vehicleIds)],
                    'start_point' => 'Kho Tong TP.HCM',
                    'end_point' => 'Cang Cat Lai',
                    'distance_km' => 180 + ($i * 20),
                    'start_time' => $start,
                    'end_time' => $end,
                    'price' => 4500000 + ($i * 250000),
                    'status' => 'completed',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
            $tripIds[] = (int) DB::table('trips')->where('code', $code)->value('id');
        }

        return $tripIds;
    }

    /** @param array<int,int> $tripIds @return array<int,int> */
    private function seedInvoices(int $customerId, array $tripIds): array
    {
        $invoiceIds = [];
        foreach ($tripIds as $idx => $tripId) {
            $code = 'INV-LOGIC-'.now()->format('Ym').sprintf('-%03d', $idx + 1);
            $subtotal = 4500000 + (($idx + 1) * 250000);
            $vatRate = 8.0;
            $vatAmount = round($subtotal * ($vatRate / 100), 2);
            $total = $subtotal + $vatAmount;

            DB::table('invoices')->updateOrInsert(
                ['code' => $code],
                [
                    'trip_id' => $tripId,
                    'customer_id' => $customerId,
                    'subtotal' => $subtotal,
                    'vat_rate' => $vatRate,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $total,
                    'status' => 'paid',
                    'issued_at' => now()->startOfMonth()->addDays(10 + $idx),
                    'paid_at' => now()->startOfMonth()->addDays(20 + $idx),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
            $invoiceIds[] = (int) DB::table('invoices')->where('code', $code)->value('id');
        }

        return $invoiceIds;
    }

    /** @param array<int,int> $driverIds @param array<int,int> $vehicleIds */
    private function seedVehicleAssignments(array $driverIds, array $vehicleIds): void
    {
        for ($i = 0; $i < 5; $i++) {
            DB::table('vehicle_assignments')->updateOrInsert(
                [
                    'vehicle_id' => $vehicleIds[$i % count($vehicleIds)],
                    'driver_id' => $driverIds[$i % count($driverIds)],
                    'from_date' => now()->startOfMonth()->addDays($i)->toDateString(),
                ],
                [
                    'company_id' => (int) DB::table('drivers')->where('id', $driverIds[$i % count($driverIds)])->value('company_id'),
                    'to_date' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }
    }

    /** @param array<int,int> $driverIds @param array<int,int> $vehicleIds */
    private function seedVehicleExpenses(array $driverIds, array $vehicleIds): void
    {
        for ($i = 0; $i < 5; $i++) {
            DB::table('vehicle_expenses')->insertOrIgnore([
                'company_id' => (int) DB::table('drivers')->where('id', $driverIds[$i % count($driverIds)])->value('company_id'),
                'vehicle_id' => $vehicleIds[$i % count($vehicleIds)],
                'driver_id' => $driverIds[$i % count($driverIds)],
                'type' => 'fuel',
                'amount' => 350000 + ($i * 25000),
                'note' => 'Fuel expense seeded from business flow',
                'expense_date' => now()->startOfMonth()->addDays(8 + $i)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function seedPayrollAndLines(int $companyId, int $month, int $year): void
    {
        if (! Schema::hasTable('payrolls') || ! Schema::hasTable('payroll_lines')) {
            return;
        }

        if (Schema::hasTable('attendances')) {
            app(\App\Services\DriverPayrollCalculationService::class)->createOrRecalculateDraft($companyId, $month, $year);

            return;
        }

        // Fallback when attendance table is not available in current schema.
        DB::table('payrolls')->updateOrInsert(
            ['company_id' => $companyId, 'month' => $month, 'year' => $year],
            [
                'status' => 'draft',
                'locked_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'notes' => 'Seeded fallback payroll without attendance table',
                'snapshot_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]
        );

        $payrollId = (int) DB::table('payrolls')
            ->where('company_id', $companyId)
            ->where('month', $month)
            ->where('year', $year)
            ->value('id');

        $drivers = DB::table('drivers')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('id')
            ->limit(5)
            ->get();

        foreach ($drivers as $driver) {
            $base = 8500000;
            $tripBonus = 1200000;
            $allowance = 500000;
            $deduction = 900000;
            $tax = 0;
            $fuel = 300000;
            $net = $base + $tripBonus + $allowance - $deduction - $tax - $fuel;

            DB::table('payroll_lines')->updateOrInsert(
                ['payroll_id' => $payrollId, 'driver_id' => $driver->id],
                [
                    'company_id' => $companyId,
                    'base_salary' => $base,
                    'trip_bonus' => $tripBonus,
                    'overtime_pay' => 0,
                    'night_shift_allowance' => 0,
                    'public_holiday_pay' => 0,
                    'allowance' => $allowance,
                    'deduction' => $deduction,
                    'leave_unpaid_deduction' => 0,
                    'violation_deduction' => 0,
                    'fuel_excess_deduction' => $fuel,
                    'tax' => $tax,
                    'net_salary' => $net,
                    'working_days' => 22,
                    'leave_days_paid' => 0,
                    'leave_days_unpaid' => 0,
                    'overtime_hours' => 0,
                    'trips_completed_count' => 2,
                    'total_distance_km' => 420,
                    'meta_json' => json_encode(['seeded' => true, 'fallback' => true]),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]
            );
        }
    }

    private function seedPayrollStatusHistory(int $userId): void
    {
        $payroll = DB::table('payrolls')->orderByDesc('id')->first();
        if (! $payroll || ! Schema::hasTable('payroll_status_histories')) {
            return;
        }

        DB::table('payroll_status_histories')->updateOrInsert(
            ['payroll_id' => $payroll->id, 'to_status' => (string) $payroll->status],
            [
                'from_status' => null,
                'changed_by' => $userId,
                'changed_at' => now(),
                'note' => 'Seeded initial payroll state history',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedNightShiftPolicy(int $companyId, int $userId): void
    {
        if (! Schema::hasTable('night_shift_policies')) {
            return;
        }

        DB::table('night_shift_policies')->updateOrInsert(
            ['company_id' => $companyId, 'effective_from' => now()->startOfYear()->toDateString()],
            [
                'start_hour' => 22,
                'end_hour' => 6,
                'differential_pct' => 30,
                'effective_to' => null,
                'is_active' => true,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]
        );
    }

    private function seedViolationDispute(int $driverId, int $resolverId): void
    {
        if (! Schema::hasTable('violation_disputes')) {
            return;
        }

        $violationId = DB::table('violations')->where('driver_id', $driverId)->value('id');
        if (! $violationId) {
            return;
        }

        DB::table('violation_disputes')->updateOrInsert(
            ['violation_id' => $violationId],
            [
                'driver_id' => $driverId,
                'reason' => 'Driver submitted dispute evidence for review.',
                'evidence_urls' => json_encode(['https://example.com/evidence/gps-track-1']),
                'status' => 'under_review',
                'resolved_by' => null,
                'resolved_at' => null,
                'resolution_note' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]
        );
    }

    /** @param array<int,int> $tripIds */
    private function seedTripStatusHistory(array $tripIds, int $userId): void
    {
        if (! Schema::hasTable('trip_status_histories')) {
            return;
        }

        foreach ($tripIds as $tripId) {
            DB::table('trip_status_histories')->updateOrInsert(
                ['trip_id' => $tripId, 'to_status' => 'completed'],
                [
                    'from_status' => 'in_progress',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                    'note' => 'Seeded trip completion history',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /** @param array<int,int> $invoiceIds */
    private function seedInvoiceStatusHistory(array $invoiceIds, int $userId): void
    {
        if (! Schema::hasTable('invoice_status_histories')) {
            return;
        }

        foreach ($invoiceIds as $invoiceId) {
            DB::table('invoice_status_histories')->updateOrInsert(
                ['invoice_id' => $invoiceId, 'to_status' => 'paid'],
                [
                    'from_status' => 'issued',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                    'note' => 'Seeded invoice payment history',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedNotifications(int $userId): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        for ($i = 1; $i <= 3; $i++) {
            DB::table('notifications')->updateOrInsert(
                ['id' => (string) Str::uuid()],
                [
                    'type' => 'App\\Notifications\\SystemNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $userId,
                    'data' => json_encode(['title' => "Seed notification {$i}", 'body' => 'Business event seeded']),
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedChatMessages(int $userId): void
    {
        if (! Schema::hasTable('chat_messages')) {
            return;
        }

        $sessionId = 'seed-session-business';
        for ($i = 1; $i <= 3; $i++) {
            DB::table('chat_messages')->insertOrIgnore([
                'user_id' => $userId,
                'session_id' => $sessionId,
                'message' => "Seeded user question {$i}",
                'response' => "Seeded assistant response {$i}",
                'context' => json_encode(['module' => 'payroll', 'seeded' => true]),
                'model' => 'gemini',
                'status' => 'success',
                'error_message' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedLoginAndExportLogs(int $userId): void
    {
        DB::table('login_logs')->insertOrIgnore([
            'user_id' => $userId,
            'ip' => '127.0.0.1',
            'device' => 'Seeder Device',
            'login_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('export_logs')->insertOrIgnore([
            'user_id' => $userId,
            'type' => 'revenue_report',
            'file_name' => 'revenue-seeded.xlsx',
            'file_path' => 'exports/revenue-seeded.xlsx',
            'record_count' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedReportCache(): void
    {
        DB::table('report_caches')->insertOrIgnore([
            'type' => 'dashboard',
            'month' => (int) now()->format('m'),
            'year' => (int) now()->format('Y'),
            'data_json' => json_encode(['seeded' => true, 'kpi' => ['revenue' => 100000000]]),
            'expires_at' => now()->addHours(6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAuthTokens(int $userId, string $email): void
    {
        DB::table('refresh_tokens')->updateOrInsert(
            ['token' => hash('sha256', 'seed-refresh-business-1')],
            [
                'user_id' => $userId,
                'access_token_id' => DB::table('personal_access_tokens')->where('tokenable_id', $userId)->value('id'),
                'expires_at' => now()->addDays(30),
                'is_revoked' => false,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'BusinessLogicSeeder',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => hash('sha256', 'seed-password-reset-business'), 'created_at' => now()]
        );
    }

    private function seedQueueTables(): void
    {
        DB::table('cache_locks')->updateOrInsert(
            ['key' => 'seed:business:lock'],
            ['owner' => (string) Str::uuid(), 'expiration' => time() + 600]
        );

        DB::table('jobs')->insertOrIgnore([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'BusinessLogicSeedJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        DB::table('job_batches')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'name' => 'business-seed-batch',
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
            'payload' => json_encode(['displayName' => 'BusinessLogicFailedJob']),
            'exception' => 'Seeded failed job for monitoring demo',
            'failed_at' => now(),
        ]);
    }
}

