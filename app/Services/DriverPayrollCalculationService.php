<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Driver;
use App\Models\NightShiftPolicy;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\PublicHoliday;
use App\Models\Trip;
use App\Models\TripBonusRule;
use App\Models\VehicleExpense;
use App\Models\Violation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverPayrollCalculationService
{
    /**
     * Standard daily working hours used for rate calculations.
     */
    private const STANDARD_HOURS_PER_DAY = 8.0;

    public function __construct(
        private readonly TaxCalculatorService $taxCalculator,
    ) {}

    /**
     * @return array{payroll: Payroll, lines_created: int}
     */
    public function createOrRecalculateDraft(int $companyId, int $month, int $year): array
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('month must be 1-12');
        }

        $start = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth()->endOfDay();

        return DB::transaction(function () use ($companyId, $month, $year, $start, $end): array {
            // Create the payroll row if it doesn't exist yet (safe: unique constraint prevents duplicates).
            Payroll::query()->firstOrCreate(
                ['company_id' => $companyId, 'month' => $month, 'year' => $year],
                ['status' => 'draft', 'notes' => null],
            );

            // Pessimistic lock: any concurrent recalculation request will block here until
            // this transaction commits, preventing duplicate PayrollLine inserts.
            $payroll = Payroll::query()
                ->where('company_id', $companyId)
                ->where('month', $month)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payroll->isFrozen()) {
                throw new InvalidArgumentException('Payroll is locked or paid and cannot be recalculated.');
            }

            // Hard-delete lines so the unique (payroll_id, driver_id) constraint is not blocked by soft-deleted rows.
            PayrollLine::query()->where('payroll_id', $payroll->id)->forceDelete();

            $rules = TripBonusRule::query()
                ->where('company_id', $companyId)
                ->whereDate('effective_from', '<=', $end->toDateString())
                ->where(function ($query) use ($start): void {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', $start->toDateString());
                })
                ->orderBy('min_km')
                ->get();
            $workingDays      = (int) config('payroll.default_working_days', 22);
            $defaultAllowance = (float) config('payroll.default_allowance_per_driver', 0.0);

            $holidayDates   = PublicHoliday::datesForMonth($year, $month);
            $holidayCount   = count($holidayDates);
            $effectiveStdDays = max(1, $workingDays - $holidayCount);

            $nightPolicy = NightShiftPolicy::query()
                ->activeForCompany($companyId)
                ->effectiveOn($start->toDateString())
                ->first();

            // Include drivers who were soft-deleted mid-period so they still receive
            // pay for the days they actually worked before their deletion date.
            $drivers = Driver::withTrashed()
                ->where('company_id', $companyId)
                ->where(function ($q) use ($start): void {
                    $q->where(function ($inner): void {
                        // Currently active, non-deleted drivers
                        $inner->whereNull('deleted_at')->where('status', 'active');
                    })->orWhere(function ($inner) use ($start): void {
                        // Drivers deleted after the period started (partial-month terminations)
                        $inner->whereNotNull('deleted_at')
                            ->where('deleted_at', '>', $start->toDateString());
                    });
                })
                ->with('position')
                ->get();

            $linesCreated = 0;

            foreach ($drivers as $driver) {
                $baseSalary = (float) ($driver->position?->base_salary ?? 0);

                // --- Trip bonuses ---
                $trips    = $this->completedTripsInPeriod($driver->id, $start, $end);
                $tripMeta = [];
                $tripBonus = 0.0;
                $totalKm  = 0.0;

                foreach ($trips as $trip) {
                    $km    = (float) $trip->distance_km;
                    $totalKm += $km;
                    $rule  = $this->matchBonusRule($rules, $km);
                    $perKm = $rule ? (float) $rule->bonus_per_km : 0.0;
                    $bonus = $km * $perKm;
                    $tripBonus += $bonus;
                    $tripMeta[] = [
                        'trip_id'      => $trip->id,
                        'code'         => $trip->code,
                        'distance_km'  => $km,
                        'bonus_per_km' => $perKm,
                        'trip_bonus'   => round($bonus, 2),
                        'rule'         => $rule ? [
                            'id'     => $rule->id,
                            'min_km' => (float) $rule->min_km,
                            'max_km' => $rule->max_km !== null ? (float) $rule->max_km : null,
                        ] : null,
                    ];
                }

                // --- Fuel cost ---
                $fuelCost = (float) VehicleExpense::query()
                    ->where('driver_id', $driver->id)
                    ->where('type', 'fuel')
                    ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('amount');

                // --- Leave proration (unpaid leave reduces base_salary) ---
                // Both deduction and prorated salary use effectiveStdDays as the
                // denominator so the per-day rate is consistent within the period.
                $unpaidLeaveDays = $this->unpaidLeaveDaysForPeriod($driver->id, $start, $end);
                $leaveUnpaidDeduction = $unpaidLeaveDays > 0
                    ? round($baseSalary * $unpaidLeaveDays / $effectiveStdDays, 0)
                    : 0.0;

                // Paid leave counts towards working days (no deduction needed)
                $paidLeaveDays     = $this->paidLeaveDaysForPeriod($driver->id, $start, $end);
                $actualWorkingDays = max(0, $effectiveStdDays - (int) $unpaidLeaveDays);
                $proratedBaseSalary = round($baseSalary * ($actualWorkingDays / max(1, $effectiveStdDays)), 2);

                // --- Overtime pay ---
                $otPay = $this->calculateOtPay(
                    $driver->id,
                    $start->toDateString(),
                    $end->toDateString(),
                    $baseSalary,
                    $effectiveStdDays,
                    $holidayDates,
                );

                // --- Night shift allowance ---
                $nightShiftAllowance = 0.0;
                if ($nightPolicy) {
                    $nightShiftAllowance = $this->calculateNightShiftAllowance(
                        $driver->id,
                        $start,
                        $end,
                        $baseSalary,
                        $effectiveStdDays,
                        $nightPolicy->start_hour,
                        $nightPolicy->end_hour,
                        (float) $nightPolicy->differential_pct,
                    );
                }

                // --- Public holiday pay (300% for days worked on public holidays) ---
                $publicHolidayPay = $this->calculatePublicHolidayPay(
                    $driver->id,
                    $start,
                    $end,
                    $baseSalary,
                    $effectiveStdDays,
                    $holidayDates,
                );

                // --- Violation deductions ---
                $violationDeduction = (float) Violation::query()
                    ->where('driver_id', $driver->id)
                    ->where('status', 'confirmed')
                    ->whereBetween('occurred_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                    // Keep violations in 3-day dispute window on hold.
                    ->where('occurred_at', '<=', $end->copy()->subDays(3)->toDateTimeString())
                    // Exclude open/under-review/overturned disputes from deduction.
                    ->whereNotExists(function ($query): void {
                        $query->selectRaw('1')
                            ->from('violation_disputes')
                            ->whereColumn('violation_disputes.violation_id', 'violations.id')
                            ->whereIn('violation_disputes.status', ['open', 'under_review', 'resolved_overturned'])
                            ->whereNull('violation_disputes.deleted_at');
                    })
                    ->sum('penalty_amount');

                // --- Insurance & TNCN tax via VN progressive calculator ---
                // Gross for tax purposes includes all income components.
                // Insurance uses prorated base (capped at ceiling) per VN law.
                $grossForTax = $proratedBaseSalary
                    + $tripBonus
                    + $otPay
                    + $nightShiftAllowance
                    + $publicHolidayPay
                    + $defaultAllowance;

                $taxCalc = $this->taxCalculator->calculate(
                    $grossForTax,
                    (int) ($driver->tax_dependents ?? 0),
                );

                $allowance = $defaultAllowance;
                $deduction = $taxCalc['insurance'];  // BHXH + BHYT + BHTN (employee share)
                $tax       = $taxCalc['tncn_tax'];   // progressive personal income tax

                $fuelQuota = (float) config('payroll.fuel_monthly_quota', 0.0);
                $fuelSavingBonusRate = (float) config('payroll.fuel_saving_bonus_rate', 0.0);
                $fuelDeduction = max(0.0, $fuelCost - $fuelQuota);
                $fuelSavingBonus = max(0.0, $fuelQuota - $fuelCost) * $fuelSavingBonusRate;

                $net = round(
                    $proratedBaseSalary
                    + $tripBonus
                    + $otPay
                    + $nightShiftAllowance
                    + $publicHolidayPay
                    + $allowance
                    + $fuelSavingBonus
                    - $deduction
                    - $violationDeduction
                    - $fuelDeduction
                    - $tax,
                    0,
                );

                PayrollLine::query()->create([
                    'payroll_id'            => $payroll->id,
                    'company_id'            => $companyId,
                    'driver_id'             => $driver->id,
                    'base_salary'           => $proratedBaseSalary,
                    'trip_bonus'            => round($tripBonus, 2),
                    'overtime_pay'          => round($otPay, 2),
                    'night_shift_allowance' => round($nightShiftAllowance, 2),
                    'public_holiday_pay'    => round($publicHolidayPay, 2),
                    'allowance'             => $allowance,
                    'deduction'             => $deduction,
                    'leave_unpaid_deduction' => round($leaveUnpaidDeduction, 2),
                    'violation_deduction'   => round($violationDeduction, 2),
                    'fuel_excess_deduction' => round($fuelDeduction, 2),
                    'tax'                   => $tax,
                    'net_salary'            => $net,
                    'working_days'          => $actualWorkingDays,
                    'leave_days_paid'       => (int) $paidLeaveDays,
                    'leave_days_unpaid'     => (int) $unpaidLeaveDays,
                    'overtime_hours'        => $this->totalApprovedOtHours($driver->id, $start, $end),
                    'trips_completed_count' => $trips->count(),
                    'total_distance_km'     => round($totalKm, 2),
                    'meta_json'             => [
                        'period'         => ['month' => $month, 'year' => $year],
                        'effective_std_days' => $effectiveStdDays,
                        'working_days_standard' => $workingDays,
                        'working_days_actual' => $actualWorkingDays,
                        'prorated_base_salary' => $proratedBaseSalary,
                        'holiday_count'  => $holidayCount,
                        'holiday_dates'  => $holidayDates,
                        'fuel' => [
                            'actual_fuel_cost' => round($fuelCost, 2),
                            'fuel_quota' => round($fuelQuota, 2),
                            'fuel_deduction' => round($fuelDeduction, 2),
                            'fuel_saving_bonus' => round($fuelSavingBonus, 2),
                        ],
                        'violation' => [
                            'dispute_window_days' => 3,
                            'deduction_only_confirmed_and_not_disputed' => true,
                        ],
                        'trip_inclusion' => 'Trips with status=completed where COALESCE(end_time, updated_at) is within period.',
                        'trips'          => $tripMeta,
                        'tax_breakdown' => [
                            'gross_for_tax'        => $grossForTax,
                            'insurance_base'       => $taxCalc['insurance_base'],
                            'bhxh'                 => $taxCalc['bhxh'],
                            'bhyt'                 => $taxCalc['bhyt'],
                            'bhtn'                 => $taxCalc['bhtn'],
                            'insurance_total'      => $taxCalc['insurance'],
                            'personal_deduction'   => TaxCalculatorService::PERSONAL_DEDUCTION,
                            'dependent_deduction'  => ($driver->tax_dependents ?? 0) * TaxCalculatorService::DEPENDENT_DEDUCTION,
                            'taxable_income'       => $taxCalc['taxable_income'],
                            'tncn_tax'             => $taxCalc['tncn_tax'],
                            'dependents'           => $taxCalc['dependents'],
                        ],
                        'config_snapshot' => [
                            'insurance_ceiling'            => TaxCalculatorService::INSURANCE_CEILING,
                            'default_allowance_per_driver' => $defaultAllowance,
                            'default_working_days'         => $workingDays,
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
                        'locked_at'           => now()->toIso8601String(),
                        'payroll_config'      => $snapshot['payroll_config'] ?? null,
                        'trip_bonus_rules'    => $snapshot['trip_bonus_rules'] ?? null,
                        'driver_base_salary'  => $snapshot['drivers'][(string) $line->driver_id]['base_salary'] ?? null,
                        'driver_position_id'  => $snapshot['drivers'][(string) $line->driver_id]['position_id'] ?? null,
                    ];
                    $line->update(['meta_json' => $meta]);
                }
            });

            $payroll->update([
                'status'        => 'locked',
                'locked_at'     => now(),
                'snapshot_json' => $snapshot,
            ]);

            return $payroll->fresh(['lines.driver']);
        });
    }

    // ---------------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function buildLockSnapshot(Payroll $payroll): array
    {
        $start = Carbon::createFromDate($payroll->year, $payroll->month, 1)->startOfDay();
        $end = (clone $start)->endOfMonth()->endOfDay();

        $rules = TripBonusRule::query()
            ->where('company_id', $payroll->company_id)
            ->whereDate('effective_from', '<=', $end->toDateString())
            ->where(function ($query) use ($start): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $start->toDateString());
            })
            ->orderBy('min_km')
            ->get()
            ->toArray();
        $drivers = [];
        foreach ($payroll->lines as $line) {
            $driver = $line->driver;
            $drivers[(string) $line->driver_id] = [
                'position_id' => $driver?->position_id,
                'base_salary' => (float) ($driver?->position?->base_salary ?? 0.0),
            ];
        }

        return [
            'locked_at'        => now()->toIso8601String(),
            'payroll_id'       => $payroll->id,
            'company_id'       => $payroll->company_id,
            'month'            => $payroll->month,
            'year'             => $payroll->year,
            'trip_bonus_rules' => $rules,
            'payroll_config'   => config('payroll'),
            'drivers'          => $drivers,
        ];
    }

    /** @return Collection<int, Trip> */
    private function completedTripsInPeriod(int $driverId, Carbon $start, Carbon $end): Collection
    {
        return Trip::query()
            ->where('driver_id', $driverId)
            ->where('status', 'completed')
            ->whereRaw(
                'COALESCE(end_time, updated_at) BETWEEN ? AND ?',
                [$start->toDateTimeString(), $end->toDateTimeString()],
            )
            ->orderBy('id')
            ->get();
    }

    /** @param Collection<int, TripBonusRule> $rulesOrderedByMinKm */
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

    private function unpaidLeaveDaysForPeriod(int $driverId, Carbon $start, Carbon $end): float
    {
        // Clip overlapping leave requests to the payroll period so cross-month leaves
        // (e.g. Oct 28 – Nov 3) are only counted once in each respective period.
        return (float) DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.driver_id', $driverId)
            ->where('leave_requests.status', 'approved')
            ->where('leave_types.is_paid', false)
            ->where('leave_requests.from_date', '<=', $end->toDateString())
            ->where('leave_requests.to_date', '>=', $start->toDateString())
            ->whereNull('leave_requests.deleted_at')
            ->selectRaw(
                'SUM(DATEDIFF(LEAST(leave_requests.to_date, ?), GREATEST(leave_requests.from_date, ?)) + 1) AS clipped_days',
                [$end->toDateString(), $start->toDateString()],
            )
            ->value('clipped_days') ?? 0.0;
    }

    private function paidLeaveDaysForPeriod(int $driverId, Carbon $start, Carbon $end): float
    {
        return (float) DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.driver_id', $driverId)
            ->where('leave_requests.status', 'approved')
            ->where('leave_types.is_paid', true)
            ->where('leave_requests.from_date', '<=', $end->toDateString())
            ->where('leave_requests.to_date', '>=', $start->toDateString())
            ->whereNull('leave_requests.deleted_at')
            ->selectRaw(
                'SUM(DATEDIFF(LEAST(leave_requests.to_date, ?), GREATEST(leave_requests.from_date, ?)) + 1) AS clipped_days',
                [$end->toDateString(), $start->toDateString()],
            )
            ->value('clipped_days') ?? 0.0;
    }

    /**
     * OT pay per Vietnamese Labor Code:
     *   Weekday: 150% | Weekend: 200% | Holiday: 300%
     *
     * Uses the same $stdDays denominator as night-shift allowance so all
     * per-hour rates are consistent within a single payroll period.
     *
     * @param array<string> $holidayDates
     */
    private function calculateOtPay(
        int $driverId,
        string $from,
        string $to,
        float $monthlyBase,
        int $stdDays,
        array $holidayDates,
    ): float {
        $hourlyRate = $monthlyBase / ($stdDays * self::STANDARD_HOURS_PER_DAY);
        $maxOtHours = (float) config('payroll.max_ot_hours_per_month', 40.0);

        $otRequests = OvertimeRequest::query()
            ->where('driver_id', $driverId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get();

        $total        = 0.0;
        $usedOtHours  = 0.0;

        foreach ($otRequests as $ot) {
            $remaining = $maxOtHours - $usedOtHours;
            if ($remaining <= 0.0) {
                break;
            }

            // Clamp this request's hours to the remaining cap.
            $hours = min((float) $ot->ot_hours, $remaining);
            $usedOtHours += $hours;

            $dateStr   = $ot->work_date->toDateString();
            $dayOfWeek = $ot->work_date->dayOfWeek;
            $isHoliday = in_array($dateStr, $holidayDates);
            $isWeekend = in_array($dayOfWeek, [0, 6]);

            $multiplier = match (true) {
                $isHoliday => 3.0,
                $isWeekend => 2.0,
                default    => 1.5,
            };

            $total += $hours * $hourlyRate * $multiplier;
        }

        return round($total, 0);
    }

    private function totalApprovedOtHours(int $driverId, Carbon $start, Carbon $end): float
    {
        return (float) OvertimeRequest::query()
            ->where('driver_id', $driverId)
            ->where('status', 'approved')
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->sum('ot_hours');
    }

    /**
     * Night shift allowance: additional differential% on top of base hourly rate
     * for each hour worked within the night window (default 22:00–06:00).
     * We approximate using attendance records.
     */
    private function calculateNightShiftAllowance(
        int $driverId,
        Carbon $start,
        Carbon $end,
        float $monthlyBase,
        int $stdDays,
        int $nightStart,
        int $nightEnd,
        float $differentialPct,
    ): float {
        $hourlyRate = $monthlyBase / ($stdDays * self::STANDARD_HOURS_PER_DAY);

        $records = DB::table('attendances')
            ->where('driver_id', $driverId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->get();

        $totalNightHours = 0.0;

        foreach ($records as $record) {
            $checkIn  = Carbon::parse($record->date.' '.$record->check_in);
            $checkOut = Carbon::parse($record->date.' '.$record->check_out);

            if ($checkOut->lt($checkIn)) {
                $checkOut->addDay();
            }

            $totalNightHours += $this->nightHoursInWindow($checkIn, $checkOut, $nightStart, $nightEnd);
        }

        return round($totalNightHours * $hourlyRate * ($differentialPct / 100), 0);
    }

    private function nightHoursInWindow(Carbon $checkIn, Carbon $checkOut, int $startH, int $endH): float
    {
        $nightStartToday = (clone $checkIn)->setTime($startH, 0, 0);
        $nightEndToday   = (clone $checkIn)->setTime($endH, 0, 0);

        if ($endH <= $startH) {
            $nightEndToday->addDay();
        }

        $overlapStart = max($checkIn->timestamp, $nightStartToday->timestamp);
        $overlapEnd   = min($checkOut->timestamp, $nightEndToday->timestamp);

        if ($overlapEnd <= $overlapStart) {
            return 0.0;
        }

        return ($overlapEnd - $overlapStart) / 3600;
    }

    /**
     * Public holiday pay: when a driver works on a holiday they receive 300% of daily rate.
     * We detect "worked on holiday" via attendance records.
     *
     * @param array<string> $holidayDates
     */
    private function calculatePublicHolidayPay(
        int $driverId,
        Carbon $start,
        Carbon $end,
        float $monthlyBase,
        int $stdDays,
        array $holidayDates,
    ): float {
        if (empty($holidayDates)) {
            return 0.0;
        }

        $dailyRate = $monthlyBase / $stdDays;

        $workedHolidays = DB::table('attendances')
            ->where('driver_id', $driverId)
            ->whereIn('date', $holidayDates)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')   // incomplete attendance records are excluded
            ->count();

        return round($workedHolidays * $dailyRate * 3.0, 0);
    }
}
