<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $dbDriver = DB::getDriverName();

        if (Schema::hasColumn('users', 'driver_id')) {
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['driver_id']);
                });
            } catch (\Throwable) {
            }
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('driver_id');
            });
        }

        if (Schema::hasColumn('drivers', 'user_id')) {
            try {
                Schema::table('drivers', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('drivers', function (Blueprint $table) {
                    $table->dropUnique(['user_id']);
                });
            } catch (\Throwable) {
            }
            Schema::table('drivers', function (Blueprint $table) {
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
};
