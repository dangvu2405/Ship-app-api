<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Vietnamese personal income tax (TNCN) and social insurance calculator.
 *
 * Tax rules per Vietnamese law (2025):
 *   - Employee social insurance (BHXH/BHYT/BHTN): 10.5% of insurable salary
 *   - Insurance ceiling: 20 × base wage (2025: VND 2,340,000 × 20 = 46,800,000)
 *   - Personal deduction: VND 11,000,000/month
 *   - Dependent deduction: VND 4,400,000/dependent/month
 *   - TNCN: progressive brackets applied to taxable income
 *
 * Tax brackets are loaded from the `tax_brackets` table when seeded;
 * falls back to the hardcoded 2025 schedule otherwise.
 */
class TaxCalculatorService
{
    // Employee-side insurance rates (Điều 85, 86 Luật BHXH 2014)
    private const BHXH_RATE = 0.08;   // 8% pension + disability

    private const BHYT_RATE = 0.015;  // 1.5% health insurance

    private const BHTN_RATE = 0.01;   // 1% unemployment insurance

    public const INSURANCE_RATE = 0.105; // total 10.5%

    // Insurable salary ceiling: 20 × base wage for BHXH/BHTN; 3 × region min for BHYT (simplified to same ceiling)
    // 2025 base wage = 2,340,000 VND → ceiling = 46,800,000 VND
    public const INSURANCE_CEILING = 46_800_000.0;

    // Personal deduction per Thông tư 111/2013 amended by NĐ 954/2020
    public const PERSONAL_DEDUCTION = 11_000_000.0;

    public const DEPENDENT_DEDUCTION = 4_400_000.0;

    /**
     * 2025 default progressive TNCN brackets (Điều 22, Luật Thuế TNCN).
     * Format: [income_from, income_to, rate, quick_deduction]
     * quick_deduction = cumulative tax already paid in lower brackets.
     *
     * @var array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private const DEFAULT_BRACKETS = [
        [0.0,           5_000_000.0,   0.05, 0.0],
        [5_000_000.0,  10_000_000.0,   0.10, 250_000.0],
        [10_000_000.0, 18_000_000.0,   0.15, 750_000.0],
        [18_000_000.0, 32_000_000.0,   0.20, 1_650_000.0],
        [32_000_000.0, 52_000_000.0,   0.25, 3_250_000.0],
        [52_000_000.0, 80_000_000.0,   0.30, 5_850_000.0],
        [80_000_000.0, PHP_INT_MAX,    0.35, 9_850_000.0],
    ];

    /**
     * Calculate employee insurance and TNCN for a given gross monthly salary.
     *
     * @param  float  $grossSalary  Total gross income for the month (base + bonuses + OT + allowances)
     * @param  int  $dependents  Number of registered tax dependents (default 0)
     * @return array{
     *   gross: float,
     *   insurance_base: float,
     *   bhxh: float,
     *   bhyt: float,
     *   bhtn: float,
     *   insurance: float,
     *   taxable_income: float,
     *   tncn_tax: float,
     *   dependents: int,
     * }
     */
    public function calculate(float $grossSalary, int $dependents = 0): array
    {
        // 1. Employee insurance (capped at ceiling)
        $insuranceBase = min($grossSalary, self::INSURANCE_CEILING);
        $bhxh = round($insuranceBase * self::BHXH_RATE, 0);
        $bhyt = round($insuranceBase * self::BHYT_RATE, 0);
        $bhtn = round($insuranceBase * self::BHTN_RATE, 0);
        $insurance = $bhxh + $bhyt + $bhtn;

        // 2. Taxable income = gross − insurance − personal deduction − dependent deduction
        $taxableIncome = $grossSalary
            - $insurance
            - self::PERSONAL_DEDUCTION
            - ($dependents * self::DEPENDENT_DEDUCTION);

        if ($taxableIncome <= 0.0) {
            return [
                'gross' => $grossSalary,
                'insurance_base' => $insuranceBase,
                'bhxh' => $bhxh,
                'bhyt' => $bhyt,
                'bhtn' => $bhtn,
                'insurance' => $insurance,
                'taxable_income' => 0.0,
                'tncn_tax' => 0.0,
                'dependents' => $dependents,
            ];
        }

        // 3. Progressive TNCN (using quick-deduction formula for efficiency)
        $brackets = $this->loadBrackets();
        $tncnTax = 0.0;

        foreach ($brackets as [$from, $to, $rate, $quickDeduction]) {
            if ($taxableIncome <= $from) {
                break;
            }
            // The quick_deduction shortcut: tax = taxable × rate − quick_deduction
            // We apply it at the highest bracket that applies.
            if ($taxableIncome >= $to) {
                continue;
            }
            $tncnTax = $taxableIncome * $rate - $quickDeduction;
            break;
        }

        // If income exceeds all bracket upper bounds, use the last bracket
        if ($tncnTax === 0.0 && $taxableIncome > 0.0) {
            [$from, $to, $rate, $quickDeduction] = end($brackets);
            $tncnTax = $taxableIncome * $rate - $quickDeduction;
        }

        return [
            'gross' => $grossSalary,
            'insurance_base' => $insuranceBase,
            'bhxh' => $bhxh,
            'bhyt' => $bhyt,
            'bhtn' => $bhtn,
            'insurance' => $insurance,
            'taxable_income' => max(0.0, $taxableIncome),
            'tncn_tax' => round(max(0.0, $tncnTax), 0),
            'dependents' => $dependents,
        ];
    }

    /**
     * Load active tax brackets from DB (seeded via TaxBracketsSeeder),
     * falling back to hardcoded 2025 defaults if the table is empty.
     *
     * @return array<int, array{0: float, 1: float, 2: float, 3: float}>
     */
    private function loadBrackets(): array
    {
        return Cache::remember('tax_brackets_active', now()->addHours(6), function (): array {
            $rows = DB::table('tax_brackets')
                ->where('status', 'active')
                ->where('effective_from', '<=', now()->toDateString())
                ->where(function ($q): void {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', now()->toDateString());
                })
                ->orderBy('income_from')
                ->get(['income_from', 'income_to', 'tax_rate', 'quick_deduction']);

            if ($rows->isEmpty()) {
                return self::DEFAULT_BRACKETS;
            }

            // DB stores tax_rate as a percentage (e.g. 5.00 for 5%) → convert to decimal
            return $rows->map(fn ($r): array => [
                (float) $r->income_from,
                $r->income_to !== null ? (float) $r->income_to : (float) PHP_INT_MAX,
                (float) $r->tax_rate / 100.0,
                (float) $r->quick_deduction,
            ])->all();
        });
    }
}
