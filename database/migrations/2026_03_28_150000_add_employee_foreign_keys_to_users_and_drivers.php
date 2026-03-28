<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! $this->foreignKeyExists('users', 'users_employee_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('drivers') && ! $this->foreignKeyExists('drivers', 'drivers_employee_id_foreign')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->foreign('employee_id')
                    ->references('id')
                    ->on('employees')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && $this->foreignKeyExists('users', 'users_employee_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        }

        if (Schema::hasTable('drivers') && $this->foreignKeyExists('drivers', 'drivers_employee_id_foreign')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });
        }
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $db = DB::getDatabaseName();

            return DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraintName)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        }

        if ($driver === 'sqlite') {
            $rows = DB::select('PRAGMA foreign_key_list('.$table.')');
            foreach ($rows as $row) {
                if (($row->table ?? null) === 'employees') {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
