<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dữ liệu vận hành dày cho **tháng 4**: 30 ngày × mỗi loại **20** bản ghi/ngày (trips, vehicle_expenses, overtime_requests).
 *
 * **Tài xế + xe:** trước khi sinh chuyến, seeder tạo thêm một đợt tài xế & xe “bulk tháng 4”
 * (cùng công ty/văn phòng/chức vụ với một tài xế mẫu đang active), số lượng = `APRIL_BULK_FLEET_SIZE`
 * (mặc định bằng `APRIL_BULK_ROWS_PER_DAY`, tối đa 100). Đặt `APRIL_BULK_FLEET_SIZE=0` để không tạo thêm, chỉ dùng fleet sẵn có.
 *
 * - Năm: `APRIL_BULK_YEAR` (mặc định 2026), tháng cố định = 4.
 * - Số dòng/ngày/bảng: `APRIL_BULK_ROWS_PER_DAY` (mặc định 20, tối đa 100).
 *
 * Chạy: `php artisan db:seed --class=Database\\Seeders\\AprilHeavyOperationsSeeder`
 */
final class AprilHeavyOperationsSeeder extends Seeder
{
    private const APRIL_MONTH = 4;

    private const DAYS_IN_APRIL = 30;

    private const DEFAULT_ROWS_PER_DAY = 20;

    private const INSERT_CHUNK = 100;

