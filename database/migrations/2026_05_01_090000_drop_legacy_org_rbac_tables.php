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
        if (app()->runningUnitTests() || Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $this->dropForeignKeySafe('drivers', 'drivers_office_id_foreign');
        $this->dropForeignKeySafe('drivers', 'drivers_department_id_foreign');
        $this->dropForeignKeySafe('drivers', 'drivers_position_id_foreign');

        $this->dropForeignKeySafe('vehicles', 'vehicles_office_id_foreign');

        $this->dropForeignKeySafe('driver_work_schedules', 'driver_work_schedules_office_id_foreign');

        $this->dropForeignKeySafe('user_roles', 'user_roles_office_id_foreign');
        $this->dropForeignKeySafe('offices', 'offices_manager_id_foreign');
        $this->dropForeignKeySafe('departments', 'departments_office_id_foreign');
        $this->dropForeignKeySafe('departments', 'departments_parent_id_foreign');

        $this->dropDriverOrgColumns();
        $this->dropVehicleOfficeColumn();
        $this->dropDriverWorkScheduleOfficeColumn();

        Schema::disableForeignKeyConstraints();

        $tablesToDrop = [
            'user_roles',
            'role_permissions',
            'permissions',
            'roles',
            'departments',
            'positions',
            'offices',
            'login_logs',
            'export_logs',
            'refresh_tokens',
            'user_companies',
        ];

        foreach ($tablesToDrop as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Intentionally empty. Restoring dropped tables requires a full reseed.
    }

    private function dropDriverOrgColumns(): void
    {
        if (! Schema::hasTable('drivers')) {
            return;
        }

        $this->dropIndexSafe('drivers', 'drivers_office_id_status_index');
        $this->dropIndexSafe('drivers', 'drivers_department_id_index');

        $toDrop = [];
        foreach (['office_id', 'department_id', 'position_id'] as $column) {
            if (Schema::hasColumn('drivers', $column)) {
                $toDrop[] = $column;
            }
        }

        if ($toDrop !== []) {
            Schema::table('drivers', function (Blueprint $table) use ($toDrop): void {
                $table->dropColumn($toDrop);
            });
        }
    }

    private function dropVehicleOfficeColumn(): void
    {
        if (! Schema::hasTable('vehicles')) {
            return;
        }

        $this->dropIndexSafe('vehicles', 'vehicles_office_id_status_index');

        if (Schema::hasColumn('vehicles', 'office_id')) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->dropColumn('office_id');
            });
        }
    }

    private function dropDriverWorkScheduleOfficeColumn(): void
    {
        if (! Schema::hasTable('driver_work_schedules')) {
            return;
        }

        $this->dropIndexSafe('driver_work_schedules', 'dws_office_date_status_idx');

        if (Schema::hasColumn('driver_work_schedules', 'office_id')) {
            Schema::table('driver_work_schedules', function (Blueprint $table): void {
                $table->dropColumn('office_id');
            });
        }
    }

    private function dropIndexSafe(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $t) use ($indexName): void {
                $t->dropIndex($indexName);
            });
        } catch (\Throwable) {
            // Ignore if index does not exist.
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $db = DB::getDatabaseName();

            return DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA index_list('{$table}')");
            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        return false;
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
            // Ignore if FK does not exist.
        }
    }

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
            // Ignore if FK does not exist.
        }
    }
};
