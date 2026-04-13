<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add employee personal fields to drivers table
        Schema::table('drivers', function (Blueprint $table): void {
            $table->string('code', 50)->unique()->after('id');
            $table->string('name')->after('code');
            $table->string('email')->unique()->nullable()->after('name');
            $table->string('phone', 20)->nullable()->after('email');
            $table->date('dob')->nullable()->after('phone');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('dob');
            $table->text('address')->nullable()->after('gender');
            $table->string('avatar_url')->nullable()->after('address');
            $table->string('national_id_no', 30)->nullable()->after('avatar_url');
            $table->date('national_id_issue_date')->nullable()->after('national_id_no');
            $table->string('national_id_issue_place')->nullable()->after('national_id_issue_date');
            $table->string('social_insurance_no', 30)->nullable()->after('national_id_issue_place');
            $table->string('health_insurance_no', 30)->nullable()->after('social_insurance_no');
            $table->date('insurance_registered_at')->nullable()->after('health_insurance_no');
            $table->unsignedBigInteger('office_id')->after('insurance_registered_at');
            $table->unsignedBigInteger('department_id')->nullable()->after('office_id');
            $table->unsignedBigInteger('position_id')->after('department_id');
            $table->enum('status', ['active', 'inactive', 'resigned'])->default('active')->after('available_status');
            $table->date('join_date')->nullable()->after('status');
            $table->date('resign_date')->nullable()->after('join_date');
            $table->string('bank_name')->nullable()->after('resign_date');
            $table->string('bank_account_no', 50)->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account_no');

            $table->index('code');
            $table->index(['office_id', 'status']);
            $table->index('department_id');
            $table->index('social_insurance_no');
            $table->index('health_insurance_no');
            $table->index('national_id_no');
        });

        // Step 2–3: Migrate employees → drivers and repoint FKs (MySQL + SQLite)
        if (Schema::hasTable('employees')) {
            $this->copyEmployeeFieldsOntoDrivers();
            $this->repointUsersEmployeeIdToDriverPk();
            $this->repointTripsDriverIdToDriverPk();
            $this->repointVehicleAssignmentsDriverIdToDriverPk();
            $this->repointVehicleExpensesDriverIdToDriverPk();
            $this->repointOfficesManagerIdToDriverPk();
        }

        // Step 4: Drop employee_id from drivers (no longer needed)
        $this->dropForeignKeySafe('drivers', 'drivers_employee_id_foreign');
        Schema::table('drivers', function (Blueprint $table): void {
            try {
                $table->dropUnique(['employee_id']);
            } catch (\Throwable) {
                // Index may already be absent depending on driver / migration path
            }
            $table->dropColumn('employee_id');
        });

        // Add proper FKs for office/department/position on drivers
        Schema::table('drivers', function (Blueprint $table): void {
            $table->foreign('office_id')->references('id')->on('offices')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
            $table->foreign('position_id')->references('id')->on('positions')->restrictOnDelete();
        });

        // Step 5: Drop removed module tables (disable FK checks to avoid ordering issues)
        Schema::disableForeignKeyConstraints();

        $tablesToDrop = [
            'payslips',
            'payroll_deductions',
            'payroll_earnings',
            'payroll_status_histories',
            'payroll_adjustments',
            'payroll_details',
            'payrolls',
            'payroll_periods',
            'employee_salary_configs',
            'attendance_summaries',
            'attendances',
            'employee_allowances',
            'employee_deductions',
            'allowances',
            'deductions',
            'leave_requests',
            'leave_balances',
            'leave_types',
            'employees',
        ];

        foreach ($tablesToDrop as $t) {
            Schema::dropIfExists($t);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // This migration is not reversible in a meaningful way.
        // Run migrate:fresh to restore the original schema.
    }

    private function copyEmployeeFieldsOntoDrivers(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("
                UPDATE drivers
                INNER JOIN employees ON employees.id = drivers.employee_id
                SET
                    drivers.code = employees.code,
                    drivers.name = employees.name,
                    drivers.email = employees.email,
                    drivers.phone = employees.phone,
                    drivers.dob = employees.dob,
                    drivers.gender = employees.gender,
                    drivers.address = employees.address,
                    drivers.avatar_url = employees.avatar_url,
                    drivers.national_id_no = employees.national_id_no,
                    drivers.national_id_issue_date = employees.national_id_issue_date,
                    drivers.national_id_issue_place = employees.national_id_issue_place,
                    drivers.social_insurance_no = employees.social_insurance_no,
                    drivers.health_insurance_no = employees.health_insurance_no,
                    drivers.insurance_registered_at = employees.insurance_registered_at,
                    drivers.office_id = employees.office_id,
                    drivers.department_id = employees.department_id,
                    drivers.position_id = employees.position_id,
                    drivers.status = employees.status,
                    drivers.join_date = employees.join_date,
                    drivers.resign_date = employees.resign_date,
                    drivers.bank_name = employees.bank_name,
                    drivers.bank_account_no = employees.bank_account_no,
                    drivers.bank_account_name = employees.bank_account_name
            ");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement('
                UPDATE drivers AS d
                SET
                    code = e.code,
                    name = e.name,
                    email = e.email,
                    phone = e.phone,
                    dob = e.dob,
                    gender = e.gender,
                    address = e.address,
                    avatar_url = e.avatar_url,
                    national_id_no = e.national_id_no,
                    national_id_issue_date = e.national_id_issue_date,
                    national_id_issue_place = e.national_id_issue_place,
                    social_insurance_no = e.social_insurance_no,
                    health_insurance_no = e.health_insurance_no,
                    insurance_registered_at = e.insurance_registered_at,
                    office_id = e.office_id,
                    department_id = e.department_id,
                    position_id = e.position_id,
                    status = e.status,
                    join_date = e.join_date,
                    resign_date = e.resign_date,
                    bank_name = e.bank_name,
                    bank_account_no = e.bank_account_no,
                    bank_account_name = e.bank_account_name
                FROM employees AS e
                WHERE e.id = d.employee_id
            ');
        }
    }

    private function repointUsersEmployeeIdToDriverPk(): void
    {
        if (! Schema::hasColumn('users', 'employee_id')) {
            return;
        }

        $this->dropForeignKeySafe('users', 'users_employee_id_foreign');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE users
                INNER JOIN drivers ON drivers.employee_id = users.employee_id
                SET users.employee_id = drivers.id
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                UPDATE users AS u
                SET employee_id = d.id
                FROM drivers AS d
                WHERE d.employee_id = u.employee_id
            ');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('employee_id', 'driver_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    private function repointTripsDriverIdToDriverPk(): void
    {
        $this->dropForeignKeySafe('trips', 'trips_driver_id_foreign');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE trips
                INNER JOIN drivers ON drivers.employee_id = trips.driver_id
                SET trips.driver_id = drivers.id
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                UPDATE trips AS t
                SET driver_id = d.id
                FROM drivers AS d
                WHERE d.employee_id = t.driver_id
            ');
        }

        Schema::table('trips', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('drivers')->restrictOnDelete();
        });
    }

    private function repointVehicleAssignmentsDriverIdToDriverPk(): void
    {
        $this->dropForeignKeySafe('vehicle_assignments', 'vehicle_assignments_driver_id_foreign');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE vehicle_assignments
                INNER JOIN drivers ON drivers.employee_id = vehicle_assignments.driver_id
                SET vehicle_assignments.driver_id = drivers.id
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                UPDATE vehicle_assignments AS va
                SET driver_id = d.id
                FROM drivers AS d
                WHERE d.employee_id = va.driver_id
            ');
        }

        Schema::table('vehicle_assignments', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('drivers')->cascadeOnDelete();
        });
    }

    private function repointVehicleExpensesDriverIdToDriverPk(): void
    {
        $this->dropForeignKeySafe('vehicle_expenses', 'vehicle_expenses_driver_id_foreign');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE vehicle_expenses
                INNER JOIN drivers ON drivers.employee_id = vehicle_expenses.driver_id
                SET vehicle_expenses.driver_id = drivers.id
                WHERE vehicle_expenses.driver_id IS NOT NULL
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                UPDATE vehicle_expenses AS ve
                SET driver_id = d.id
                FROM drivers AS d
                WHERE ve.driver_id IS NOT NULL AND d.employee_id = ve.driver_id
            ');
        }

        Schema::table('vehicle_expenses', function (Blueprint $table): void {
            $table->foreign('driver_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    private function repointOfficesManagerIdToDriverPk(): void
    {
        $this->dropForeignKeySafe('offices', 'offices_manager_id_foreign');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('
                UPDATE offices
                INNER JOIN drivers ON drivers.employee_id = offices.manager_id
                SET offices.manager_id = drivers.id
                WHERE offices.manager_id IS NOT NULL
            ');
        } elseif ($driver === 'sqlite') {
            DB::statement('
                UPDATE offices AS o
                SET manager_id = d.id
                FROM drivers AS d
                WHERE o.manager_id IS NOT NULL AND d.employee_id = o.manager_id
            ');
        }

        Schema::table('offices', function (Blueprint $table): void {
            $table->foreign('manager_id')->references('id')->on('drivers')->nullOnDelete();
        });
    }

    private function dropForeignKeySafe(string $table, string $constraintName): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->dropSqliteForeignKeySafe($table, $constraintName);

            return;
        }

        try {
            $db = DB::getDatabaseName();
            $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraintName)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();

            if ($exists) {
                Schema::table($table, function (Blueprint $t) use ($constraintName): void {
                    $t->dropForeign($constraintName);
                });
            }
        } catch (\Throwable) {
            // Ignore if FK doesn't exist
        }
    }

    /**
     * Laravel names FKs `{table}_{column}_foreign`. SQLite needs an explicit drop before column changes.
     */
    private function dropSqliteForeignKeySafe(string $table, string $constraintName): void
    {
        $prefix = $table.'_';
        $suffix = '_foreign';
        if (! str_starts_with($constraintName, $prefix) || ! str_ends_with($constraintName, $suffix)) {
            return;
        }

        $column = substr($constraintName, strlen($prefix), -strlen($suffix));
        if ($column === '' || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $t) use ($column): void {
                $t->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // Ignore if FK doesn't exist
        }
    }
};
