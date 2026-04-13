<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_lines', 'overtime_pay')) {
                $table->decimal('overtime_pay', 15, 2)->default(0)->after('trip_bonus');
            }
            if (! Schema::hasColumn('payroll_lines', 'night_shift_allowance')) {
                $table->decimal('night_shift_allowance', 15, 2)->default(0)->after('overtime_pay');
            }
            if (! Schema::hasColumn('payroll_lines', 'public_holiday_pay')) {
                $table->decimal('public_holiday_pay', 15, 2)->default(0)->after('night_shift_allowance');
            }
            if (! Schema::hasColumn('payroll_lines', 'leave_unpaid_deduction')) {
                $table->decimal('leave_unpaid_deduction', 15, 2)->default(0)->after('deduction');
            }
            if (! Schema::hasColumn('payroll_lines', 'violation_deduction')) {
                $table->decimal('violation_deduction', 15, 2)->default(0)->after('leave_unpaid_deduction');
            }
            if (! Schema::hasColumn('payroll_lines', 'leave_days_paid')) {
                $table->unsignedSmallInteger('leave_days_paid')->default(0)->after('working_days');
            }
            if (! Schema::hasColumn('payroll_lines', 'leave_days_unpaid')) {
                $table->unsignedSmallInteger('leave_days_unpaid')->default(0)->after('leave_days_paid');
            }
            if (! Schema::hasColumn('payroll_lines', 'overtime_hours')) {
                $table->decimal('overtime_hours', 8, 2)->default(0)->after('leave_days_unpaid');
            }
            if (! Schema::hasColumn('payroll_lines', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')
                    ->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table): void {
            $columns = [
                'overtime_pay', 'night_shift_allowance', 'public_holiday_pay',
                'leave_unpaid_deduction', 'violation_deduction',
                'leave_days_paid', 'leave_days_unpaid', 'overtime_hours',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('payroll_lines', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('payroll_lines', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
