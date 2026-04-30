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
        // Ensure assignment range is valid.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE vehicle_assignments ADD CONSTRAINT chk_vehicle_assignments_dates CHECK (to_date IS NULL OR from_date <= to_date)');
            } catch (\Throwable) {
            }
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE vehicle_assignments ADD CONSTRAINT chk_vehicle_assignments_dates CHECK (to_date IS NULL OR from_date <= to_date)');
            } catch (\Throwable) {
            }
        }

        Schema::table('vehicle_assignments', function (Blueprint $table): void {
            $table->index(['company_id', 'from_date', 'to_date'], 'idx_va_company_date_range');
            $table->index(['driver_id', 'from_date', 'to_date'], 'idx_va_driver_date_range');
            $table->index(['vehicle_id', 'from_date', 'to_date'], 'idx_va_vehicle_date_range');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE vehicle_assignments DROP CHECK chk_vehicle_assignments_dates');
            } catch (\Throwable) {
            }
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER TABLE vehicle_assignments DROP CONSTRAINT IF EXISTS chk_vehicle_assignments_dates');
            } catch (\Throwable) {
            }
        }

        Schema::table('vehicle_assignments', function (Blueprint $table): void {
            try {
                $table->dropIndex('idx_va_company_date_range');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('idx_va_driver_date_range');
            } catch (\Throwable) {
            }
            try {
                $table->dropIndex('idx_va_vehicle_date_range');
            } catch (\Throwable) {
            }
        });
    }
};

