<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Position;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleExpense;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seed 3 công ty demo + dữ liệu phong phú: nhiều VP, tài xế, xe, KH, chuyến, phân công, chi phí.
 * Idempotent: firstOrCreate theo mã cố định (company / office / driver / plate / trip code / …).
 */
final class ThreeCompaniesSeeder extends Seeder
{
    private int $phoneCounter = 310000000;

    public function run(): void
    {
        $managerPosition = Position::firstOrCreate(
            ['code' => 'SEED-MGR'],
            ['name' => 'Quản lý (seed 3 công ty)', 'base_salary' => 15000000, 'level' => 5]
        );
        $driverPosition = Position::firstOrCreate(
            ['code' => 'SEED-DRV'],
            ['name' => 'Tài xế (seed 3 công ty)', 'base_salary' => 8000000, 'level' => 2]
        );

        $definitions = [
            [
                'company' => [
                    'code' => 'DEMO-C1',
                    'name' => 'Vận tải Miền Bắc Demo',
                    'tax_code' => '0100000001',
                    'address' => 'Hà Nội',
                    'phone' => '02400000001',
                    'email' => 'contact@demo-mienbac.vn',
                ],
                'office' => ['code' => 'OFF-D1', 'name' => 'VP Hà Nội'],
                'branch' => ['code' => 'OFF-D1B', 'name' => 'Chi nhánh Hải Phòng'],
                'plate_prefix' => '29D',
                'routes' => [['Hà Nội', 'Hải Phòng'], ['Hà Nội', 'Lạng Sơn'], ['Vĩnh Phúc', 'Quảng Ninh']],
            ],
            [
                'company' => [
                    'code' => 'DEMO-C2',
                    'name' => 'Vận tải Miền Trung Demo',
                    'tax_code' => '0100000002',
                    'address' => 'Đà Nẵng',
                    'phone' => '02360000002',
                    'email' => 'contact@demo-mientrung.vn',
                ],
                'office' => ['code' => 'OFF-D2', 'name' => 'VP Đà Nẵng'],
                'branch' => ['code' => 'OFF-D2B', 'name' => 'Chi nhánh Huế'],
                'plate_prefix' => '43D',
                'routes' => [['Đà Nẵng', 'Huế'], ['Đà Nẵng', 'Quy Nhơn'], ['Quảng Ngãi', 'Nha Trang']],
            ],
            [
                'company' => [
                    'code' => 'DEMO-C3',
                    'name' => 'Vận tải Miền Nam Demo',
                    'tax_code' => '0100000003',
                    'address' => 'TP.HCM',
                    'phone' => '02800000003',
                    'email' => 'contact@demo-miennam.vn',
                ],
                'office' => ['code' => 'OFF-D3', 'name' => 'VP TP.HCM'],
                'branch' => ['code' => 'OFF-D3B', 'name' => 'Chi nhánh Bình Dương'],
                'plate_prefix' => '51D',
                'routes' => [['TP.HCM', 'Bình Dương'], ['TP.HCM', 'Đồng Nai'], ['Long An', 'Cần Thơ']],
            ],
        ];

        foreach ($definitions as $index => $def) {
            $cc = $def['company']['code'];
            $company = Company::firstOrCreate(
                ['code' => $cc],
                array_merge($def['company'], ['status' => 'active'])
            );

            $mainOffice = Office::firstOrCreate(
                ['company_id' => $company->id, 'code' => $def['office']['code']],
                [
                    'name' => $def['office']['name'],
                    'address' => $def['company']['address'],
                    'manager_id' => null,
                ]
            );

            $branchOffice = Office::firstOrCreate(
                ['company_id' => $company->id, 'code' => $def['branch']['code']],
                [
                    'name' => $def['branch']['name'],
                    'address' => $def['branch']['name'],
                    'manager_id' => null,
                ]
            );

            $hrMain = Department::firstOrCreate(
                ['office_id' => $mainOffice->id, 'code' => 'HR-'.$cc],
                ['name' => 'Nhân sự']
            );
            $fleetMain = Department::firstOrCreate(
                ['office_id' => $mainOffice->id, 'code' => 'FLEET-'.$cc],
                ['name' => 'Đội xe']
            );
            $logistics = Department::firstOrCreate(
                ['office_id' => $mainOffice->id, 'code' => 'LOG-'.$cc],
                ['name' => 'Kho & giao nhận']
            );
            $fleetBranch = Department::firstOrCreate(
                ['office_id' => $branchOffice->id, 'code' => 'FLEETB-'.$cc],
                ['name' => 'Đội xe chi nhánh']
            );

            $managerDriver = Driver::firstOrCreate(
                ['code' => 'DRV-'.$cc.'-MGR'],
                $this->driverPayload(
                    'Quản lý '.$def['company']['name'],
                    'mgr.'.strtolower($cc).'.main@demo.vn',
                    $mainOffice->id,
                    $hrMain->id,
                    $managerPosition,
                    'DL-'.$cc.'-M',
                    'B2'
                )
            );

            $driver01 = Driver::firstOrCreate(
                ['code' => 'DRV-'.$cc.'-01'],
                $this->driverPayload(
                    'Tài xế trưởng '.$cc,
                    'drv.'.strtolower($cc).'.01@demo.vn',
                    $mainOffice->id,
                    $fleetMain->id,
                    $driverPosition,
                    'DL-'.$cc.'-01',
                    'C'
                )
            );

            $mainOffice->update(['manager_id' => $managerDriver->id]);

            $drivers = [$managerDriver, $driver01];

            // Thêm 8 tài xế (mã 02–09)
            for ($n = 2; $n <= 9; $n++) {
                $suffix = str_pad((string) $n, 2, '0', STR_PAD_LEFT);
                $officeId = $n % 3 === 0 ? $branchOffice->id : $mainOffice->id;
                if ($officeId === $branchOffice->id) {
                    $deptId = $fleetBranch->id;
                } elseif ($n % 4 === 0) {
                    $deptId = $logistics->id;
                } else {
                    $deptId = $fleetMain->id;
                }
                $drivers[] = Driver::firstOrCreate(
                    ['code' => 'DRV-'.$cc.'-'.$suffix],
                    $this->driverPayload(
                        'Tài xế '.$cc.' #'.$suffix,
                        'drv.'.strtolower($cc).'.'.$suffix.'@demo.vn',
                        $officeId,
                        $deptId,
                        $driverPosition,
                        'DL-'.$cc.'-'.$suffix,
                        $n % 2 === 0 ? 'C' : 'B2'
                    )
                );
            }

            // Quản lý chi nhánh
            $branchMgr = Driver::firstOrCreate(
                ['code' => 'DRV-'.$cc.'-BMGR'],
                $this->driverPayload(
                    'QL chi nhánh '.$def['branch']['name'],
                    'mgr.'.strtolower($cc).'.branch@demo.vn',
                    $branchOffice->id,
                    $fleetBranch->id,
                    $managerPosition,
                    'DL-'.$cc.'-BM',
                    'B2'
                )
            );
            $drivers[] = $branchMgr;
            $branchOffice->update(['manager_id' => $branchMgr->id]);

            // 8 xe / công ty (biển unique)
            $vehicles = [];
            for ($v = 1; $v <= 8; $v++) {
                $plate = $def['plate_prefix'].'-'.str_pad((string) (10000 + $index * 1000 + $v), 5, '0', STR_PAD_LEFT);
                $officeId = $v % 2 === 0 ? $branchOffice->id : $mainOffice->id;
                $vehicles[] = Vehicle::firstOrCreate(
                    ['plate_number' => $plate],
                    [
                        'office_id' => $officeId,
                        'type' => $v % 3 === 0 ? 'van' : 'truck',
                        'brand' => $v % 2 === 0 ? 'Hino' : 'Isuzu',
                        'model' => (string) (300 + $v),
                        'year' => 2019 + ($v % 5),
                        'capacity' => 8 + $v,
                        'status' => 'active',
                    ]
                );
            }

            // 15 khách hàng / công ty
            $customers = [];
            for ($c = 1; $c <= 15; $c++) {
                $customers[] = Customer::firstOrCreate(
                    ['email' => 'cust-'.strtolower($cc).'-'.str_pad((string) $c, 3, '0', STR_PAD_LEFT).'@demo.vn'],
                    [
                        'type' => $c % 4 === 0 ? 'individual' : 'company',
                        'name' => 'KH '.$cc.' #'.$c,
                        'tax_code' => $c % 4 === 0 ? null : sprintf('0%d%08d', $index + 1, 10000000 + $c),
                        'phone' => $this->nextPhone(),
                        'address' => $def['company']['address'],
                    ]
                );
            }

            // 24 chuyến / công ty (mã trip unique)
            $routePool = $def['routes'];
            for ($t = 1; $t <= 24; $t++) {
                $tripCode = 'TRIP-'.$cc.'-'.str_pad((string) $t, 4, '0', STR_PAD_LEFT);
                $drv = $drivers[($t - 1) % count($drivers)];
                $veh = $vehicles[($t - 1) % count($vehicles)];
                $cust = $customers[($t - 1) % count($customers)];
                $route = $routePool[($t - 1) % count($routePool)];
                // Chỉ 1 chuyến in_progress / công ty (tránh nhiều tài xế cùng lúc in_progress trùng quy tắc nghiệp vụ)
                $status = match (true) {
                    $t === 1 => 'pending',
                    $t === 2 => 'in_progress',
                    $t === 3 => 'cancelled',
                    default => 'completed',
                };
                $start = Carbon::now()->subDays(60 - $t)->setHour(8)->setMinute(0);
                $end = (clone $start)->addHours(4);

                $startTime = null;
                $endTime = null;
                if ($status === 'pending') {
                    $startTime = null;
                    $endTime = null;
                } elseif ($status === 'in_progress') {
                    $startTime = $start;
                    $endTime = null;
                } elseif ($status === 'cancelled') {
                    $startTime = $start;
                    $endTime = null;
                } else {
                    $startTime = $start;
                    $endTime = $end;
                }

                Trip::firstOrCreate(
                    ['code' => $tripCode],
                    [
                        'customer_id' => $cust->id,
                        'driver_id' => $drv->id,
                        'vehicle_id' => $veh->id,
                        'start_point' => $route[0],
                        'end_point' => $route[1],
                        'distance_km' => 50 + ($t * 17) % 400,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'price' => 500000 + $t * 125000,
                        'status' => $status,
                    ]
                );
            }

            $this->dedupeInProgressTripsPerDriver($cc);

            // Phân công xe (mỗi xe 1–2 kỳ)
            foreach ($vehicles as $vi => $vehicle) {
                $drv = $drivers[$vi % count($drivers)];
                VehicleAssignment::firstOrCreate(
                    [
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $drv->id,
                        'from_date' => '2025-01-01',
                    ],
                    ['to_date' => '2025-12-31']
                );
                VehicleAssignment::firstOrCreate(
                    [
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $drivers[($vi + 1) % count($drivers)]->id,
                        'from_date' => '2026-01-01',
                    ],
                    ['to_date' => null]
                );
            }

            // Chi phí xe
            foreach ($vehicles as $ei => $vehicle) {
                for ($k = 0; $k < 3; $k++) {
                    VehicleExpense::firstOrCreate(
                        [
                            'vehicle_id' => $vehicle->id,
                            'expense_date' => Carbon::now()->subDays($ei * 3 + $k)->toDateString(),
                            'type' => ['fuel', 'maintenance', 'toll'][$k],
                        ],
                        [
                            'driver_id' => $drivers[($ei + $k) % count($drivers)]->id,
                            'amount' => 200000 + $ei * 50000 + $k * 30000,
                            'note' => 'Seed '.$cc.' xe '.$vehicle->plate_number,
                        ]
                    );
                }
            }
        }

        $this->command?->info('ThreeCompaniesSeeder: mỗi công ty — 2 VP, 3 phòng ban (VP chính) + 1 PB chi nhánh, 11 tài xế, 8 xe, 15 KH, 24 chuyến, phân công xe, 24 dòng chi phí xe.');
    }

