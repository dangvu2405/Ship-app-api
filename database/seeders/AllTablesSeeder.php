<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CostCategory;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\LeaveType;
use App\Models\OrderStatusConfig;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class AllTablesSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first() ?? Company::factory()->create(['status' => 'active']);

        VehicleType::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Xe tải 5 tấn'],
            ['max_load_ton' => 5, 'required_license_class' => 'C', 'is_active' => true, 'sort_order' => 1]
        );

        CostCategory::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'FUEL'],
            ['name' => 'Nhiên liệu', 'requires_receipt' => true, 'approval_threshold' => null, 'is_active' => true, 'sort_order' => 1]
        );

        OrderStatusConfig::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'NEW'],
            ['name' => 'Mới', 'color' => '#6B7280', 'is_terminal' => false, 'sort_order' => 1]
        );

        LeaveType::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'ANNUAL'],
            ['name' => 'Nghỉ phép năm', 'is_paid' => true, 'annual_quota_days' => 12, 'status' => 'active']
        );

        $user = User::query()->first() ?? User::factory()->create([
            'status' => 'active',
            'role' => 'admin',
        ]);

        $driver = Driver::query()->first() ?? Driver::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        if ($user->driver_id === null) {
            $user->forceFill(['driver_id' => $driver->id])->saveQuietly();
        }

        $vehicle = Vehicle::query()->first() ?? Vehicle::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);

        $customer = Customer::query()->first() ?? Customer::factory()->create([
            'company_id' => $company->id,
        ]);

        Trip::query()->firstOrCreate(
            ['code' => 'TRIP-SEED-0001'],
            [
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'start_point' => 'Kho A',
                'end_point' => 'Kho B',
                'distance_km' => 35,
                'price' => 1500000,
                'status' => 'pending',
            ]
        );
    }
}
