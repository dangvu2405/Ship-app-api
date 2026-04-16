<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(int $month, int $year): array
    {
        $key = "report:dashboard:{$month}:{$year}";

        /** @var array<string, mixed> $data */
        $data = Cache::remember($key, 3600, function () use ($month, $year): array {
            $companiesTotal = Company::count();
            $companiesActive = Company::where('status', 'active')->count();
            $employeesTotal = Driver::count();
            $employeesActive = Driver::where('status', 'active')->count();
            $vehiclesTotal = Vehicle::count();
            $vehiclesActive = Vehicle::where('status', 'active')->count();
            $tripsTotal = Trip::whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count();
            $tripsPending = Trip::where('status', 'pending')
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count();
            $tripsCompleted = Trip::where('status', 'completed')
                ->whereMonth('created_at', $month)
                ->whereYear('created_at', $year)
                ->count();

            $payrollsTotal = Payroll::where('month', $month)->where('year', $year)->count();
            $payrollsPending = Payroll::whereIn('status', ['draft', 'pending'])
                ->where('month', $month)
                ->where('year', $year)
                ->count();
            $payrollsCompleted = Payroll::whereIn('status', ['approved', 'locked', 'completed'])
                ->where('month', $month)
                ->where('year', $year)
                ->count();

            return [
                'companies_count' => $companiesTotal,
                'payrolls_count' => $payrollsTotal,
                'companies' => [
                    'total' => $companiesTotal,
                    'active' => $companiesActive,
                ],
                'employees' => [
                    'total' => $employeesTotal,
                    'active' => $employeesActive,
                ],
                'vehicles' => [
                    'total' => $vehiclesTotal,
                    'active' => $vehiclesActive,
                ],
                'trips' => [
                    'total' => $tripsTotal,
                    'pending' => $tripsPending,
                    'completed' => $tripsCompleted,
                ],
                'payrolls' => [
                    'total' => $payrollsTotal,
                    'pending' => $payrollsPending,
                    'completed' => $payrollsCompleted,
                ],
            ];
        });

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPayrollSummaryData(int $companyId, int $month, int $year): ?array
    {
        $key = "payroll:{$companyId}:{$month}:{$year}";

        /** @var array<string, mixed>|null $data */
        $data = Cache::remember($key, 86400, function () use ($companyId, $month, $year): ?array {
            $payroll = Payroll::with('lines.driver')
                ->where('company_id', $companyId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (! $payroll) {
                return null;
            }

            return [
                'payroll' => $payroll,
                'total_net' => $payroll->lines->sum('net_salary'),
                'employees_count' => $payroll->lines->pluck('driver_id')->filter()->unique()->count(),
            ];
        });

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRevenueSummaryData(?int $companyId, string $from, string $to): array
    {
        $fromDate = Carbon::parse($from)->startOfDay();
        $toDate = Carbon::parse($to)->endOfDay();
        $fromYm = ((int) $fromDate->format('Y') * 100) + (int) $fromDate->format('m');
        $toYm = ((int) $toDate->format('Y') * 100) + (int) $toDate->format('m');
        $cacheKey = sprintf('report:revenue:%s:%s:%s', (string) ($companyId ?? 'all'), $fromDate->toDateString(), $toDate->toDateString());

        /** @var array<string, mixed> $data */
        $data = Cache::remember($cacheKey, 3600, function () use ($companyId, $fromDate, $toDate, $fromYm, $toYm): array {
            $totalInvoicedQuery = Invoice::query()
                ->whereBetween('issued_at', [$fromDate, $toDate]);
            if ($companyId !== null) {
                $totalInvoicedQuery->whereHas('trip', static function ($query) use ($companyId): void {
                    $query->where('company_id', $companyId);
                });
            }
            $totalInvoiced = (float) ($totalInvoicedQuery->sum('total_amount') ?? 0.0);

            $collectedQuery = Invoice::query()
                ->where('status', 'paid')
                ->whereBetween('paid_at', [$fromDate, $toDate]);

            if ($companyId !== null) {
                $collectedQuery->whereHas('trip', static function ($query) use ($companyId): void {
                    $query->where('company_id', $companyId);
                });
            }

            $totalCollected = (float) ($collectedQuery->sum('total_amount') ?? 0.0);
            // TODO: replace with cross-month allocation table when available.
            $crossMonthAllocated = $totalCollected;

            $payrollNetQuery = PayrollLine::query()
                ->whereHas('payroll', static function ($query) use ($fromYm, $toYm): void {
                    $query->whereRaw('(year * 100 + month) BETWEEN ? AND ?', [$fromYm, $toYm]);
                });
            if ($companyId !== null) {
                $payrollNetQuery->where('company_id', $companyId);
            }
            $totalNetSalary = (float) ($payrollNetQuery->sum('net_salary') ?? 0.0);

            $vehicleExpenseBaseQuery = VehicleExpense::query()
                ->whereBetween('expense_date', [$fromDate->toDateString(), $toDate->toDateString()]);
            if ($companyId !== null) {
                $vehicleExpenseBaseQuery->where('company_id', $companyId);
            }
            $fuelTypes = ['fuel', 'diesel', 'gasoline', 'refuel'];
            $maintenanceTypes = ['maintenance', 'preventive', 'repair'];

            $totalVehicleFuel = (float) (clone $vehicleExpenseBaseQuery)
                ->whereIn('type', $fuelTypes)
                ->sum('amount');
            $totalVehicleMaintenance = (float) (clone $vehicleExpenseBaseQuery)
                ->whereIn('type', $maintenanceTypes)
                ->sum('amount');
            $totalOverhead = (float) (clone $vehicleExpenseBaseQuery)
                ->whereNotIn('type', array_merge($fuelTypes, $maintenanceTypes))
                ->sum('amount');

            $companyTakeHomeRevenue = $crossMonthAllocated
                - $totalNetSalary
                - $totalVehicleFuel
                - $totalVehicleMaintenance
                - $totalOverhead;
            $roundedCompanyTakeHomeRevenue = round($companyTakeHomeRevenue, 2);

            $budget = (float) config('reports.company_take_home_budget', 0.0);
            $variance = $roundedCompanyTakeHomeRevenue - $budget;
            $variancePct = $budget != 0.0 ? round(($variance / $budget) * 100, 2) : null;

            return [
                'filters' => [
                    'company_id' => $companyId,
                    'from' => $fromDate->toDateString(),
                    'to' => $toDate->toDateString(),
                ],
                'period' => [
                    'month' => (int) $fromDate->format('m'),
                    'year' => (int) $fromDate->format('Y'),
                ],
                'revenue' => [
                    'total_invoiced' => round($totalInvoiced, 2),
                    'total_collected' => round($totalCollected, 2),
                    'cross_month_allocated' => round($crossMonthAllocated, 2),
                ],
                'costs' => [
                    'total_net_salary' => round($totalNetSalary, 2),
                    'total_vehicle_fuel' => round($totalVehicleFuel, 2),
                    'total_vehicle_maintenance' => round($totalVehicleMaintenance, 2),
                    'total_overhead' => round($totalOverhead, 2),
                ],
                'company_take_home_revenue' => $roundedCompanyTakeHomeRevenue,
                // Backward compatibility for existing clients.
                'operating_margin' => $roundedCompanyTakeHomeRevenue,
                '_deprecation_notice' => 'operating_margin will be removed in v2. Use company_take_home_revenue',
                'company_take_home_analysis' => [
                    'actual' => $roundedCompanyTakeHomeRevenue,
                    'budget' => round($budget, 2),
                    'variance' => round($variance, 2),
                    'variance_pct' => $variancePct,
                ],
                '_meta' => [
                    'formula' => 'cross_month_allocated - total_net_salary - total_vehicle_fuel - total_vehicle_maintenance - total_overhead',
                    'note' => 'Cross-month allocation and outsourced driver AP are pending dedicated data sources.',
                ],
            ];
        });

        return $data;
    }
}
