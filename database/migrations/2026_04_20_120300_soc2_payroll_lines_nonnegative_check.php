<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'chk_payroll_lines_amounts_non_negative';

    public function up(): void
    {
        if (! Schema::hasTable('payroll_lines')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
            DB::statement('ALTER TABLE payroll_lines ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (
                base_salary >= 0 AND trip_bonus >= 0 AND allowance >= 0 AND deduction >= 0
                AND fuel_cost >= 0 AND tax >= 0
            )');

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payroll_lines ADD CONSTRAINT '.self::CONSTRAINT.' CHECK (
                base_salary >= 0 AND trip_bonus >= 0 AND allowance >= 0 AND deduction >= 0
                AND fuel_cost >= 0 AND tax >= 0
            )');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_lines')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payroll_lines DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);

            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payroll_lines DROP CHECK '.self::CONSTRAINT);
        }
    }
};
