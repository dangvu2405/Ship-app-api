<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->index(['employee_id', 'status', 'date'], 'idx_att_emp_status_date');
        });

        Schema::table('trips', function (Blueprint $table): void {
            $table->index(['driver_id', 'status', 'start_time'], 'idx_trips_driver_status_start');
            $table->index(['vehicle_id', 'status', 'start_time'], 'idx_trips_vehicle_status_start');
        });

        Schema::table('vehicle_expenses', function (Blueprint $table): void {
            $table->index(['vehicle_id', 'expense_date', 'type'], 'idx_vehicle_exp_vehicle_date_type');
        });

        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->index(['employee_id', 'payroll_id'], 'idx_payroll_details_emp_payroll');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_details', function (Blueprint $table): void {
            $table->dropIndex('idx_payroll_details_emp_payroll');
        });

        Schema::table('vehicle_expenses', function (Blueprint $table): void {
            $table->dropIndex('idx_vehicle_exp_vehicle_date_type');
        });

        Schema::table('trips', function (Blueprint $table): void {
            $table->dropIndex('idx_trips_driver_status_start');
            $table->dropIndex('idx_trips_vehicle_status_start');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('idx_att_emp_status_date');
        });
    }
};