    /**
     * @return array<string, mixed>
     */
    private function driverPayload(
        string $name,
        string $email,
        int $officeId,
        int $departmentId,
        Position $position,
        string $licenseNo,
        string $licenseClass
    ): array {
        return [
            'name' => $name,
            'email' => $email,
            'phone' => $this->nextPhone(),
            'dob' => '1990-03-15',
            'gender' => 'male',
            'address' => 'Việt Nam',
            'office_id' => $officeId,
            'department_id' => $departmentId,
            'position_id' => $position->id,
            'status' => 'active',
            'join_date' => '2022-06-01',
            'license_no' => $licenseNo,
            'license_class' => $licenseClass,
            'expired_date' => '2030-12-31',
            'available_status' => 'available',
        ];
    }

    private function nextPhone(): string
    {
        $this->phoneCounter++;

        return '09'.str_pad((string) ($this->phoneCounter % 100000000), 8, '0', STR_PAD_LEFT);
    }

    private function dedupeInProgressTripsPerDriver(string $companyCode): void
    {
        $seen = [];
        $trips = Trip::query()
            ->where('code', 'like', 'TRIP-'.$companyCode.'-%')
            ->where('status', 'in_progress')
            ->orderBy('id')
            ->get(['id', 'driver_id']);

        foreach ($trips as $trip) {
            if (isset($seen[$trip->driver_id])) {
                Trip::query()->whereKey($trip->id)->update([
                    'status' => 'completed',
                    'end_time' => Carbon::now()->subDay(),
                ]);
            } else {
                $seen[$trip->driver_id] = true;
            }
        }
    }
}
