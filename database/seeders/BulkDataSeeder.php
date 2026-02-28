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
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleExpense;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\Invoice;
use App\Models\TripBonusRule;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollAdjustment;
use App\Models\Attendance;
use App\Models\LoginLog;
use App\Models\AuditLog;
use App\Models\ExportLog;
use App\Models\ReportCache;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BulkDataSeeder extends Seeder
{
    /**
     * Seed 1000 records for each table
     */
    public function run(): void
    {
        $this->command->info('Starting bulk data seeding...');
        
        // Seed in order to respect foreign key constraints
        
        // 1. Companies (1000)
        $this->command->info('Seeding Companies...');
        $companies = Company::factory()->count(1000)->create();
        $this->command->info('✓ Companies seeded: ' . $companies->count());
        
        // 2. Offices (1000) - depends on companies
        $this->command->info('Seeding Offices...');
        $offices = collect();
        foreach ($companies as $company) {
            $office = Office::factory()->create(['company_id' => $company->id]);
            $offices->push($office);
        }
        // Add more offices to reach 1000
        $remainingOffices = 1000 - $offices->count();
        if ($remainingOffices > 0) {
            $moreOffices = Office::factory()->count($remainingOffices)->create();
            $offices = $offices->merge($moreOffices);
        }
        $this->command->info('✓ Offices seeded: ' . $offices->count());
        
        // 3. Departments (1000) - depends on offices
        $this->command->info('Seeding Departments...');
        $departments = collect();
        foreach ($offices->take(500) as $office) {
            $dept = Department::factory()->create(['office_id' => $office->id]);
            $departments->push($dept);
        }
        $remainingDepts = 1000 - $departments->count();
        if ($remainingDepts > 0) {
            $moreDepts = collect();
            for ($i = 0; $i < $remainingDepts; $i++) {
                $dept = Department::factory()->create([
                    'office_id' => $offices->random()->id
                ]);
                $moreDepts->push($dept);
            }
            $departments = $departments->merge($moreDepts);
        }
        $this->command->info('✓ Departments seeded: ' . $departments->count());
        
        // 4. Positions (1000)
        $this->command->info('Seeding Positions...');
        $positions = Position::factory()->count(1000)->create();
        $this->command->info('✓ Positions seeded: ' . $positions->count());
        
        // 5. Employees (1000) - depends on offices, departments, positions
        $this->command->info('Seeding Employees...');
        $employees = collect();
        for ($i = 0; $i < 1000; $i++) {
            $employee = Employee::factory()->create([
                'office_id' => $offices->random()->id,
                'department_id' => $departments->random()->id,
                'position_id' => $positions->random()->id,
            ]);
            $employees->push($employee);
        }
        $this->command->info('✓ Employees seeded: ' . $employees->count());
        
        // 6. Drivers (1000) - depends on employees (driver type)
        $this->command->info('Seeding Drivers...');
        $driverEmployees = $employees->where('type', 'driver');
        $drivers = collect();
        foreach ($driverEmployees->take(500) as $employee) {
            $driver = Driver::factory()->create(['employee_id' => $employee->id]);
            $drivers->push($driver);
        }
        // Create more driver employees if needed
        $remainingDrivers = 1000 - $drivers->count();
        if ($remainingDrivers > 0) {
            for ($i = 0; $i < $remainingDrivers; $i++) {
                $driverEmployee = Employee::factory()->create([
                    'type' => 'driver',
                    'office_id' => $offices->random()->id,
                    'department_id' => $departments->random()->id,
                    'position_id' => $positions->random()->id,
                ]);
                $driver = Driver::factory()->create(['employee_id' => $driverEmployee->id]);
                $drivers->push($driver);
                $employees->push($driverEmployee);
            }
        }
        $this->command->info('✓ Drivers seeded: ' . $drivers->count());
        
        // 7. Users (1000) - depends on employees
        $this->command->info('Seeding Users...');
        $users = collect();
        foreach ($employees->take(1000) as $employee) {
            $user = User::factory()->create([
                'employee_id' => $employee->id,
                'username' => fake()->unique()->userName(),
                'email' => fake()->unique()->safeEmail(),
            ]);
            $users->push($user);
        }
        $this->command->info('✓ Users seeded: ' . $users->count());
        
        // 8. Roles (1000)
        $this->command->info('Seeding Roles...');
        $roles = Role::factory()->count(1000)->create();
        $this->command->info('✓ Roles seeded: ' . $roles->count());
        
        // 9. Permissions (1000)
        $this->command->info('Seeding Permissions...');
        $permissions = Permission::factory()->count(1000)->create();
        $this->command->info('✓ Permissions seeded: ' . $permissions->count());
        
        // 10. Role-Permission pivot (1000)
        $this->command->info('Seeding Role-Permissions...');
        for ($i = 0; $i < 1000; $i++) {
            $role = $roles->random();
            $permission = $permissions->random();
            if (!$role->permissions()->where('permission_id', $permission->id)->exists()) {
                $role->permissions()->attach($permission->id);
            }
        }
        $this->command->info('✓ Role-Permissions seeded: 1000');
        
        // 11. User-Role pivot (1000)
        $this->command->info('Seeding User-Roles...');
        for ($i = 0; $i < 1000; $i++) {
            $user = $users->random();
            $role = $roles->random();
            if (!$user->roles()->where('role_id', $role->id)->exists()) {
                $user->roles()->attach($role->id);
            }
        }
        $this->command->info('✓ User-Roles seeded: 1000');
        
        // 12. Allowances (1000)
        $this->command->info('Seeding Allowances...');
        $allowances = Allowance::factory()->count(1000)->create();
        $this->command->info('✓ Allowances seeded: ' . $allowances->count());
        
        // 13. Deductions (1000)
        $this->command->info('Seeding Deductions...');
        $deductions = Deduction::factory()->count(1000)->create();
        $this->command->info('✓ Deductions seeded: ' . $deductions->count());
        
        // 14. Employee Allowances (1000)
        $this->command->info('Seeding Employee Allowances...');
        $employeeAllowances = collect();
        for ($i = 0; $i < 1000; $i++) {
            $employee = $employees->random();
            $allowance = $allowances->random();
            if (!$employee->employeeAllowances()->where('allowance_id', $allowance->id)->exists()) {
                $empAllowance = EmployeeAllowance::factory()->create([
                    'employee_id' => $employee->id,
                    'allowance_id' => $allowance->id,
                ]);
                $employeeAllowances->push($empAllowance);
            } else {
                $i--; // Retry
            }
        }
        $this->command->info('✓ Employee Allowances seeded: ' . $employeeAllowances->count());
        
        // 15. Employee Deductions (1000)
        $this->command->info('Seeding Employee Deductions...');
        $employeeDeductions = collect();
        for ($i = 0; $i < 1000; $i++) {
            $employee = $employees->random();
            $deduction = $deductions->random();
            if (!$employee->employeeDeductions()->where('deduction_id', $deduction->id)->exists()) {
                $empDeduction = EmployeeDeduction::factory()->create([
                    'employee_id' => $employee->id,
                    'deduction_id' => $deduction->id,
                ]);
                $employeeDeductions->push($empDeduction);
            } else {
                $i--; // Retry
            }
        }
        $this->command->info('✓ Employee Deductions seeded: ' . $employeeDeductions->count());
        
        // 16. Vehicles (1000) - depends on offices
        $this->command->info('Seeding Vehicles...');
        $vehicles = collect();
        for ($i = 0; $i < 1000; $i++) {
            $vehicle = Vehicle::factory()->create([
                'office_id' => $offices->random()->id,
            ]);
            $vehicles->push($vehicle);
        }
        $this->command->info('✓ Vehicles seeded: ' . $vehicles->count());
        
        // 17. Vehicle Assignments (1000) - depends on vehicles and drivers
        $this->command->info('Seeding Vehicle Assignments...');
        $vehicleAssignments = collect();
        $driverEmployees = $employees->where('type', 'driver');
        for ($i = 0; $i < 1000; $i++) {
            $vehicleAssignment = VehicleAssignment::factory()->create([
                'vehicle_id' => $vehicles->random()->id,
                'driver_id' => $driverEmployees->random()->id,
            ]);
            $vehicleAssignments->push($vehicleAssignment);
        }
        $this->command->info('✓ Vehicle Assignments seeded: ' . $vehicleAssignments->count());
        
        // 18. Vehicle Expenses (1000) - depends on vehicles and drivers
        $this->command->info('Seeding Vehicle Expenses...');
        $vehicleExpenses = collect();
        for ($i = 0; $i < 1000; $i++) {
            $vehicleExpense = VehicleExpense::factory()->create([
                'vehicle_id' => $vehicles->random()->id,
                'driver_id' => fake()->optional(0.8)->randomElement($driverEmployees->pluck('id')->toArray()),
            ]);
            $vehicleExpenses->push($vehicleExpense);
        }
        $this->command->info('✓ Vehicle Expenses seeded: ' . $vehicleExpenses->count());
        
        // 19. Customers (1000)
        $this->command->info('Seeding Customers...');
        $customers = Customer::factory()->count(1000)->create();
        $this->command->info('✓ Customers seeded: ' . $customers->count());
        
        // 20. Trips (1000) - depends on customers, drivers, vehicles
        $this->command->info('Seeding Trips...');
        $trips = collect();
        $driverEmployees = $employees->where('type', 'driver');
        for ($i = 0; $i < 1000; $i++) {
            $trip = Trip::factory()->create([
                'customer_id' => $customers->random()->id,
                'driver_id' => $driverEmployees->random()->id,
                'vehicle_id' => $vehicles->random()->id,
            ]);
            $trips->push($trip);
        }
        $this->command->info('✓ Trips seeded: ' . $trips->count());
        
        // 21. Invoices (1000) - depends on trips and customers
        $this->command->info('Seeding Invoices...');
        $invoices = collect();
        for ($i = 0; $i < 1000; $i++) {
            $invoice = Invoice::factory()->create([
                'trip_id' => fake()->optional(0.7)->randomElement($trips->pluck('id')->toArray()),
                'customer_id' => $customers->random()->id,
            ]);
            $invoices->push($invoice);
        }
        $this->command->info('✓ Invoices seeded: ' . $invoices->count());
        
        // 22. Trip Bonus Rules (1000)
        $this->command->info('Seeding Trip Bonus Rules...');
        $tripBonusRules = TripBonusRule::factory()->count(1000)->create();
        $this->command->info('✓ Trip Bonus Rules seeded: ' . $tripBonusRules->count());
        
        // 23. Payrolls (1000) - depends on companies
        $this->command->info('Seeding Payrolls...');
        $payrolls = collect();
        for ($i = 0; $i < 1000; $i++) {
            $payroll = Payroll::factory()->create([
                'company_id' => $companies->random()->id,
            ]);
            $payrolls->push($payroll);
        }
        $this->command->info('✓ Payrolls seeded: ' . $payrolls->count());
        
        // 24. Payroll Details (1000) - depends on payrolls and employees
        $this->command->info('Seeding Payroll Details...');
        $payrollDetails = collect();
        for ($i = 0; $i < 1000; $i++) {
            $payrollDetail = PayrollDetail::factory()->create([
                'payroll_id' => $payrolls->random()->id,
                'employee_id' => $employees->random()->id,
            ]);
            $payrollDetails->push($payrollDetail);
        }
        $this->command->info('✓ Payroll Details seeded: ' . $payrollDetails->count());
        
        // 25. Payroll Adjustments (1000) - depends on payroll details
        $this->command->info('Seeding Payroll Adjustments...');
        $payrollAdjustments = collect();
        for ($i = 0; $i < 1000; $i++) {
            $payrollAdjustment = PayrollAdjustment::factory()->create([
                'payroll_detail_id' => $payrollDetails->random()->id,
            ]);
            $payrollAdjustments->push($payrollAdjustment);
        }
        $this->command->info('✓ Payroll Adjustments seeded: ' . $payrollAdjustments->count());
        
        // 26. Attendances (1000) - depends on employees
        $this->command->info('Seeding Attendances...');
        $attendances = collect();
        for ($i = 0; $i < 1000; $i++) {
            $attendance = Attendance::factory()->create([
                'employee_id' => $employees->random()->id,
            ]);
            $attendances->push($attendance);
        }
        $this->command->info('✓ Attendances seeded: ' . $attendances->count());
        
        // 27. Login Logs (1000) - depends on users
        $this->command->info('Seeding Login Logs...');
        $loginLogs = collect();
        for ($i = 0; $i < 1000; $i++) {
            $loginLog = LoginLog::factory()->create([
                'user_id' => fake()->optional(0.9)->randomElement($users->pluck('id')->toArray()),
            ]);
            $loginLogs->push($loginLog);
        }
        $this->command->info('✓ Login Logs seeded: ' . $loginLogs->count());
        
        // 28. Audit Logs (1000) - depends on users
        $this->command->info('Seeding Audit Logs...');
        $auditLogs = collect();
        for ($i = 0; $i < 1000; $i++) {
            $auditLog = AuditLog::factory()->create([
                'user_id' => fake()->optional(0.8)->randomElement($users->pluck('id')->toArray()),
            ]);
            $auditLogs->push($auditLog);
        }
        $this->command->info('✓ Audit Logs seeded: ' . $auditLogs->count());
        
        // 29. Export Logs (1000) - depends on users
        $this->command->info('Seeding Export Logs...');
        $exportLogs = collect();
        for ($i = 0; $i < 1000; $i++) {
            $exportLog = ExportLog::factory()->create([
                'user_id' => fake()->optional(0.9)->randomElement($users->pluck('id')->toArray()),
            ]);
            $exportLogs->push($exportLog);
        }
        $this->command->info('✓ Export Logs seeded: ' . $exportLogs->count());
        
        // 30. Report Caches (1000)
        $this->command->info('Seeding Report Caches...');
        $reportCaches = ReportCache::factory()->count(1000)->create();
        $this->command->info('✓ Report Caches seeded: ' . $reportCaches->count());
        
        $this->command->info('✓ All data seeded successfully!');
    }
}