    public function run(): void
    {
        $year = max(2020, (int) env('APRIL_BULK_YEAR', 2026));
        $rowsPerDay = max(1, min(100, (int) env('APRIL_BULK_ROWS_PER_DAY', self::DEFAULT_ROWS_PER_DAY)));
        $fleetSize = env('APRIL_BULK_FLEET_SIZE');
        $fleetSize = $fleetSize === null || $fleetSize === ''
            ? $rowsPerDay
            : max(0, min(100, (int) $fleetSize));

        $customers = DB::table('customers')->orderBy('id')->pluck('id')->all();
        $users = DB::table('users')->where('status', 'active')->orderBy('id')->pluck('id')->all();

        $templateDriver = DB::table('drivers')->where('status', 'active')->orderBy('id')->first();
        $templateVehicle = DB::table('vehicles')->where('status', 'active')->orderBy('id')->first();

        if ($templateDriver === null || $templateVehicle === null || $customers === [] || $users === []) {
            $this->command?->error('AprilHeavyOperationsSeeder: thiếu ít nhất 1 driver active, 1 vehicle active, customers, users — chạy DatabaseSeeder trước.');

            return;
        }

        $now = now();
        $runId = $now->format('His');

        $fleetDrivers = 0;
        $fleetVehicles = 0;
        if ($fleetSize > 0) {
            [$fleetDrivers, $fleetVehicles] = $this->seedAprilFleet($templateDriver, $templateVehicle, $fleetSize, $runId, $now);
        }

        $drivers = DB::table('drivers')->where('status', 'active')->orderBy('id')->pluck('id')->all();
        $vehicles = DB::table('vehicles')->where('status', 'active')->orderBy('id')->pluck('id')->all();

        $tripRows = [];
        $expenseRows = [];
        $otRows = [];

        for ($day = 1; $day <= self::DAYS_IN_APRIL; $day++) {
            $workDate = sprintf('%04d-%02d-%02d', $year, self::APRIL_MONTH, $day);
            $dayStart = Carbon::create($year, self::APRIL_MONTH, $day, 6, 0, 0);

            for ($n = 1; $n <= $rowsPerDay; $n++) {
                $driverId = (int) $drivers[($day + $n) % count($drivers)];
                $vehicleId = (int) $vehicles[($day * 3 + $n) % count($vehicles)];
                $customerId = (int) $customers[($day + $n * 2) % count($customers)];
                $companyId = (int) DB::table('drivers')->where('id', $driverId)->value('company_id');

                $code = sprintf('APR-%04d-%02d-D%02d-R%02d-%s', $year, self::APRIL_MONTH, $day, $n, $runId);
                $start = $dayStart->copy()->addMinutes(($n - 1) * 25);
                $end = $start->copy()->addHours(4 + ($n % 3));

                $tripRows[] = [
                    'code' => $code,
                    'company_id' => $companyId,
                    'customer_id' => $customerId,
                    'driver_id' => $driverId,
                    'vehicle_id' => $vehicleId,
                    'start_point' => 'Kho '.$day.' — '.$n,
                    'end_point' => 'Điểm giao '.$day.' — '.$n,
                    'distance_km' => round(80 + ($day % 10) * 5 + $n * 2.5, 2),
                    'start_time' => $start,
                    'end_time' => $end,
                    'price' => round(2_500_000 + $n * 120_000 + $day * 50_000, 2),
                    'status' => $n % 7 === 0 ? 'pending' : ($n % 5 === 0 ? 'in_progress' : 'completed'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $expenseRows[] = [
                    'company_id' => $companyId,
                    'vehicle_id' => $vehicleId,
                    'driver_id' => $driverId,
                    'type' => ['fuel', 'maintenance', 'toll', 'parking', 'other'][($day + $n) % 5],
                    'amount' => round(150_000 + $n * 15_000 + $day * 2_000, 2),
                    'note' => "April bulk seed {$workDate} #{$n}",
                    'expense_date' => $workDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasTable('overtime_requests')) {
                    $requesterId = (int) $users[($day + $n) % count($users)];
                    $otRows[] = [
                        'driver_id' => $driverId,
                        'company_id' => $companyId,
                        'work_date' => $workDate,
                        'start_time' => '18:00:00',
                        'end_time' => '22:30:00',
                        'ot_hours' => round(2 + ($n % 4) * 0.5, 2),
                        'reason' => "OT tháng 4 bulk {$workDate} #{$n}",
                        'status' => ['pending', 'approved', 'rejected'][($day + $n) % 3],
                        'requested_by' => $requesterId,
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejection_reason' => null,
                        'payroll_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        $this->insertInChunks('trips', $tripRows);
        $this->insertInChunks('vehicle_expenses', $expenseRows);
        if ($otRows !== []) {
            $this->insertInChunks('overtime_requests', $otRows);
        }

        $perTable = self::DAYS_IN_APRIL * $rowsPerDay;
        $this->command?->info(sprintf(
            'AprilHeavyOperationsSeeder: tháng 04/%d — fleet +%d drivers, +%d vehicles; %d ngày × %d dòng/ngày → trips=%d, vehicle_expenses=%d%s.',
            $year,
            $fleetDrivers,
            $fleetVehicles,
            self::DAYS_IN_APRIL,
            $rowsPerDay,
            $perTable,
            $perTable,
            $otRows !== [] ? ', overtime_requests='.$perTable : ''
        ));
    }

    /**
     * Tạo thêm tài xế + xe gắn cùng company/office/department/position với bản mẫu.
     *
     * @return array{0: int, 1: int} [số driver đã insert, số vehicle đã insert]
     */
    private function seedAprilFleet(object $templateDriver, object $templateVehicle, int $fleetSize, string $runId, \DateTimeInterface $now): array
    {
        $driverRows = [];
        $vehicleRows = [];

        for ($i = 1; $i <= $fleetSize; $i++) {
            $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $code = 'APR'.$runId.'D'.$suffix;
            $plate = '51A-APR'.$runId.$suffix;
            $email = 'apr.bulk.'.$runId.'.'.$suffix.'@seed.invalid';
            $license = 'DL-APR-'.$runId.'-'.$suffix;

            $driverRows[] = [
                'code' => $code,
                'name' => 'Tài xế bulk T4 '.$suffix,
                'email' => $email,
                'phone' => sprintf('091%08d', (($i * 97) + (int) $runId) % 100_000_000),
                'dob' => '1990-04-15',
                'gender' => 'male',
                'address' => 'Seeded April bulk',
                'avatar_url' => null,
                'national_id_no' => null,
                'national_id_issue_date' => null,
                'national_id_issue_place' => null,
                'social_insurance_no' => null,
                'health_insurance_no' => null,
                'insurance_registered_at' => null,
                'office_id' => $templateDriver->office_id,
                'company_id' => $templateDriver->company_id,
                'department_id' => $templateDriver->department_id,
                'position_id' => $templateDriver->position_id,
                'license_no' => $license,
                'license_image_url' => null,
                'identity_image_url' => null,
                'driver_insurance_no' => null,
                'driver_insurance_expired_date' => null,
                'health_certificate_no' => null,
                'health_certificate_expired_date' => null,
                'license_class' => 'C',
                'expired_date' => '2028-12-31',
                'available_status' => 'available',
                'status' => 'active',
                'join_date' => '2024-01-01',
                'resign_date' => null,
                'bank_name' => 'Vietcombank',
                'bank_account_no' => str_pad((string) (1000000000 + $i), 10, '0', STR_PAD_LEFT),
                'bank_account_name' => 'Driver '.$suffix,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $vehicleRows[] = [
                'office_id' => $templateVehicle->office_id,
                'company_id' => $templateVehicle->company_id,
                'plate_number' => $plate,
                'type' => 'truck',
                'brand' => 'Hino',
                'model' => '500 Series',
                'year' => 2022,
                'capacity' => 15000,
                'status' => 'active',
                'image_front' => null,
                'image_back' => null,
                'image_side' => null,
                'image_other' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];
        }

        $this->insertInChunks('drivers', $driverRows);
        $this->insertInChunks('vehicles', $vehicleRows);

        return [$fleetSize, $fleetSize];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function insertInChunks(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
