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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder dữ liệu demo đầy đủ cho release cho khách hàng.
 * Chạy: php artisan db:seed --class=DemoReleasedSeeder
 */
class DemoReleasedSeeder extends Seeder
{
    private int $companyId;
    private int $adminId;

    public function run(): void
    {
        $this->command->info('🚀 Seeding demo data for customer release...');

        $this->setupCompanyAndAdmin();
        $this->seedLeaveTypes();
        $this->seedCustomers();
        $this->seedDrivers();
        $this->seedVehicles();
        $this->seedTrips();
        $this->seedMaintenanceRecords();
        $this->seedPaymentRecords();
        $this->seedLeaveRequests();

        $this->command->info('✅ Demo seed complete!');
        $this->command->table(
            ['Loại dữ liệu', 'Số lượng'],
            [
                ['Customers',          Customer::query()->where('company_id', $this->companyId)->count()],
                ['Drivers',            Driver::query()->where('company_id', $this->companyId)->count()],
                ['Vehicles',           Vehicle::query()->where('company_id', $this->companyId)->count()],
                ['Trips',              DB::table('trips')->where('company_id', $this->companyId)->count()],
                ['Payment records',    DB::table('payment_records')->where('company_id', $this->companyId)->count()],
                ['Maintenance records',DB::table('maintenance_records')->where('company_id', $this->companyId)->count()],
                ['Leave requests',     DB::table('leave_requests')->where('company_id', $this->companyId)->count()],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────
    //  Company & Admin
    // ─────────────────────────────────────────────────────────────
    private function setupCompanyAndAdmin(): void
    {
        $company = Company::query()->firstOrCreate(
            ['name' => 'ABC Transport Co., Ltd.'],
            [
                'code'        => 'ABCTRANS',
                'email'       => 'info@abctransport.vn',
                'phone'       => '024-3826-1122',
                'address'     => '45 Trần Hưng Đạo, Hoàn Kiếm, Hà Nội',
                'tax_code'    => '0101234567',
                'status'      => 'active',
            ]
        );
        $this->companyId = (int) $company->id;

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@abctransport.vn'],
            [
                'full_name' => 'Nguyễn Quản Lý',
                'username'  => 'admin_demo',
                'password'  => Hash::make('Demo@12345'),
                'role'      => 'admin',
                'status'    => 'active',
            ]
        );
        $this->adminId = (int) $admin->id;
        $admin->companies()->syncWithoutDetaching([$this->companyId => ['is_default' => true]]);

        // Dispatcher user
        $dispatcher = User::query()->firstOrCreate(
            ['email' => 'dispatcher@abctransport.vn'],
            [
                'full_name' => 'Trần Điều Phối',
                'username'  => 'dispatcher_demo',
                'password'  => Hash::make('Demo@12345'),
                'role'      => 'dispatcher',
                'status'    => 'active',
            ]
        );
        $dispatcher->companies()->syncWithoutDetaching([$this->companyId => ['is_default' => true]]);

        $this->command->info("  Company ID: {$this->companyId} | Admin: admin@abctransport.vn / Demo@12345");
    }

    // ─────────────────────────────────────────────────────────────
    //  Leave Types
    // ─────────────────────────────────────────────────────────────
    private function seedLeaveTypes(): void
    {
        $types = [
            ['code' => 'PHEP_NAM',  'name' => 'Phép năm',       'is_paid' => 1, 'annual_quota_days' => 12],
            ['code' => 'PHEP_OM',   'name' => 'Phép ốm',        'is_paid' => 1, 'annual_quota_days' => 10],
            ['code' => 'PHEP_KHONG_LUONG', 'name' => 'Nghỉ không lương', 'is_paid' => 0, 'annual_quota_days' => 0],
        ];

        foreach ($types as $type) {
            DB::table('leave_types')->updateOrInsert(
                ['code' => $type['code'], 'company_id' => $this->companyId],
                array_merge($type, [
                    'company_id'         => $this->companyId,
                    'status'             => 'active',
                    'allow_carry_forward'=> 0,
                    'requires_attachment'=> 0,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ])
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  Customers (15 records)
    // ─────────────────────────────────────────────────────────────
    private function seedCustomers(): void
    {
        $customers = [
            ['name' => 'Công ty TNHH Sản Xuất Minh Phát',   'type' => 'company',    'phone' => '0901-234-001', 'tax_code' => '0300123401', 'company_name' => 'TNHH Minh Phát',   'credit_limit' => 50000000],
            ['name' => 'Công ty CP Logistics Đông Nam Á',    'type' => 'company',    'phone' => '0901-234-002', 'tax_code' => '0300123402', 'company_name' => 'CP Logistics ĐNÁ', 'credit_limit' => 100000000],
            ['name' => 'Tập Đoàn Thương Mại Hoàng Gia',      'type' => 'company',    'phone' => '0901-234-003', 'tax_code' => '0300123403', 'company_name' => 'TĐ Hoàng Gia',     'credit_limit' => 200000000],
            ['name' => 'Công ty TNHH Thực Phẩm Sạch Việt',  'type' => 'company',    'phone' => '0901-234-004', 'tax_code' => '0300123404', 'company_name' => 'TP Sạch Việt',     'credit_limit' => 30000000],
            ['name' => 'Nhà máy Dệt May Phong Phú',          'type' => 'company',    'phone' => '0901-234-005', 'tax_code' => '0300123405', 'company_name' => 'Dệt May PP',       'credit_limit' => 75000000],
            ['name' => 'Công ty XNK Hoà Phát Steel',         'type' => 'company',    'phone' => '0901-234-006', 'tax_code' => '0300123406', 'company_name' => 'Hoà Phát Steel',   'credit_limit' => 150000000],
            ['name' => 'Siêu Thị Co.opmart Bình Dương',      'type' => 'company',    'phone' => '0901-234-007', 'tax_code' => '0300123407', 'company_name' => 'Co.opmart BD',     'credit_limit' => 80000000],
            ['name' => 'Công ty CP Xây Dựng Trường Thịnh',  'type' => 'company',    'phone' => '0901-234-008', 'tax_code' => '0300123408', 'company_name' => 'XD Trường Thịnh', 'credit_limit' => 60000000],
            ['name' => 'Kho Lạnh Đông Nam ICD',              'type' => 'company',    'phone' => '0901-234-009', 'tax_code' => '0300123409', 'company_name' => 'Kho Lạnh ĐN',     'credit_limit' => 40000000],
            ['name' => 'Công ty TNHH Nhựa Bao Bì Tiến Long', 'type' => 'company',   'phone' => '0901-234-010', 'tax_code' => '0300123410', 'company_name' => 'Nhựa Tiến Long',  'credit_limit' => 25000000],
            ['name' => 'Nguyễn Văn An',                      'type' => 'individual', 'phone' => '0912-345-011', 'tax_code' => null,         'company_name' => null,               'credit_limit' => 10000000],
            ['name' => 'Trần Thị Bích Ngọc',                 'type' => 'individual', 'phone' => '0912-345-012', 'tax_code' => null,         'company_name' => null,               'credit_limit' => 5000000],
            ['name' => 'Lê Minh Tuấn',                       'type' => 'individual', 'phone' => '0912-345-013', 'tax_code' => null,         'company_name' => null,               'credit_limit' => 8000000],
            ['name' => 'Phạm Quốc Hùng',                     'type' => 'individual', 'phone' => '0912-345-014', 'tax_code' => null,         'company_name' => null,               'credit_limit' => 15000000],
            ['name' => 'Võ Thị Mai Linh',                    'type' => 'individual', 'phone' => '0912-345-015', 'tax_code' => null,         'company_name' => null,               'credit_limit' => 7000000],
        ];

        foreach ($customers as $i => $data) {
            Customer::query()->firstOrCreate(
                ['phone' => $data['phone'], 'company_id' => $this->companyId],
                array_merge($data, [
                    'company_id'        => $this->companyId,
                    'email'             => 'khachhang' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '@demo.vn',
                    'is_active'         => true,
                    'payment_terms_days'=> in_array($data['type'], ['company']) ? 30 : 0,
                ])
            );
        }
        $this->command->info('  ✓ 15 customers');
    }

    // ─────────────────────────────────────────────────────────────
    //  Drivers (8 records)
    // ─────────────────────────────────────────────────────────────
    private function seedDrivers(): void
    {
        $drivers = [
            ['code' => 'DRV-001', 'name' => 'Đinh Văn Hùng',     'phone' => '0977-001-101', 'license' => 'B2', 'license_no' => 'DLG-001101'],
            ['code' => 'DRV-002', 'name' => 'Nguyễn Thành Trung', 'phone' => '0977-001-102', 'license' => 'C',  'license_no' => 'DLG-001102'],
            ['code' => 'DRV-003', 'name' => 'Phan Thanh Bình',    'phone' => '0977-001-103', 'license' => 'C',  'license_no' => 'DLG-001103'],
            ['code' => 'DRV-004', 'name' => 'Lương Đức Minh',     'phone' => '0977-001-104', 'license' => 'FC', 'license_no' => 'DLG-001104'],
            ['code' => 'DRV-005', 'name' => 'Trần Quang Khải',    'phone' => '0977-001-105', 'license' => 'FC', 'license_no' => 'DLG-001105'],
            ['code' => 'DRV-006', 'name' => 'Hoàng Văn Tài',      'phone' => '0977-001-106', 'license' => 'C',  'license_no' => 'DLG-001106'],
            ['code' => 'DRV-007', 'name' => 'Vũ Đình Sơn',        'phone' => '0977-001-107', 'license' => 'B2', 'license_no' => 'DLG-001107'],
            ['code' => 'DRV-008', 'name' => 'Đặng Hữu Phước',     'phone' => '0977-001-108', 'license' => 'FC', 'license_no' => 'DLG-001108'],
        ];

        foreach ($drivers as $d) {
            Driver::query()->firstOrCreate(
                ['phone' => $d['phone'], 'company_id' => $this->companyId],
                [
                    'company_id'        => $this->companyId,
                    'code'              => $d['code'],
                    'name'              => $d['name'],
                    'phone'             => $d['phone'],
                    'license_class'     => $d['license'],
                    'license_no'        => $d['license_no'],
                    'status'            => 'active',
                    'available_status'  => 'available',
                    'gender'            => 'male',
                ]
            );
        }
        $this->command->info('  ✓ 8 drivers');
    }

    // ─────────────────────────────────────────────────────────────
    //  Vehicles (8 records)
    // ─────────────────────────────────────────────────────────────
    private function seedVehicles(): void
    {
        $vehicles = [
            ['plate' => '51H-234.56', 'type' => 'truck',   'brand' => 'Hino',   'model' => 'FC9JLSW', 'year' => 2021, 'payload' => 5,  'status' => 'active'],
            ['plate' => '51H-234.57', 'type' => 'truck',   'brand' => 'Isuzu',  'model' => 'NQR 270', 'year' => 2020, 'payload' => 5,  'status' => 'active'],
            ['plate' => '29H-567.89', 'type' => 'truck',   'brand' => 'Hino',   'model' => 'GH8JMSA', 'year' => 2022, 'payload' => 8,  'status' => 'active'],
            ['plate' => '29H-567.90', 'type' => 'truck',   'brand' => 'Thaco',  'model' => 'Mooc 40T', 'year'=> 2019, 'payload' => 30, 'status' => 'active'],
            ['plate' => '43H-112.34', 'type' => 'truck',   'brand' => 'Mitsubishi', 'model' => 'Fuso FJ', 'year' => 2021, 'payload' => 10, 'status' => 'active'],
            ['plate' => '43H-112.35', 'type' => 'truck',   'brand' => 'Isuzu',  'model' => 'FVM 34T', 'year' => 2020, 'payload' => 14, 'status' => 'active'],
            ['plate' => '92H-998.77', 'type' => 'van',     'brand' => 'Ford',   'model' => 'Transit',  'year' => 2022, 'payload' => 2,  'status' => 'active'],
            ['plate' => '77H-444.22', 'type' => 'truck',   'brand' => 'Hyundai','model' => 'HD99',     'year' => 2018, 'payload' => 7,  'status' => 'maintenance'],
        ];

        foreach ($vehicles as $v) {
            Vehicle::query()->firstOrCreate(
                ['plate_number' => $v['plate'], 'company_id' => $this->companyId],
                [
                    'company_id'   => $this->companyId,
                    'plate_number' => $v['plate'],
                    'type'         => $v['type'],
                    'brand'        => $v['brand'],
                    'model'        => $v['model'],
                    'year'         => $v['year'],
                    'max_load_ton'         => $v['payload'],
                    'status'               => $v['status'],
                    'fuel_type'            => 'diesel',
                    'current_odometer_km'  => rand(30000, 150000),
                ]
            );
        }
        $this->command->info('  ✓ 8 vehicles');
    }

    // ─────────────────────────────────────────────────────────────
    //  Trips (30 records)
    // ─────────────────────────────────────────────────────────────
    private function seedTrips(): void
    {
        if (! Schema::hasTable('trips')) {
            return;
        }

        $customerIds = Customer::query()->where('company_id', $this->companyId)->pluck('id')->values();
        $driverIds   = Driver::query()->where('company_id', $this->companyId)->where('status', 'active')->pluck('id')->values();
        $vehicleIds  = Vehicle::query()->where('company_id', $this->companyId)->where('status', 'active')->pluck('id')->values();

        $routes = [
            ['from' => 'Kho Long Biên, Hà Nội',           'to' => 'KCN Đình Vũ, Hải Phòng',              'km' => 120, 'price' => 4500000],
            ['from' => 'Cảng Cát Lái, TP.HCM',            'to' => 'KCN Sóng Thần, Bình Dương',            'km' => 38,  'price' => 1800000],
            ['from' => 'Kho Đà Nẵng',                     'to' => 'KCN Phú Bài, Huế',                     'km' => 95,  'price' => 3200000],
            ['from' => 'ICD Tân Cảng, TP.HCM',            'to' => 'KCN Amata Biên Hoà, Đồng Nai',         'km' => 55,  'price' => 2200000],
            ['from' => 'Cảng Hải Phòng',                  'to' => 'KCN Thăng Long, Hà Nội',               'km' => 110, 'price' => 4000000],
            ['from' => 'Kho Bắc Ninh',                    'to' => 'Cảng Hải Phòng',                       'km' => 90,  'price' => 3500000],
            ['from' => 'KCN Vân Trung, Bắc Giang',        'to' => 'Cảng Đình Vũ',                         'km' => 105, 'price' => 3800000],
            ['from' => 'Nhà máy Bình Dương',              'to' => 'Cảng VICT TP.HCM',                     'km' => 30,  'price' => 1500000],
            ['from' => 'KCN VSIP Hải Phòng',              'to' => 'Ga Hàng Hoá Giáp Bát',                 'km' => 100, 'price' => 3600000],
            ['from' => 'Kho Thủ Đức, TP.HCM',             'to' => 'KCN Long Hậu, Long An',                'km' => 45,  'price' => 2000000],
            ['from' => 'Cảng Quy Nhơn',                   'to' => 'KCN Nhơn Hội, Bình Định',              'km' => 35,  'price' => 1600000],
            ['from' => 'Kho lạnh Bình Tân, TP.HCM',       'to' => 'Siêu thị Big C Đồng Nai',              'km' => 42,  'price' => 1900000],
            ['from' => 'KCN Tam Thăng, Quảng Nam',        'to' => 'Cảng Tiên Sa, Đà Nẵng',               'km' => 65,  'price' => 2500000],
            ['from' => 'Nhà máy thép Hoà Phát Hải Dương', 'to' => 'Dự án Cầu Vĩnh Thịnh, Hà Nội',        'km' => 75,  'price' => 2800000],
            ['from' => 'Kho Cần Thơ',                     'to' => 'Cảng Cái Mép, Vũng Tàu',              'km' => 200, 'price' => 7500000],
        ];

        $tripData = [
            // Completed (10 trips - 3 tháng qua)
            ['status' => 'completed',   'daysAgo' => 85, 'surcharge' => 500000],
            ['status' => 'completed',   'daysAgo' => 78, 'surcharge' => 0],
            ['status' => 'completed',   'daysAgo' => 70, 'surcharge' => 300000],
            ['status' => 'completed',   'daysAgo' => 62, 'surcharge' => 200000],
            ['status' => 'completed',   'daysAgo' => 55, 'surcharge' => 0],
            ['status' => 'completed',   'daysAgo' => 48, 'surcharge' => 400000],
            ['status' => 'completed',   'daysAgo' => 35, 'surcharge' => 150000],
            ['status' => 'completed',   'daysAgo' => 21, 'surcharge' => 0],
            ['status' => 'completed',   'daysAgo' => 14, 'surcharge' => 250000],
            ['status' => 'completed',   'daysAgo' => 7,  'surcharge' => 0],
            // Delivered (2 trips)
            ['status' => 'delivered',   'daysAgo' => 3,  'surcharge' => 100000],
            ['status' => 'delivered',   'daysAgo' => 2,  'surcharge' => 0],
            // In progress (5 trips - hôm nay)
            ['status' => 'in_progress', 'daysAgo' => 0,  'surcharge' => 0],
            ['status' => 'in_progress', 'daysAgo' => 0,  'surcharge' => 200000],
            ['status' => 'in_progress', 'daysAgo' => 0,  'surcharge' => 0],
            ['status' => 'in_progress', 'daysAgo' => 1,  'surcharge' => 300000],
            ['status' => 'in_progress', 'daysAgo' => 1,  'surcharge' => 0],
            // Assigned (5 trips - hôm nay hoặc ngày mai)
            ['status' => 'assigned',    'daysAgo' => -1, 'surcharge' => 0],
            ['status' => 'assigned',    'daysAgo' => -1, 'surcharge' => 150000],
            ['status' => 'assigned',    'daysAgo' => -2, 'surcharge' => 0],
            ['status' => 'assigned',    'daysAgo' => -2, 'surcharge' => 200000],
            ['status' => 'assigned',    'daysAgo' => -3, 'surcharge' => 0],
            // Pending (5 trips - tương lai)
            ['status' => 'pending',     'daysAgo' => -3, 'surcharge' => 0],
            ['status' => 'pending',     'daysAgo' => -4, 'surcharge' => 0],
            ['status' => 'pending',     'daysAgo' => -5, 'surcharge' => 0],
            ['status' => 'pending',     'daysAgo' => -5, 'surcharge' => 100000],
            ['status' => 'pending',     'daysAgo' => -7, 'surcharge' => 0],
            // Cancelled (3 trips)
            ['status' => 'cancelled',   'daysAgo' => 30, 'surcharge' => 0, 'reason' => 'Khách hàng huỷ do thay đổi kế hoạch sản xuất'],
            ['status' => 'cancelled',   'daysAgo' => 20, 'surcharge' => 0, 'reason' => 'Tắc đường, giao hàng không kịp deadline'],
            ['status' => 'cancelled',   'daysAgo' => 10, 'surcharge' => 0, 'reason' => 'Xe bị hỏng, chưa có xe thay thế'],
        ];

        $now = now();

        foreach ($tripData as $idx => $td) {
            $routeIdx  = $idx % count($routes);
            $route     = $routes[$routeIdx];
            $custIdx   = $idx % $customerIds->count();
            $driIdx    = $idx % $driverIds->count();
            $vehIdx    = $idx % $vehicleIds->count();

            $schedDate = $now->copy()->subDays($td['daysAgo']);
            $isCompleted = in_array($td['status'], ['completed', 'delivered']);
            $isActive    = in_array($td['status'], ['in_progress', 'assigned']);
            $hasCrew     = in_array($td['status'], ['assigned', 'in_progress', 'delivered', 'completed']);

            $code = sprintf('DH-%s-%04d', $now->format('m'), $idx + 1);
            $basePrice = $route['price'];
            $surcharge = $td['surcharge'];

            DB::table('trips')->updateOrInsert(
                ['code' => $code, 'company_id' => $this->companyId],
                array_filter([
                    'code'               => $code,
                    'company_id'         => $this->companyId,
                    'customer_id'        => $customerIds[$custIdx],
                    'driver_id'          => $hasCrew ? $driverIds[$driIdx] : null,
                    'vehicle_id'         => $hasCrew ? $vehicleIds[$vehIdx] : null,
                    'dispatcher_id'      => $this->adminId,
                    'start_point'        => $route['from'],
                    'end_point'          => $route['to'],
                    'cargo_description'  => $this->cargos()[$idx % 8],
                    'cargo_weight_ton'   => rand(1, 15),
                    'distance_km'        => $route['km'],
                    'scheduled_date'     => $schedDate->toDateString(),
                    'scheduled_time_from'=> '08:00:00',
                    'scheduled_time_to'  => '17:00:00',
                    'assigned_at'        => $hasCrew ? $schedDate->copy()->subDay()->toDateTimeString() : null,
                    'start_time'         => ($isActive || $isCompleted) ? $schedDate->copy()->setTimeFromTimeString('08:15:00')->toDateTimeString() : null,
                    'end_time'           => $isCompleted ? $schedDate->copy()->setTimeFromTimeString('15:45:00')->toDateTimeString() : null,
                    'base_price'         => $basePrice,
                    'price'              => $basePrice,
                    'surcharge_amount'   => $surcharge,
                    'total_revenue'      => $basePrice + $surcharge,
                    'payment_status'     => $isCompleted ? 'paid' : 'unpaid',
                    'payment_method'     => $isCompleted ? 'bank_transfer' : null,
                    'status'             => $td['status'],
                    'cancellation_reason'=> $td['reason'] ?? null,
                    'cancelled_at'       => $td['status'] === 'cancelled' ? $schedDate->toDateTimeString() : null,
                    'internal_notes'     => null,
                    'created_at'         => $schedDate->copy()->subDays(2)->toDateTimeString(),
                    'updated_at'         => $now->toDateTimeString(),
                ], static fn ($v) => $v !== null)
            );
        }

        $this->command->info('  ✓ 30 trips (10 completed, 2 delivered, 5 in_progress, 5 assigned, 5 pending, 3 cancelled)');
    }

    // ─────────────────────────────────────────────────────────────
    //  Maintenance Records (4 records)
    // ─────────────────────────────────────────────────────────────
    private function seedMaintenanceRecords(): void
    {
        if (! Schema::hasTable('maintenance_records')) {
            return;
        }

        $vehicles = Vehicle::query()->where('company_id', $this->companyId)->get();
        if ($vehicles->isEmpty()) {
            return;
        }

        $now = now();
        $records = [
            [
                'vehicle_id'   => $vehicles[0]->id,
                'type'         => 'scheduled',
                'title'        => 'Bảo dưỡng định kỳ 50.000km',
                'description'  => 'Thay dầu, lọc dầu, kiểm tra hệ thống phanh',
                'odometer_km'  => 50000,
                'started_date' => $now->copy()->subDays(60)->toDateString(),
                'completed_date'=> $now->copy()->subDays(58)->toDateString(),
                'garage_name'  => 'Garage Hino Hà Nội',
                'total_cost'   => 3500000,
                'invoice_number'=> 'GN-2024-001',
                'status'       => 'completed',
            ],
            [
                'vehicle_id'   => $vehicles[2]->id,
                'type'         => 'unscheduled',
                'title'        => 'Sửa lốp xe sau bên trái bị xịt',
                'description'  => 'Thay lốp mới 11R22.5',
                'odometer_km'  => 78500,
                'started_date' => $now->copy()->subDays(15)->toDateString(),
                'completed_date'=> $now->copy()->subDays(14)->toDateString(),
                'garage_name'  => 'Vỏ xe Minh Đức',
                'total_cost'   => 2800000,
                'invoice_number'=> 'MD-2024-088',
                'status'       => 'completed',
            ],
            [
                'vehicle_id'   => $vehicles[7]->id, // vehicle with maintenance status
                'type'         => 'scheduled',
                'title'        => 'Đại tu động cơ 200.000km',
                'description'  => 'Đại tu toàn bộ động cơ, thay xéc-măng, gioăng máy',
                'odometer_km'  => 198000,
                'started_date' => $now->copy()->subDays(5)->toDateString(),
                'completed_date'=> null,
                'garage_name'  => 'Xưởng Trung Tâm Hyundai',
                'total_cost'   => 35000000,
                'invoice_number'=> null,
                'status'       => 'in_progress',
            ],
            [
                'vehicle_id'   => $vehicles[4]->id,
                'type'         => 'scheduled',
                'title'        => 'Kiểm định an toàn kỹ thuật',
                'description'  => 'Đăng kiểm định kỳ 1 năm',
                'odometer_km'  => 65000,
                'started_date' => $now->copy()->subDays(3)->toDateString(),
                'completed_date'=> $now->copy()->subDays(2)->toDateString(),
                'garage_name'  => 'Trung tâm Đăng kiểm 07-01D',
                'total_cost'   => 560000,
                'invoice_number'=> 'DK-2024-7890',
                'status'       => 'completed',
            ],
        ];

        foreach ($records as $r) {
            $key = ['vehicle_id' => $r['vehicle_id'], 'title' => $r['title'], 'company_id' => $this->companyId];
            DB::table('maintenance_records')->updateOrInsert(
                $key,
                array_merge($r, ['company_id' => $this->companyId, 'created_at' => now(), 'updated_at' => now()])
            );
        }

        $this->command->info('  ✓ 4 maintenance records');
    }

    // ─────────────────────────────────────────────────────────────
    //  Payment Records (for completed trips)
    // ─────────────────────────────────────────────────────────────
    private function seedPaymentRecords(): void
    {
        if (! Schema::hasTable('payment_records')) {
            return;
        }

        $completedTrips = DB::table('trips')
            ->where('company_id', $this->companyId)
            ->whereIn('status', ['completed', 'delivered'])
            ->get();

        foreach ($completedTrips as $trip) {
            DB::table('payment_records')->updateOrInsert(
                ['company_id' => $this->companyId, 'customer_id' => $trip->customer_id, 'bank_reference' => 'TT-' . $trip->code],
                [
                    'company_id'      => $this->companyId,
                    'customer_id'     => $trip->customer_id,
                    'payment_date'    => now()->subDays(rand(1, 5))->toDateString(),
                    'amount'          => $trip->total_revenue,
                    'payment_method'  => 'bank_transfer',
                    'bank_reference'  => 'TT-' . $trip->code,
                    'notes'           => 'Thanh toán chuyến hàng ' . $trip->code,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
        }

        $this->command->info('  ✓ ' . $completedTrips->count() . ' payment records');
    }

    // ─────────────────────────────────────────────────────────────
    //  Leave Requests (6 records)
    // ─────────────────────────────────────────────────────────────
    private function seedLeaveRequests(): void
    {
        if (! Schema::hasTable('leave_requests') || ! Schema::hasTable('leave_types')) {
            return;
        }

        $leaveTypeId = DB::table('leave_types')
            ->where('company_id', $this->companyId)
            ->where('code', 'PHEP_NAM')
            ->value('id');

        $omTypeId = DB::table('leave_types')
            ->where('company_id', $this->companyId)
            ->where('code', 'PHEP_OM')
            ->value('id');

        if (! $leaveTypeId) {
            return;
        }

        $drivers = Driver::query()->where('company_id', $this->companyId)->pluck('id')->values();
        if ($drivers->isEmpty()) {
            return;
        }

        $now = now();
        $requests = [
            [
                'driver_id'    => $drivers[0],
                'leave_type_id'=> $leaveTypeId,
                'from_date'    => $now->copy()->subDays(45)->toDateString(),
                'to_date'      => $now->copy()->subDays(43)->toDateString(),
                'total_days'   => 3,
                'reason'       => 'Nghỉ phép năm kết hợp việc gia đình',
                'status'       => 'approved',
                'approved_by'  => $this->adminId,
                'approved_at'  => $now->copy()->subDays(50)->toDateTimeString(),
            ],
            [
                'driver_id'    => $drivers[2],
                'leave_type_id'=> $omTypeId ?? $leaveTypeId,
                'from_date'    => $now->copy()->subDays(20)->toDateString(),
                'to_date'      => $now->copy()->subDays(18)->toDateString(),
                'total_days'   => 3,
                'reason'       => 'Ốm, có giấy khám bác sĩ',
                'status'       => 'approved',
                'approved_by'  => $this->adminId,
                'approved_at'  => $now->copy()->subDays(22)->toDateTimeString(),
            ],
            [
                'driver_id'    => $drivers[4],
                'leave_type_id'=> $leaveTypeId,
                'from_date'    => $now->copy()->addDays(7)->toDateString(),
                'to_date'      => $now->copy()->addDays(9)->toDateString(),
                'total_days'   => 3,
                'reason'       => 'Về quê thăm gia đình',
                'status'       => 'pending',
                'approved_by'  => null,
                'approved_at'  => null,
            ],
            [
                'driver_id'    => $drivers[1],
                'leave_type_id'=> $leaveTypeId,
                'from_date'    => $now->copy()->addDays(14)->toDateString(),
                'to_date'      => $now->copy()->addDays(15)->toDateString(),
                'total_days'   => 2,
                'reason'       => 'Nghỉ phép năm',
                'status'       => 'approved',
                'approved_by'  => $this->adminId,
                'approved_at'  => $now->copy()->subDays(2)->toDateTimeString(),
            ],
            [
                'driver_id'    => $drivers[3],
                'leave_type_id'=> $omTypeId ?? $leaveTypeId,
                'from_date'    => $now->copy()->subDays(7)->toDateString(),
                'to_date'      => $now->copy()->subDays(6)->toDateString(),
                'total_days'   => 2,
                'reason'       => 'Sốt cao, nghỉ dưỡng bệnh',
                'status'       => 'approved',
                'approved_by'  => $this->adminId,
                'approved_at'  => $now->copy()->subDays(8)->toDateTimeString(),
            ],
            [
                'driver_id'    => $drivers[5],
                'leave_type_id'=> $leaveTypeId,
                'from_date'    => $now->copy()->addDays(20)->toDateString(),
                'to_date'      => $now->copy()->addDays(22)->toDateString(),
                'total_days'   => 3,
                'reason'       => 'Xin nghỉ phép năm, có việc riêng',
                'status'       => 'pending',
                'approved_by'  => null,
                'approved_at'  => null,
            ],
        ];

        foreach ($requests as $r) {
            DB::table('leave_requests')->updateOrInsert(
                [
                    'driver_id'  => $r['driver_id'],
                    'from_date'  => $r['from_date'],
                    'company_id' => $this->companyId,
                ],
                array_merge($r, [
                    'company_id' => $this->companyId,
                    'created_by' => $this->adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('  ✓ 6 leave requests');
    }

    // ─────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────
    private function cargos(): array
    {
        return [
            'Hàng điện tử, máy móc thiết bị',
            'Thực phẩm đông lạnh',
            'Vật liệu xây dựng (gạch, xi măng)',
            'Hàng dệt may, quần áo xuất khẩu',
            'Thép tấm, ống thép',
            'Hoá chất công nghiệp (đóng thùng)',
            'Hàng tiêu dùng tổng hợp',
            'Nông sản (gạo, cà phê)',
        ];
    }
}
