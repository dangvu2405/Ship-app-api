<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! $this->foreignKeyExists('invoices', 'invoices_trip_id_foreign')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('trip_id')
                    ->references('id')
                    ->on('trips')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && $this->foreignKeyExists('invoices', 'invoices_trip_id_foreign')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['trip_id']);
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
                if (($row->table ?? null) === 'trips') {
                    return true;
                }
            }

            return false;
        }

        return false;
    }
};
