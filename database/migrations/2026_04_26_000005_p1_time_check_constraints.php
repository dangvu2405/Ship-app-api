<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1: Thêm CHECK constraints cho các cặp start/end time.
 *
 * - trips: end_time >= start_time (nullable-safe, kiểu DATETIME)
 * - driver_work_schedules: end_time > start_time (kiểu TIME, không có ca qua đêm)
 * - payroll_adjustments: amount > 0 (dấu suy ra từ type)
 *
 * Không thêm CHECK cho attendances.check_out >= check_in vì cột là kiểu TIME —
 * ca đêm qua 0h (check_in=23:00, check_out=01:00) sẽ vi phạm constraint sai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->safeStatement('
            ALTER TABLE `trips`
            ADD CONSTRAINT `chk_trips_time`
                CHECK (end_time IS NULL OR start_time IS NULL OR end_time >= start_time)
        ');

        $this->safeStatement('
            ALTER TABLE `driver_work_schedules`
            ADD CONSTRAINT `chk_dws_time`
                CHECK (end_time > start_time)
        ');

        $this->safeStatement('
            ALTER TABLE `payroll_adjustments`
            ADD CONSTRAINT `chk_pa_amount_positive`
                CHECK (amount > 0)
        ');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ([
            ['payroll_adjustments', 'chk_pa_amount_positive'],
            ['driver_work_schedules', 'chk_dws_time'],
            ['trips', 'chk_trips_time'],
        ] as [$table, $constraint]) {
            $this->safeStatement("ALTER TABLE `{$table}` DROP CHECK `{$constraint}`");
        }
    }

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[time_checks] Skipped: '.$e->getMessage());
        }
    }
};
