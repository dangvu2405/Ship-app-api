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
        if (! Schema::hasColumn('drivers', 'company_id')) {
            Schema::table('drivers', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('office_id')->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        DB::statement('UPDATE drivers SET company_id = (SELECT company_id FROM offices WHERE offices.id = drivers.office_id) WHERE company_id IS NULL AND office_id IS NOT NULL');

        if (! Schema::hasColumn('vehicles', 'company_id')) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('office_id')->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        DB::statement('UPDATE vehicles SET company_id = (SELECT company_id FROM offices WHERE offices.id = vehicles.office_id) WHERE company_id IS NULL AND office_id IS NOT NULL');

        if (! Schema::hasColumn('trips', 'company_id')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('driver_id')->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'status']);
            });
        }

        DB::statement('UPDATE trips SET company_id = (SELECT company_id FROM drivers WHERE drivers.id = trips.driver_id) WHERE company_id IS NULL AND driver_id IS NOT NULL');
        DB::statement('UPDATE trips SET company_id = (SELECT company_id FROM vehicles WHERE vehicles.id = trips.vehicle_id) WHERE company_id IS NULL AND vehicle_id IS NOT NULL');

        if (! Schema::hasColumn('vehicle_assignments', 'company_id')) {
            Schema::table('vehicle_assignments', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('driver_id')->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'driver_id']);
                $table->index(['company_id', 'vehicle_id']);
            });
        }

        DB::statement('UPDATE vehicle_assignments SET company_id = (SELECT company_id FROM drivers WHERE drivers.id = vehicle_assignments.driver_id) WHERE company_id IS NULL AND driver_id IS NOT NULL');

        if (! Schema::hasColumn('vehicle_expenses', 'company_id')) {
            Schema::table('vehicle_expenses', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('driver_id')->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'expense_date']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement('UPDATE vehicle_expenses SET company_id = COALESCE(
                (SELECT company_id FROM drivers WHERE drivers.id = vehicle_expenses.driver_id),
                (SELECT company_id FROM vehicles WHERE vehicles.id = vehicle_expenses.vehicle_id)
            ) WHERE company_id IS NULL');
        } else {
            DB::statement('UPDATE vehicle_expenses ve SET company_id = COALESCE(
                (SELECT company_id FROM drivers WHERE drivers.id = ve.driver_id),
                (SELECT vehicles.company_id FROM vehicles WHERE vehicles.id = ve.vehicle_id)
            ) WHERE ve.company_id IS NULL');
        }

        if (! Schema::hasColumn('payroll_lines', 'company_id')) {
            Schema::table('payroll_lines', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('payroll_id')->constrained()->cascadeOnDelete();
                $table->index(['company_id', 'driver_id']);
            });
        }

        DB::statement('UPDATE payroll_lines SET company_id = (SELECT company_id FROM payrolls WHERE payrolls.id = payroll_lines.payroll_id) WHERE company_id IS NULL');

        $this->assertNoNullCompanyIds('drivers');
        $this->assertNoNullCompanyIds('vehicles');
        $this->assertNoNullCompanyIds('trips');
        $this->assertNoNullCompanyIds('vehicle_assignments');
        $this->assertNoNullCompanyIds('vehicle_expenses');
        $this->assertNoNullCompanyIds('payroll_lines');

        $this->setCompanyIdNotNull('drivers');
        $this->setCompanyIdNotNull('vehicles');
        $this->setCompanyIdNotNull('trips');
        $this->setCompanyIdNotNull('vehicle_assignments');
        $this->setCompanyIdNotNull('vehicle_expenses');
        $this->setCompanyIdNotNull('payroll_lines');
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_lines', 'company_id')) {
            Schema::table('payroll_lines', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('vehicle_expenses', 'company_id')) {
            Schema::table('vehicle_expenses', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('vehicle_assignments', 'company_id')) {
            Schema::table('vehicle_assignments', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('trips', 'company_id')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('vehicles', 'company_id')) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }

        if (Schema::hasColumn('drivers', 'company_id')) {
            Schema::table('drivers', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }

    private function assertNoNullCompanyIds(string $table): void
    {
        if (DB::table($table)->whereNull('company_id')->exists()) {
            throw new \RuntimeException("SOC2 denormalize migration: {$table}.company_id still has NULL after backfill.");
        }
    }

    private function setCompanyIdNotNull(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `{$table}` MODIFY `company_id` BIGINT UNSIGNED NOT NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN company_id SET NOT NULL");
        } else {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('company_id')->nullable(false)->change();
            });
        }
    }
};
