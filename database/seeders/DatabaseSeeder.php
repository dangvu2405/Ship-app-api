<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Position;
use App\Models\Role;
use App\Models\TripBonusRule;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'code' => 'COMP001',
            'name' => 'ABC Transport Company',
            'tax_code' => '1234567890',
            'address' => '123 Main Street, City',
            'phone' => '0123456789',
            'email' => 'info@abctransport.com',
            'status' => 'active',
        ]);

        $office = Office::create([
            'company_id' => $company->id,
            'code' => 'OFF001',
            'name' => 'Head Office',
            'address' => '123 Main Street, City',
        ]);

        $hrDept = Department::create([
            'office_id' => $office->id,
            'code' => 'HR',
            'name' => 'Human Resources',
        ]);

        $fleetDept = Department::create([
            'office_id' => $office->id,
            'code' => 'FLEET',
            'name' => 'Fleet Management',
        ]);

        $managerPosition = Position::create([
            'code' => 'MGR',
            'name' => 'Manager',
            'base_salary' => 15000000,
            'level' => 5,
        ]);

        $driverPosition = Position::create([
            'code' => 'DRV',
            'name' => 'Driver',
            'base_salary' => 8000000,
            'level' => 2,
        ]);

        // Create Drivers
        $managerDriver = Driver::create([
            'code' => 'DRV001',
            'name' => 'John Manager',
            'email' => 'john.manager@abctransport.com',
            'phone' => '0912345678',
            'dob' => '1980-01-15',
            'gender' => 'male',
            'address' => '456 Manager Street',
            'office_id' => $office->id,
            'department_id' => $hrDept->id,
            'position_id' => $managerPosition->id,
            'status' => 'active',
            'join_date' => '2020-01-01',
            'license_no' => 'DL000001',
            'license_class' => 'B2',
            'expired_date' => '2027-12-31',
            'available_status' => 'available',
        ]);

        $driver1 = Driver::create([
            'code' => 'DRV002',
            'name' => 'Mike Driver',
            'email' => 'mike.driver@abctransport.com',
            'phone' => '0912345679',
            'dob' => '1985-05-20',
            'gender' => 'male',
            'address' => '789 Driver Street',
            'office_id' => $office->id,
            'department_id' => $fleetDept->id,
            'position_id' => $driverPosition->id,
            'status' => 'active',
            'join_date' => '2021-03-01',
            'license_no' => 'DL123456',
            'license_class' => 'B2',
            'expired_date' => '2025-12-31',
            'available_status' => 'available',
        ]);

        $office->update(['manager_id' => $managerDriver->id]);

        // Công ty thứ 2 + tài xế (để filter company_id=2 trên UI / API demo)
        $company2 = Company::create([
            'code' => 'COMP002',
            'name' => 'XYZ Logistics Demo',
            'tax_code' => '9876543210',
            'address' => '456 Industrial Zone',
            'phone' => '0283999888',
            'email' => 'contact@xyzlogistics.demo',
            'status' => 'active',
        ]);
        $office2 = Office::create([
            'company_id' => $company2->id,
            'code' => 'OFF002',
            'name' => 'Chi nhánh Nam',
            'address' => '456 Industrial Zone',
        ]);
        $fleetDept2 = Department::create([
            'office_id' => $office2->id,
            'code' => 'FLEET-S',
            'name' => 'Fleet Miền Nam',
        ]);
        $phones = ['0912000201', '0912000202', '0912000203'];
        $i = 0;
        foreach (['DRV201' => 'An Driver', 'DRV202' => 'Binh Driver', 'DRV203' => 'Cuong Driver'] as $code => $name) {
            Driver::create([
                'code' => $code,
                'name' => $name,
                'email' => strtolower($code).'@xyzlogistics.demo',
                'phone' => $phones[$i++],
                'dob' => '1990-06-01',
                'gender' => 'male',
                'address' => 'TP.HCM',
                'office_id' => $office2->id,
                'department_id' => $fleetDept2->id,
                'position_id' => $driverPosition->id,
                'status' => 'active',
                'join_date' => '2022-01-10',
                'license_no' => 'DL-'.$code,
                'license_class' => 'C',
                'expired_date' => '2028-12-31',
                'available_status' => 'available',
            ]);
        }

        // Create Users
        $adminUser = User::create([
            'username' => 'admin',
            'email' => 'admin@abctransport.com',
            'password' => Hash::make('password'),
            'driver_id' => $managerDriver->id,
            'status' => 'active',
        ]);

        $driverUser = User::create([
            'username' => 'driver1',
            'email' => 'driver1@abctransport.com',
            'password' => Hash::make('password'),
            'driver_id' => $driver1->id,
            'status' => 'active',
        ]);

        // Roles and permissions
        $this->call(RolesAndPermissionsSeeder::class);

        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $driverRole = Role::firstOrCreate(
            ['name' => 'driver'],
            ['description' => 'Driver']
        );

        if ($adminRole && ! $adminUser->roles()->where('name', 'admin')->exists()) {
            $adminUser->roles()->attach($adminRole->id);
        }
        if ($managerRole && ! $adminUser->roles()->where('name', 'manager')->exists()) {
            $adminUser->roles()->attach($managerRole->id);
        }
        if ($driverRole && ! $driverUser->roles()->where('name', 'driver')->exists()) {
            $driverUser->roles()->attach($driverRole->id);
        }

        // Create Vehicle
        Vehicle::create([
            'office_id' => $office->id,
            'plate_number' => '29A-12345',
            'type' => 'truck',
            'brand' => 'Toyota',
            'model' => 'Hiace',
            'year' => 2020,
            'capacity' => 16,
            'status' => 'active',
        ]);

        // Create Trip Bonus Rules
        TripBonusRule::create(['min_km' => 0, 'max_km' => 1000, 'bonus_per_km' => 1000]);
        TripBonusRule::create(['min_km' => 1001, 'max_km' => 2000, 'bonus_per_km' => 1500]);
        TripBonusRule::create(['min_km' => 2001, 'max_km' => null, 'bonus_per_km' => 2000]);

        $this->call(AllTablesSeeder::class);
        $this->call(SpecReferenceDataSeeder::class);

        if (Schema::hasTable('payrolls')) {
            $this->call(DriverPayrollBulkSeeder::class);
        }
    }
}
