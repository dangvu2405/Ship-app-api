<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Payroll;
use App\Models\PayrollLine;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tạo nhiều bảng lương tài xế (draft / locked) theo từng công ty + tháng/năm, kèm payroll_lines.
 *
 * Chạy: php artisan db:seed --class=Database\\Seeders\\DriverPayrollBulkSeeder
 *
 * Idempotent: payroll unique (company_id, month, year); line unique (payroll_id, driver_id).
 * Số tháng: env PAYROLL_BULK_MONTHS (mặc định 24, tối đa 48).
 */
final class DriverPayrollBulkSeeder extends Seeder
{
    /** Mặc định số tháng lịch sử; ghi đè env PAYROLL_BULK_MONTHS (1–48). */
    private const DEFAULT_MONTH_SPAN = 24;

    /** Tối đa tài xế active / công ty / kỳ (tránh seed quá lâu). */
    private const MAX_DRIVERS_PER_COMPANY = 80;

    private const NOTES_MARKER = '[seed:bulk-driver-payroll]';

    public function run(): void
    {
        if (! Schema::hasTable('payrolls')) {
            $this->command?->error('Bảng payrolls chưa tồn tại. Chạy: php artisan migrate');

            return;
        }

        $companies = Company::query()->orderBy('id')->get();
        if ($companies->isEmpty()) {
            $this->command?->warn('Không có company nào — bỏ qua.');

            return;
        }

        $insurancePct = (float) config('payroll.insurance_percent_of_base', 0.105);
        $taxPct = (float) config('payroll.tax_percent_of_base', 0.0);
        $defaultAllowance = (float) config('payroll.default_allowance_per_driver', 0.0);
        $defaultWorkingDays = (int) config('payroll.default_working_days', 22);

        $monthSpan = max(1, min(48, (int) env('PAYROLL_BULK_MONTHS', self::DEFAULT_MONTH_SPAN)));
        $startMonth = Carbon::now()->startOfMonth()->subMonths($monthSpan - 1);
        $totalPayrolls = 0;
        $totalLines = 0;

        foreach ($companies as $company) {
            $drivers = Driver::query()
                ->where('status', 'active')
                ->whereHas('office', static function ($q) use ($company): void {
                    $q->where('company_id', $company->id);
                })
                ->with('position')
                ->orderBy('id')
                ->limit(self::MAX_DRIVERS_PER_COMPANY)
                ->get();

            if ($drivers->isEmpty()) {
                continue;
            }

            for ($i = 0; $i < $monthSpan; $i++) {
                $period = $startMonth->copy()->addMonths($i);
                $month = (int) $period->format('n');
                $year = (int) $period->format('Y');

                $locked = ($i % 2 === 0);

                $existing = Payroll::query()
                    ->where('company_id', $company->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->first();

                if ($existing !== null && $existing->notes !== null && $existing->notes !== '' && $existing->notes !== self::NOTES_MARKER) {
                    continue;
                }

                DB::transaction(function () use (
                    $company,
                    $month,
                    $year,
                    $locked,
                    $period,
                    $drivers,
                    $insurancePct,
                    $taxPct,
                    $defaultAllowance,
                    $defaultWorkingDays
                ): void {
                    $payroll = Payroll::query()->updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'month' => $month,
                            'year' => $year,
                        ],
                        [
                            'status' => $locked ? 'locked' : 'draft',
                            'locked_at' => $locked ? $period->copy()->endOfMonth() : null,
                            'notes' => self::NOTES_MARKER,
                        ]
                    );

                    foreach ($drivers as $driver) {
                        $base = (float) ($driver->position?->base_salary ?? 5_000_000.0);
                        $tripBonusCap = min(8_000_000.0, max(0.0, $base * 0.8));
                        $tripBonus = round(fake()->randomFloat(2, 0, $tripBonusCap), 2);
                        $deduction = round($base * $insurancePct, 2);
                        $tax = round($base * $taxPct, 2);
                        $fuelCap = min(3_000_000.0, $base * 0.25);
                        $fuel = round(fake()->randomFloat(2, 0, max(0, $fuelCap)), 2);
                        $net = round($base + $defaultAllowance + $tripBonus - $deduction - $fuel - $tax, 2);
                        $tripsCount = fake()->numberBetween(0, 120);
                        $totalKm = round(fake()->randomFloat(2, 0, 25_000), 2);

                        PayrollLine::query()->firstOrCreate(
                            [
                                'payroll_id' => $payroll->id,
                                'driver_id' => $driver->id,
                            ],
                            [
                                'base_salary' => $base,
                                'trip_bonus' => $tripBonus,
                                'allowance' => $defaultAllowance,
                                'deduction' => $deduction,
                                'fuel_excess_deduction' => $fuel,
                                'tax' => $tax,
                                'net_salary' => $net,
                                'working_days' => $defaultWorkingDays,
                                'trips_completed_count' => $tripsCount,
                                'total_distance_km' => $totalKm,
                                'meta_json' => [
                                    'seed' => true,
                                    'driver_code' => $driver->code,
                                    'period' => ['month' => $month, 'year' => $year],
                                ],
                            ]
                        );
                    }
                });

                $totalPayrolls++;
                $totalLines += $drivers->count();
            }
        }

        $this->command?->info(sprintf(
            'DriverPayrollBulkSeeder: ~%d payroll periods touched, ~%d line upserts (companies=%d, months=%d).',
            $totalPayrolls,
            $totalLines,
            $companies->count(),
            $monthSpan
        ));
    }
}
