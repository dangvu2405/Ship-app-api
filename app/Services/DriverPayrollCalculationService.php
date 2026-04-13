<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Trip;
use App\Models\TripBonusRule;
use App\Models\VehicleExpense;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverPayrollCalculationService
{
    /**
     * @return array{payroll: Payroll, lines_created: int}
     */
    public function createOrRecalculateDraft(int $companyId, int $month, int $year): array
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('month must be 1-12');
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end = (clone $start)->copy()->endOfMonth()->endOfDay();

        return DB::transaction(function () use ($companyId, $month, $year, $start, $end): array {
            $payroll = Payroll::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'status' => 'draft',
                    'notes' => null,
                ]
            );

            if ($payroll->isLocked()) {
                throw new InvalidArgumentException('Payroll is locked and cannot be recalculated.');
            }

            // Hard delete lines so unique (payroll_id, driver_id) is not blocked by soft-deleted rows.
            PayrollLine::query()->where('payroll_id', $payroll->id)->forceDelete();

            $rules = TripBonusRule::query()->orderBy('min_km')->get();
            $workingDays = (int) config('payroll.default_working_days', 22);
            $insurancePct = (float) config('payroll.insurance_percent_of_base', 0.105);
            $taxPct = (float) config('payroll.tax_percent_of_base', 0.0);
            $defaultAllowance = (float) config('payroll.default_allowance_per_driver', 0.0);

            $drivers = Driver::query()
                ->where('status', 'active')
                ->where('company_id', $companyId)
                ->with('position')
                ->get();

            $linesCreated = 0;

            foreach ($drivers as $driver) {
                $baseSalary = (float) ($driver->position?->base_salary ?? 0);

                $trips = $this->completedTripsInPeriod($driver->id, $start, $end);
                $tripMeta = [];
                $tripBonus = 0.0;
                $totalKm = 0.0;

                foreach ($trips as $trip) {
                    $km = (float) $trip->distance_km;
                    $totalKm += $km;
                    $rule = $this->matchBonusRule($rules, $km);
                    $perKm = $rule ? (float) $rule->bonus_per_km : 0.0;
                    $bonus = $km * $perKm;
                    $tripBonus += $bonus;
                    $tripMeta[] = [
                        'trip_id' => $trip->id,
                        'code' => $trip->code,
                        'distance_km' => $km,
                        'bonus_per_km' => $perKm,
                        'trip_bonus' => round($bonus, 2),
                        'rule' => $rule ? [
                            'id' => $rule->id,
                            'min_km' => (float) $rule->min_km,
                            'max_km' => $rule->max_km !== null ? (float) $rule->max_km : null,
                        ] : null,
                    ];
                }

                $fuelCost = (float) VehicleExpense::query()
                    ->where('driver_id', $driver->id)
                    ->where('type', 'fuel')
                    ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('amount');

                $allowance = $defaultAllowance;
                $deduction = round($baseSalary * $insurancePct, 2);
                $tax = round($baseSalary * $taxPct, 2);
                $net = round($baseSalary + $tripBonus + $allowance - $deduction - $fuelCost - $tax, 2);

                PayrollLine::query()->create([
                    'payroll_id' => $payroll->id,
                    'company_id' => $companyId,
                    'driver_id' => $driver->id,
                    'base_salary' => $baseSalary,
                    'trip_bonus' => round($tripBonus, 2),
                    'allowance' => $allowance,
                    'deduction' => $deduction,
                    'fuel_cost' => round($fuelCost, 2),
                    'tax' => $tax,
                    'net_salary' => $net,
                    'working_days' => $workingDays,
                    'trips_completed_count' => $trips->count(),
                    'total_distance_km' => round($totalKm, 2),
                    'meta_json' => [
                        'period' => ['month' => $month, 'year' => $year],
                        'trip_inclusion' => 'Trips with status=completed where COALESCE(end_time, updated_at) is within period start/end.',
                        'trips' => $tripMeta,
                        'config_snapshot' => [
                            'insurance_percent_of_base' => $insurancePct,
                            'tax_percent_of_base' => $taxPct,
                            'default_allowance_per_driver' => $defaultAllowance,
                            'default_working_days' => $workingDays,
                        ],
                    ],
                ]);
                ++$linesCreated;
            }

            return ['payroll' => $payroll->fresh(['lines.driver']), 'lines_created' => $linesCreated];
        });
    }

    public function lock(Payroll $payroll): Payroll
    {
        if ($payroll->isLocked()) {
            return $payroll;
        }

        return DB::transaction(function () use ($payroll): Payroll {
            $payroll->loadMissing(['lines.driver.position']);

            $snapshot = $this->buildLockSnapshot($payroll);

            PayrollLine::withoutEvents(function () use ($payroll, $snapshot): void {
                foreach ($payroll->lines as $line) {
                    $meta = $line->meta_json ?? [];
                    $meta['lock_snapshot'] = [
                        'locked_at' => now()->toIso8601String(),
                        'payroll_config' => $snapshot['payroll_config'] ?? null,
                        'trip_bonus_rules' => $snapshot['trip_bonus_rules'] ?? null,
                        'driver_base_salary' => $snapshot['drivers'][(string) $line->driver_id]['base_salary'] ?? null,
                        'driver_position_id' => $snapshot['drivers'][(string) $line->driver_id]['position_id'] ?? null,
                    ];
                    $line->update(['meta_json' => $meta]);
                }
            });

            $payroll->update([
                'status' => 'locked',
                'locked_at' => now(),
                'snapshot_json' => $snapshot,
            ]);

            return $payroll->fresh(['lines.driver']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLockSnapshot(Payroll $payroll): array
    {
        $rules = TripBonusRule::query()->orderBy('min_km')->get()->toArray();

        $drivers = [];
        foreach ($payroll->lines as $line) {
            $driver = $line->driver;
            $drivers[(string) $line->driver_id] = [
                'position_id' => $driver?->position_id,
                'base_salary' => (float) ($driver?->position?->base_salary ?? 0.0),
            ];
        }

        return [
            'locked_at' => now()->toIso8601String(),
            'payroll_id' => $payroll->id,
            'company_id' => $payroll->company_id,
            'month' => $payroll->month,
            'year' => $payroll->year,
            'trip_bonus_rules' => $rules,
            'payroll_config' => config('payroll'),
            'drivers' => $drivers,
        ];
    }

    /**
     * @return Collection<int, Trip>
     */
    private function completedTripsInPeriod(int $driverId, Carbon $start, Carbon $end): Collection
    {
        return Trip::query()
            ->where('driver_id', $driverId)
            ->where('status', 'completed')
            ->whereRaw(
                'COALESCE(end_time, updated_at) BETWEEN ? AND ?',
                [$start->toDateTimeString(), $end->toDateTimeString()]
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, TripBonusRule>  $rulesOrderedByMinKm
     */
    private function matchBonusRule(Collection $rulesOrderedByMinKm, float $distanceKm): ?TripBonusRule
    {
        foreach ($rulesOrderedByMinKm as $rule) {
            $min = (float) $rule->min_km;
            $max = $rule->max_km !== null ? (float) $rule->max_km : null;
            if ($distanceKm < $min) {
                continue;
            }
            if ($max !== null && $distanceKm > $max) {
                continue;
            }

            return $rule;
        }

        return null;
    }
}
