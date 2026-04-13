<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Spec: admin=all, manager=trips+vehicles+drivers, staff=view own.
     */
    public function run(): void
    {
        $permissionList = [
            'all' => 'Full access',
            'companies' => 'Companies',
            'offices' => 'Offices',
            'departments' => 'Departments',
            'positions' => 'Positions',
            'drivers' => 'Drivers',
            'vehicles' => 'Vehicles',
            'vehicle_assignments' => 'Vehicle assignments',
            'vehicle_expenses' => 'Vehicle expenses',
            'trips' => 'Trips',
            'customers' => 'Customers',
            'invoices' => 'Invoices',
            'users' => 'Users',
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'reports' => 'Reports',
            'payrolls' => 'Payrolls (driver MVP)',
        ];

        $permissions = [];
        foreach ($permissionList as $code => $name) {
            $permissions[$code] = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => $name]
            );
        }

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin'],
            ['description' => 'Administrator - full access']
        );
        $managerRole = Role::firstOrCreate(
            ['name' => 'manager'],
            ['description' => 'Manager - trip, vehicle, driver (own office)']
        );
        $staffRole = Role::firstOrCreate(
            ['name' => 'staff'],
            ['description' => 'Staff - view own data only']
        );

        $adminRole->permissions()->sync(Permission::pluck('id'));

        $managerRole->permissions()->sync([
            $permissions['trips']->id,
            $permissions['vehicles']->id,
            $permissions['drivers']->id,
            $permissions['vehicle_assignments']->id,
            $permissions['vehicle_expenses']->id,
            $permissions['customers']->id,
            $permissions['invoices']->id,
        ]);

        $staffRole->permissions()->sync([]);
    }
}
