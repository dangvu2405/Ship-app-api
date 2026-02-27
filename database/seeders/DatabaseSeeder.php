<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Office;
use App\Models\Department;
use App\Models\Position;
use App\Models\Employee;
use App\Models\Driver;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Allowance;
use App\Models\Deduction;
use App\Models\Vehicle;
use App\Models\TripBonusRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Company
        $company = Company::create([
            'code' => 'COMP001',
            'name' => 'ABC Transport Company',
            'tax_code' => '1234567890',
            'address' => '123 Main Street, City',
            'phone' => '0123456789',
            'email' => 'info@abctransport.com',
            'status' => 'active',
        ]);

        // Create Office
        $office = Office::create([
            'company_id' => $company->id,
            'code' => 'OFF001',
            'name' => 'Head Office',
            'address' => '123 Main Street, City',
        ]);

        // Create Departments
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

        // Create Positions
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

        $staffPosition = Position::create([
            'code' => 'STF',
            'name' => 'Staff',
            'base_salary' => 6000000,
            'level' => 1,
        ]);

        // Create Employees
        $manager = Employee::create([
            'code' => 'EMP001',
            'name' => 'John Manager',
            'email' => 'john.manager@abctransport.com',
            'phone' => '0912345678',
            'dob' => '1980-01-15',
            'gender' => 'male',
            'address' => '456 Manager Street',
            'office_id' => $office->id,
            'department_id' => $hrDept->id,
            'position_id' => $managerPosition->id,
            'type' => 'office',
            'status' => 'active',
            'join_date' => '2020-01-01',
        ]);

        $driver1 = Employee::create([
            'code' => 'EMP002',
            'name' => 'Mike Driver',
            'email' => 'mike.driver@abctransport.com',
            'phone' => '0912345679',
            'dob' => '1985-05-20',
            'gender' => 'male',
            'address' => '789 Driver Street',
            'office_id' => $office->id,
            'department_id' => $fleetDept->id,
            'position_id' => $driverPosition->id,
            'type' => 'driver',
            'status' => 'active',
            'join_date' => '2021-03-01',
        ]);

        $staff1 = Employee::create([
            'code' => 'EMP003',
            'name' => 'Jane Staff',
            'email' => 'jane.staff@abctransport.com',
            'phone' => '0912345680',
            'dob' => '1990-08-10',
            'gender' => 'female',
            'address' => '321 Staff Street',
            'office_id' => $office->id,
            'department_id' => $hrDept->id,
            'position_id' => $staffPosition->id,
            'type' => 'office',
            'status' => 'active',
            'join_date' => '2022-06-01',
        ]);

        // Create Driver
        Driver::create([
            'employee_id' => $driver1->id,
            'license_no' => 'DL123456',
            'license_class' => 'B2',
            'expired_date' => '2025-12-31',
            'available_status' => 'available',
        ]);

        // Update Office Manager
        $office->update(['manager_id' => $manager->id]);

        // Create Users
        $adminUser = User::create([
            'username' => 'admin',
            'email' => 'admin@abctransport.com',
            'password' => Hash::make('password'),
            'employee_id' => $manager->id,
            'status' => 'active',
        ]);

        $driverUser = User::create([
            'username' => 'driver1',
            'email' => 'driver1@abctransport.com',
            'password' => Hash::make('password'),
            'employee_id' => $driver1->id,
            'status' => 'active',
        ]);

        // Roles and permissions (full spec: run RolesAndPermissionsSeeder)
        $this->call(RolesAndPermissionsSeeder::class);

        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $driverRole = Role::firstOrCreate(
            ['name' => 'driver'],
            ['description' => 'Driver']
        );

        // Assign Roles to seeded users
        if ($adminRole && ! $adminUser->roles()->where('name', 'admin')->exists()) {
            $adminUser->roles()->attach($adminRole->id);
        }
        if ($managerRole && ! $adminUser->roles()->where('name', 'manager')->exists()) {
            $adminUser->roles()->attach($managerRole->id);
        }
        if ($driverRole && ! $driverUser->roles()->where('name', 'driver')->exists()) {
            $driverUser->roles()->attach($driverRole->id);
        }

        // Create Allowances
        Allowance::create([
            'code' => 'ALL001',
            'name' => 'Transport Allowance',
            'default_amount' => 500000,
            'taxable' => false,
        ]);

        Allowance::create([
            'code' => 'ALL002',
            'name' => 'Meal Allowance',
            'default_amount' => 300000,
            'taxable' => false,
        ]);

        // Create Deductions
        Deduction::create([
            'code' => 'DED001',
            'name' => 'Social Insurance',
        ]);

        Deduction::create([
            'code' => 'DED002',
            'name' => 'Health Insurance',
        ]);

        // Create Vehicles
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
        TripBonusRule::create([
            'min_km' => 0,
            'max_km' => 1000,
            'bonus_per_km' => 1000,
        ]);

        TripBonusRule::create([
            'min_km' => 1001,
            'max_km' => 2000,
            'bonus_per_km' => 1500,
        ]);

        TripBonusRule::create([
            'min_km' => 2001,
            'max_km' => null,
            'bonus_per_km' => 2000,
        ]);
    }
}
