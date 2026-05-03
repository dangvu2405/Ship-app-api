<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename payroll_lines.fuel_cost → fuel_excess_deduction.
 *
 * The column stores the amount deducted when actual fuel spend exceeds the
 * configured monthly quota — not the total fuel cost itself.  The old name
 * caused confusion in reports and audits.
 */
return new class extends Migration
{
    private const CONSTRAINT = 'chk_payroll_lines_amounts_non_negative';

    public function up(): void
    {
        if (! Schema::hasTable('payroll_lines')) {
            return;
        }

        if (! Schema::hasColumn('payroll_lines', 'fuel_cost')) {
            return;
        }

        if (Schema::hasColumn('payroll_lines', 'fuel_excess_deduction')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payroll_lines DROP CHECK '.self::CONSTRAINT);
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        }

        Schema::table('payroll_lines', function (Blueprint $table): void {
            $table->renameColumn('fuel_cost', 'fuel_excess_deduction');
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (
                base_salary >= 0 AND trip_bonus >= 0 AND allowance >= 0 AND deduction >= 0
                AND fuel_excess_deduction >= 0 AND tax >= 0
            )');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE payroll_lines ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (
                base_salary >= 0 AND trip_bonus >= 0 AND allowance >= 0 AND deduction >= 0
                AND fuel_excess_deduction >= 0 AND tax >= 0
            )');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_lines')) {
            return;
        }

        if (! Schema::hasColumn('payroll_lines', 'fuel_excess_deduction')) {
            return;
        }

        if (Schema::hasColumn('payroll_lines', 'fuel_cost')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payroll_lines DROP CHECK '.self::CONSTRAINT);
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        }

        Schema::table('payroll_lines', function (Blueprint $table): void {
            $table->renameColumn('fuel_excess_deduction', 'fuel_cost');
        });

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (
                base_salary >= 0 AND trip_bonus >= 0 AND allowance >= 0 AND deduction >= 0
                AND fuel_cost >= 0 AND tax >= 0
            )');
        } elseif ($driver === 'mysql') {
            DB::statement('ALTER TABLE payroll_lines ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (
                base_salary >= 0 AND trip_bonus >= 0 AND allowance >= 0 AND deduction >= 0
                AND fuel_cost >= 0 AND tax >= 0
            )');
        }
    }
};
