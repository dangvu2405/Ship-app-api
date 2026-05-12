<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'driver_id')) {
            Schema::table('users', function (Blueprint $table) {
                if ($this->foreignKeyExists('users', 'users_driver_id_foreign')) {
                    $table->dropForeign(['driver_id']);
                }
                $table->dropColumn('driver_id');
            });
        }

        if (Schema::hasColumn('drivers', 'user_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                if ($this->foreignKeyExists('drivers', 'drivers_user_id_foreign')) {
                    $table->dropForeign(['user_id']);
                }
                
                // Drop unique index if exists
                if ($this->indexExists('drivers', 'drivers_user_id_unique')) {
                    $table->dropUnique(['user_id']);
                }
                
                $table->dropColumn('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('status')->constrained('drivers')->nullOnDelete();
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->unique('user_id');
        });
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $driver = \Illuminate\Support\Facades\DB::getDriverName();

        if ($driver === 'mysql') {
            return \Illuminate\Support\Facades\DB::table('information_schema.TABLE_CONSTRAINTS')
                ->where('CONSTRAINT_SCHEMA', \Illuminate\Support\Facades\DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('CONSTRAINT_NAME', $constraint)
                ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
                ->exists();
        }

        return false;
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = \Illuminate\Support\Facades\DB::getDriverName();

        if ($driver === 'mysql') {
            return \Illuminate\Support\Facades\DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', \Illuminate\Support\Facades\DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->exists();
        }

        return false;
    }
};
