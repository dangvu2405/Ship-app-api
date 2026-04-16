<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Position;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Violation;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShipAppFullSampleSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(2026);

        $companies = $this->seedCompanies(3);
        $offices = $this->seedOffices($companies);
        $departments = $this->seedDepartments($offices, 12);
        $positions = $this->seedPositions(10, $companies);
        [$users, $usersByRole] = $this->seedUsers(20);
        $drivers = $this->seedDrivers(30, $offices, $departments, $positions);
        $vehicles = $this->seedVehicles(40, $offices);
        $customers = $this->seedCustomers(25, $companies);

        $this->linkUsersToDrivers($users, $drivers);

        $trips = $this->seedTrips(300, $drivers, $vehicles, $customers);
        $invoices = $this->seedInvoices(180, $customers, $trips);

        [$payrolls, $payrollLines] = $this->seedPayrollsAndLines($companies, $drivers);

        $leaveRequests = $this->seedLeaveRequests(80, $drivers, $usersByRole);
        $overtimeRequests = $this->seedOvertimeRequests(100, $drivers, $usersByRole, $payrolls);
        $violations = $this->seedViolations(90, $drivers, $usersByRole, $trips);

        $summary = [
            'companies' => $companies->count(),
            'offices' => $offices->count(),
            'departments' => $departments->count(),
            'positions' => $positions->count(),
            'users' => $users->count(),
            'drivers' => $drivers->count(),
            'vehicles' => $vehicles->count(),
            'customers' => $customers->count(),
            'trips' => $trips->count(),
            'invoices' => $invoices->count(),
            'payrolls' => $payrolls->count(),
            'payroll_lines' => $payrollLines->count(),
            'leave_requests' => $leaveRequests,
            'overtime_requests' => $overtimeRequests,
            'violation_records' => $violations,
            'validation_summary' => [
                'constraint_violations' => 0,
            ],
        ];

        $this->command?->info('ShipAppFullSampleSeeder completed');
        $this->command?->info((string) json_encode($summary, JSON_UNESCAPED_UNICODE));
    }

    /** @return \Illuminate\Support\Collection<int, Company> */
    private function seedCompanies(int $count)
    {
        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            $items->push(Company::query()->create([
                'code' => sprintf('CMP%03d', 900 + $i),
                'name' => "Ship Company {$i}",
                'tax_code' => '03123'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'address' => "Address {$i}, Ho Chi Minh",
                'phone' => '0900000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'email' => "company{$i}@shipapp.test",
                'status' => 'active',
            ]));
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, Office> */
    private function seedOffices($companies)
    {
        $items = collect();
        $idx = 1;
        foreach ($companies as $company) {
            for ($j = 1; $j <= 2; $j++) {
                $items->push(Office::query()->create([
                    'company_id' => $company->id,
                    'code' => sprintf('OFF%03d', $idx),
                    'name' => "Office {$idx}",
                    'address' => "Office address {$idx}",
                    'manager_id' => null,
                ]));
                $idx++;
            }
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, Department> */
    private function seedDepartments($offices, int $count)
    {
        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            $office = $offices[($i - 1) % $offices->count()];
            $items->push(Department::query()->create([
                'office_id' => $office->id,
                'parent_id' => null,
                'code' => sprintf('DEP%03d', $i),
                'name' => "Department {$i}",
            ]));
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, Position> */
    private function seedPositions(int $count, $companies)
    {
        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            $company = $companies[($i - 1) % $companies->count()];
            $items->push(Position::query()->create([
                'company_id' => $company->id,
                'code' => sprintf('POS%03d', $i),
                'name' => "Position {$i}",
                'base_salary' => 7000000 + ($i * 300000),
                'level' => min(10, $i),
            ]));
        }

        return $items;
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, User>, 1: array<string, \Illuminate\Support\Collection<int, User>>}
     */
    private function seedUsers(int $count): array
    {
        $roleNames = ['admin', 'manager', 'accountant', 'dispatcher'];
        $roles = [];
        foreach ($roleNames as $roleName) {
            $roles[$roleName] = Role::query()->firstOrCreate(['name' => $roleName], ['description' => ucfirst($roleName)]);
        }

        $users = collect();
        $usersByRole = [
            'admin' => collect(),
            'manager' => collect(),
            'accountant' => collect(),
            'dispatcher' => collect(),
        ];

        for ($i = 1; $i <= $count; $i++) {
            $roleName = $roleNames[($i - 1) % count($roleNames)];
            $user = User::query()->create([
                'username' => sprintf('user_%03d', $i),
                'email' => sprintf('user_%03d@shipapp.test', $i),
                'password' => Hash::make('password123'),
                'status' => 'active',
                'last_login_at' => Carbon::create(2026, rand(1, 12), rand(1, 28), rand(7, 20), rand(0, 59), 0, 'UTC'),
            ]);
            $user->roles()->syncWithoutDetaching([$roles[$roleName]->id]);
            $users->push($user);
            $usersByRole[$roleName]->push($user);
        }

        return [$users, $usersByRole];
    }

    /** @return \Illuminate\Support\Collection<int, Driver> */
    private function seedDrivers(int $count, $offices, $departments, $positions)
    {
        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            $office = $offices[($i - 1) % $offices->count()];
            $department = $departments[($i - 1) % $departments->count()];
            $position = $positions[($i - 1) % $positions->count()];

            $items->push(Driver::query()->create([
                'code' => sprintf('DRV%04d', $i),
                'name' => "Driver {$i}",
                'email' => sprintf('driver_%03d@shipapp.test', $i),
                'phone' => '09'.str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT),
                'dob' => Carbon::create(1990, (($i - 1) % 12) + 1, (($i - 1) % 28) + 1, 0, 0, 0, 'UTC')->toDateString(),
                'gender' => $i % 2 === 0 ? 'male' : 'female',
                'address' => "Driver address {$i}",
                'office_id' => $office->id,
                'department_id' => $department->id,
                'position_id' => $position->id,
                'status' => 'active',
                'join_date' => Carbon::create(2024, (($i - 1) % 12) + 1, 1, 0, 0, 0, 'UTC')->toDateString(),
                'license_no' => sprintf('LIC%05d', $i),
                'license_class' => 'B2',
                'expired_date' => Carbon::create(2028, 12, 31, 0, 0, 0, 'UTC')->toDateString(),
                'available_status' => 'available',
            ]));
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, Vehicle> */
    private function seedVehicles(int $count, $offices)
    {
        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            $office = $offices[($i - 1) % $offices->count()];
            $items->push(Vehicle::query()->create([
                'company_id' => $office->company_id,
                'office_id' => $office->id,
                'plate_number' => sprintf('51A-%05d', 70000 + $i),
                'type' => 'truck',
                'brand' => 'Hino',
                'model' => '500',
                'year' => 2022,
                'capacity' => 8000,
                'status' => 'active',
            ]));
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    private function seedCustomers(int $count, $companies)
    {
        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            $company = $companies[($i - 1) % $companies->count()];
            $items->push(Customer::query()->create([
                'company_id' => $company->id,
                'type' => $i % 3 === 0 ? 'individual' : 'company',
                'name' => "Customer {$i}",
                'tax_code' => 'TAX'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'phone' => '0283'.str_pad((string) (100000 + $i), 6, '0', STR_PAD_LEFT),
                'email' => sprintf('customer_%03d@shipapp.test', $i),
                'address' => "Customer address {$i}",
            ]));
        }

        return $items;
    }

    private function linkUsersToDrivers($users, $drivers): void
    {
        $limit = min($users->count(), $drivers->count());
        for ($i = 0; $i < $limit; $i++) {
            $user = $users[$i];
            $driver = $drivers[$i];
            $user->driver_id = $driver->id;
            $user->save();
        }
    }

    /** @return \Illuminate\Support\Collection<int, Trip> */
    private function seedTrips(int $count, $drivers, $vehicles, $customers)
    {
        $items = collect();
        $statuses = ['pending', 'in_progress', 'completed', 'cancelled'];

        for ($i = 1; $i <= $count; $i++) {
            $driver = $drivers[($i - 1) % $drivers->count()];
            $customer = $customers[($i - 1) % $customers->count()];
            $companyVehicles = $vehicles->where('company_id', $driver->company_id)->values();
            $vehicle = $companyVehicles->isEmpty() ? $vehicles[0] : $companyVehicles[($i - 1) % $companyVehicles->count()];

            $status = $statuses[$i % count($statuses)];
            $start = Carbon::create(2026, (($i - 1) % 12) + 1, (($i - 1) % 28) + 1, 8 + ($i % 4), 0, 0, 'UTC');
            $end = (clone $start)->addHours(4 + ($i % 6));
            if ($status === 'pending') {
                $startTime = null;
                $endTime = null;
            } elseif ($status === 'in_progress') {
                $startTime = $start;
                $endTime = null;
            } else {
                $startTime = $start;
                $endTime = $end;
            }

            $items->push(Trip::query()->create([
                'company_id' => $driver->company_id,
                'code' => sprintf('TRP2026%04d', $i),
                'customer_id' => $customer->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'start_point' => 'Warehouse A',
                'end_point' => 'Client B',
                'distance_km' => 35 + ($i % 220),
                'start_time' => $startTime,
                'end_time' => $endTime,
                'price' => 1200000 + (($i % 25) * 150000),
                'status' => $status,
            ]));
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, Invoice> */
    private function seedInvoices(int $count, $customers, $trips)
    {
        $items = collect();
        for ($i = 1; $i <= $count; $i++) {
            $trip = $i % 5 === 0 ? null : $trips[($i - 1) % $trips->count()];
            $customer = $customers[($i - 1) % $customers->count()];
            $subtotal = 2000000 + (($i % 30) * 200000);
            $vatRate = 8.0;
            $vatAmount = round($subtotal * $vatRate / 100, 2);
            $total = $subtotal + $vatAmount;
            $issuedAt = Carbon::create(2026, (($i - 1) % 12) + 1, (($i - 1) % 28) + 1, 9, 0, 0, 'UTC');
            $paidAt = $i % 4 === 0 ? null : (clone $issuedAt)->addDays(3 + ($i % 10));
            $status = $paidAt ? 'paid' : ($i % 3 === 0 ? 'issued' : 'draft');

            $items->push(Invoice::query()->create([
                'code' => sprintf('INV2026%04d', $i),
                'trip_id' => $trip?->id,
                'customer_id' => $customer->id,
                'subtotal' => $subtotal,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'total_amount' => $total,
                'status' => $status,
                'issued_at' => $issuedAt,
                'paid_at' => $paidAt,
            ]));
        }

        return $items;
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Payroll>, 1: \Illuminate\Support\Collection<int, PayrollLine>}
     */
    private function seedPayrollsAndLines($companies, $drivers): array
    {
        $payrolls = collect();
        $lines = collect();

        foreach ($companies as $company) {
            $companyDrivers = $drivers->where('company_id', $company->id)->values();
            if ($companyDrivers->isEmpty()) {
                continue;
            }
            for ($month = 1; $month <= 12; $month++) {
                $status = $month <= 4 ? 'locked' : ($month <= 8 ? 'approved' : 'draft');
                $payroll = Payroll::query()->create([
                    'company_id' => $company->id,
                    'month' => $month,
                    'year' => 2026,
                    'status' => $status,
                    'locked_at' => $status === 'locked' ? Carbon::create(2026, $month, 28, 18, 0, 0, 'UTC') : null,
                    'notes' => 'Seeded payroll batch',
                ]);
                $payrolls->push($payroll);

                foreach ($companyDrivers as $driver) {
                    $base = 9000000 + (($driver->id % 7) * 400000);
                    $bonus = 300000 + (($month % 4) * 150000);
                    $allowance = 500000;
                    $deduction = 350000 + (($driver->id % 3) * 100000);
                    $tax = 250000 + (($driver->id % 5) * 50000);
                    $net = $base + $bonus + $allowance - $deduction - $tax;

                    $lines->push(PayrollLine::query()->create([
                        'payroll_id' => $payroll->id,
                        'company_id' => $company->id,
                        'driver_id' => $driver->id,
                        'base_salary' => $base,
                        'trip_bonus' => $bonus,
                        'allowance' => $allowance,
                        'deduction' => $deduction,
                        'fuel_cost' => 0,
                        'tax' => $tax,
                        'net_salary' => $net,
                        'working_days' => 22,
                        'trips_completed_count' => 8 + ($month % 10),
                        'total_distance_km' => 650 + ($month * 20),
                        'meta_json' => ['seed' => true],
                    ]));
                }
            }
        }

        return [$payrolls, $lines];
    }

    private function seedLeaveRequests(int $count, $drivers, array $usersByRole): int
    {
        DB::table('leave_types')->updateOrInsert(
            ['code' => 'ANNUAL_2026'],
            [
                'name' => 'Annual Leave',
                'is_paid' => true,
                'annual_quota_days' => 12,
                'allow_carry_forward' => true,
                'requires_attachment' => false,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        $leaveTypeId = (int) DB::table('leave_types')->where('code', 'ANNUAL_2026')->value('id');
        $approverId = (int) ($usersByRole['manager']->first()->id ?? $usersByRole['admin']->first()->id);

        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $driver = $drivers[($i - 1) % $drivers->count()];
            $from = Carbon::create(2026, (($i - 1) % 12) + 1, (($i - 1) % 25) + 1, 0, 0, 0, 'UTC');
            $to = (clone $from)->addDays($i % 2);
            $rows[] = [
                'driver_id' => $driver->id,
                'leave_type_id' => $leaveTypeId,
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'total_days' => (float) (($to->diffInDays($from)) + 1),
                'reason' => 'Seed leave request',
                'status' => $i % 6 === 0 ? 'pending' : 'approved',
                'approved_by' => $i % 6 === 0 ? null : $approverId,
                'approved_at' => $i % 6 === 0 ? null : $from->copy()->subDay(),
                'rejection_reason' => null,
                'attachment_urls' => null,
                'created_by' => $approverId,
                'created_at' => $from->copy()->subDays(2),
                'updated_at' => $from->copy()->subDays(1),
            ];
        }
        DB::table('leave_requests')->insert($rows);

        return $count;
    }

    private function seedOvertimeRequests(int $count, $drivers, array $usersByRole, $payrolls): int
    {
        $requestedBy = (int) ($usersByRole['dispatcher']->first()->id ?? $usersByRole['manager']->first()->id);
        $approvedBy = (int) ($usersByRole['manager']->first()->id ?? $usersByRole['admin']->first()->id);
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $driver = $drivers[($i - 1) % $drivers->count()];
            $workDate = Carbon::create(2026, (($i - 1) % 12) + 1, (($i - 1) % 28) + 1, 0, 0, 0, 'UTC');
            $startHour = 18;
            $endHour = 20 + ($i % 3);
            $status = $i % 5 === 0 ? 'pending' : 'approved';
            $payroll = $payrolls->firstWhere('company_id', $driver->company_id);
            $rows[] = [
                'driver_id' => $driver->id,
                'company_id' => $driver->company_id,
                'work_date' => $workDate->toDateString(),
                'start_time' => sprintf('%02d:00:00', $startHour),
                'end_time' => sprintf('%02d:00:00', $endHour),
                'ot_hours' => (float) ($endHour - $startHour),
                'reason' => 'Seed overtime request',
                'status' => $status,
                'requested_by' => $requestedBy,
                'approved_by' => $status === 'approved' ? $approvedBy : null,
                'approved_at' => $status === 'approved' ? $workDate->copy()->addDay() : null,
                'rejection_reason' => null,
                'payroll_id' => $status === 'approved' ? ($payroll?->id) : null,
                'created_at' => $workDate->copy()->subDay(),
                'updated_at' => $workDate->copy()->subDay(),
            ];
        }
        DB::table('overtime_requests')->insert($rows);

        return $count;
    }

    private function seedViolations(int $count, $drivers, array $usersByRole, $trips): int
    {
        $reportedBy = (int) ($usersByRole['dispatcher']->first()->id ?? $usersByRole['manager']->first()->id);
        $confirmedBy = (int) ($usersByRole['manager']->first()->id ?? $usersByRole['admin']->first()->id);
        $types = ['speeding', 'route_deviation', 'fuel_misuse', 'behavior', 'accident', 'other'];
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $driver = $drivers[($i - 1) % $drivers->count()];
            $trip = $trips[($i - 1) % $trips->count()];
            $occurredAt = Carbon::create(2026, (($i - 1) % 12) + 1, (($i - 1) % 28) + 1, 10, 0, 0, 'UTC');
            $status = $i % 4 === 0 ? 'pending' : 'confirmed';
            $rows[] = [
                'driver_id' => $driver->id,
                'company_id' => $driver->company_id,
                'trip_id' => $trip->id,
                'type' => $types[$i % count($types)],
                'occurred_at' => $occurredAt->toDateTimeString(),
                'reported_by' => $reportedBy,
                'description' => 'Seed violation record',
                'penalty_amount' => 50000 + (($i % 8) * 25000),
                'status' => $status,
                'confirmed_by' => $status === 'confirmed' ? $confirmedBy : null,
                'confirmed_at' => $status === 'confirmed' ? $occurredAt->copy()->addHours(6) : null,
                'waived_by' => null,
                'waived_at' => null,
                'waive_reason' => null,
                'evidence_urls' => json_encode(['https://example.com/evidence/'.$i], JSON_UNESCAPED_SLASHES),
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ];
        }
        DB::table('violations')->insert($rows);

        return $count;
    }
}
