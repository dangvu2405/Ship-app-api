<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PhaseOneMinimalDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $drivers = DB::table('drivers')->where('status', 'active')->orderBy('id')->limit(5)->get();
        $users = DB::table('users')->orderBy('id')->limit(2)->get();
        $vehicles = DB::table('vehicles')->where('status', 'active')->orderBy('id')->limit(5)->get();

        if ($drivers->count() < 1 || $users->count() < 1) {
            $this->command?->warn('PhaseOneMinimalDataSeeder skipped: missing drivers/users seed prerequisites.');

            return;
        }

        if (Schema::hasTable('public_holidays')) {
            $this->seedPublicHolidays($now);
        }

        if (Schema::hasTable('driver_work_schedules')) {
            $this->seedDriverWorkSchedules($drivers->pluck('id')->all(), $vehicles->pluck('id')->all(), (int) $users[0]->id, $now);
        }

        if (Schema::hasTable('overtime_requests')) {
            $this->seedOvertimeRequests($drivers->pluck('id')->all(), (int) $drivers[0]->company_id, (int) $users[0]->id, $users->count() > 1 ? (int) $users[1]->id : (int) $users[0]->id, $now);
        }

        if (Schema::hasTable('leave_types') && Schema::hasTable('leave_requests') && Schema::hasTable('leave_balances')) {
            $this->seedLeaveData($drivers->pluck('id')->all(), (int) $users[0]->id, $users->count() > 1 ? (int) $users[1]->id : (int) $users[0]->id, $now);
        }

        if (Schema::hasTable('violations')) {
            $this->seedViolations($drivers->pluck('id')->all(), (int) $drivers[0]->company_id, (int) $users[0]->id, $users->count() > 1 ? (int) $users[1]->id : (int) $users[0]->id, $now);
        }

        $this->command?->info('PhaseOneMinimalDataSeeder completed: seeded at least 5 demo rows per Phase 1 table.');
    }

    private function seedPublicHolidays(\DateTimeInterface $now): void
    {
        $year = (int) now()->year;
        $rows = [
            ['date' => "{$year}-01-01", 'name' => 'New Year'],
            ['date' => "{$year}-04-30", 'name' => 'Reunification Day'],
            ['date' => "{$year}-05-01", 'name' => 'Labor Day'],
            ['date' => "{$year}-09-02", 'name' => 'National Day'],
            ['date' => "{$year}-09-03", 'name' => 'National Day (observed)'],
        ];

        foreach ($rows as $row) {
            DB::table('public_holidays')->updateOrInsert(
                ['country_code' => 'VN', 'date' => $row['date']],
                [
                    'year' => $year,
                    'name' => $row['name'],
                    'holiday_type' => 'national',
                    'is_compensatory' => false,
                    'compensatory_for' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * @param array<int,int> $driverIds
     * @param array<int,int> $vehicleIds
     */
    private function seedDriverWorkSchedules(array $driverIds, array $vehicleIds, int $userId, \DateTimeInterface $now): void
    {
        $baseDate = now()->startOfMonth()->addDays(2);

        for ($i = 0; $i < 5; $i++) {
            $driverId = $driverIds[$i % count($driverIds)];
            $vehicleId = count($vehicleIds) > 0 ? $vehicleIds[$i % count($vehicleIds)] : null;
            $workDate = $baseDate->copy()->addDays($i)->toDateString();

            DB::table('driver_work_schedules')->updateOrInsert(
                ['driver_id' => $driverId, 'work_date' => $workDate, 'shift_code' => 'day'],
                [
                    'company_id' => (int) DB::table('drivers')->where('id', $driverId)->value('company_id'),
                    'office_id' => (int) DB::table('drivers')->where('id', $driverId)->value('office_id'),
                    'start_time' => '07:00:00',
                    'end_time' => '17:00:00',
                    'vehicle_id' => $vehicleId,
                    'status' => 'approved',
                    'notes' => 'Seeded phase-1 schedule',
                    'submitted_by' => $userId,
                    'submitted_at' => $now,
                    'approved_by' => $userId,
                    'approved_at' => $now,
                    'locked_by' => null,
                    'locked_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * @param array<int,int> $driverIds
     */
    private function seedOvertimeRequests(array $driverIds, int $companyId, int $requesterId, int $approverId, \DateTimeInterface $now): void
    {
        $baseDate = now()->startOfMonth()->addDays(6);

        for ($i = 0; $i < 5; $i++) {
            $driverId = $driverIds[$i % count($driverIds)];
            $workDate = $baseDate->copy()->addDays($i)->toDateString();

            DB::table('overtime_requests')->updateOrInsert(
                ['driver_id' => $driverId, 'work_date' => $workDate, 'start_time' => '18:00:00'],
                [
                    'company_id' => $companyId,
                    'end_time' => '20:00:00',
                    'ot_hours' => 2.0,
                    'reason' => 'Seeded OT request',
                    'status' => 'approved',
                    'requested_by' => $requesterId,
                    'approved_by' => $approverId,
                    'approved_at' => $now,
                    'rejection_reason' => null,
                    'payroll_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }

    /**
     * @param array<int,int> $driverIds
     */
    private function seedLeaveData(array $driverIds, int $creatorId, int $approverId, \DateTimeInterface $now): void
    {
        $types = [
            ['code' => 'ANNUAL', 'name' => 'Nghi phep nam', 'is_paid' => true, 'annual_quota_days' => 12],
            ['code' => 'SICK', 'name' => 'Nghi om', 'is_paid' => false, 'annual_quota_days' => 0],
            ['code' => 'MATERNITY', 'name' => 'Nghi thai san', 'is_paid' => false, 'annual_quota_days' => 0],
            ['code' => 'UNPAID', 'name' => 'Nghi khong luong', 'is_paid' => false, 'annual_quota_days' => 0],
            ['code' => 'SPECIAL', 'name' => 'Nghi dac biet', 'is_paid' => true, 'annual_quota_days' => 3],
        ];

        foreach ($types as $type) {
            DB::table('leave_types')->updateOrInsert(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'is_paid' => $type['is_paid'],
                    'annual_quota_days' => $type['annual_quota_days'],
                    'allow_carry_forward' => true,
                    'requires_attachment' => false,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $annualTypeId = (int) DB::table('leave_types')->where('code', 'ANNUAL')->value('id');
        $unpaidTypeId = (int) DB::table('leave_types')->where('code', 'UNPAID')->value('id');
        $year = (int) now()->year;
        $baseDate = now()->startOfMonth()->addDays(10);

        for ($i = 0; $i < 5; $i++) {
            $driverId = $driverIds[$i % count($driverIds)];
            $typeId = $i % 2 === 0 ? $annualTypeId : $unpaidTypeId;
            $fromDate = $baseDate->copy()->addDays($i)->toDateString();
            $toDate = $baseDate->copy()->addDays($i)->toDateString();

            DB::table('leave_requests')->updateOrInsert(
                ['driver_id' => $driverId, 'leave_type_id' => $typeId, 'from_date' => $fromDate, 'to_date' => $toDate],
                [
                    'total_days' => 1.0,
                    'reason' => 'Seeded leave request',
                    'status' => 'approved',
                    'approved_by' => $approverId,
                    'approved_at' => $now,
                    'rejection_reason' => null,
                    'attachment_urls' => json_encode([]),
                    'created_by' => $creatorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );

            DB::table('leave_balances')->updateOrInsert(
                ['driver_id' => $driverId, 'leave_type_id' => $annualTypeId, 'year' => $year],
                [
                    'entitled_days' => 12.0,
                    'used_days' => 2.0,
                    'carried_forward_days' => 0.0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * @param array<int,int> $driverIds
     */
    private function seedViolations(array $driverIds, int $companyId, int $reporterId, int $confirmerId, \DateTimeInterface $now): void
    {
        $baseDate = now()->startOfMonth()->addDays(14);
        $types = ['speeding', 'route_deviation', 'fuel_misuse', 'behavior', 'other'];

        for ($i = 0; $i < 5; $i++) {
            $driverId = $driverIds[$i % count($driverIds)];
            $occurred = $baseDate->copy()->addDays($i)->setTime(10, 0)->toDateTimeString();
            $type = $types[$i];

            DB::table('violations')->updateOrInsert(
                ['driver_id' => $driverId, 'type' => $type, 'occurred_at' => $occurred],
                [
                    'company_id' => $companyId,
                    'trip_id' => null,
                    'reported_by' => $reporterId,
                    'description' => 'Seeded violation: '.$type,
                    'penalty_amount' => 200000 + ($i * 50000),
                    'status' => 'confirmed',
                    'confirmed_by' => $confirmerId,
                    'confirmed_at' => $now,
                    'waived_by' => null,
                    'waived_at' => null,
                    'waive_reason' => null,
                    'evidence_urls' => json_encode([]),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }
}

