<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Payroll;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ReportService
{
    private function invoicesInMonth(?int $companyId, int $month, int $year): Builder
    {
        $q = Invoice::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
        if ($companyId !== null && $companyId > 0) {
            $q->where('company_id', $companyId);
        }

        return $q;
    }

    private function tripsInMonth(?int $companyId, int $month, int $year): Builder
    {
        $q = Trip::query()
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month);
        if ($companyId !== null && $companyId > 0) {
            $q->where('company_id', $companyId);
        }

        return $q;
    }

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
            $driversTotal = Driver::count();
            $driversActive = Driver::where('status', 'active')->count();
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
            // DB enum (e.g. ship_db dump): draft | approved | locked | paid — no pending/completed
            $payrollsPending = Payroll::where('status', 'draft')
                ->where('month', $month)
                ->where('year', $year)
                ->count();
            $payrollsCompleted = Payroll::whereIn('status', ['approved', 'locked', 'paid'])
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
                'drivers' => [
                    'total' => $driversTotal,
                    'active' => $driversActive,
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
            $payroll = Payroll::with('lines')
                ->where('company_id', $companyId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (! $payroll) {
                return null;
            }

            $lines = $payroll->lines;

            return [
                'payroll' => $payroll,
                'total_net' => $lines->sum('net_salary'),
                'drivers_count' => $lines->pluck('driver_id')->unique()->count(),
                'lines_count' => $lines->count(),
            ];
        });

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRevenueSnapshot(?int $companyId, int $month, int $year): array
    {
        $base = $this->invoicesInMonth($companyId, $month, $year);

        return [
            'month' => $month,
            'year' => $year,
            'invoiced_total' => (string) (clone $base)->sum('total_amount'),
            'paid_total' => (string) (clone $base)->where('status', 'paid')->sum('total_amount'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCostsSnapshot(?int $companyId, int $month, int $year): array
    {
        $tripQuery = $this->tripsInMonth($companyId, $month, $year);

        return [
            'month' => $month,
            'year' => $year,
            'trips_count' => $tripQuery->count(),
            'note' => 'Extend with trip-level cost allocation when available',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfitSnapshot(?int $companyId, int $month, int $year): array
    {
        $rev = $this->getRevenueSnapshot($companyId, $month, $year);
        $cost = $this->getCostsSnapshot($companyId, $month, $year);

        return [
            'month' => $month,
            'year' => $year,
            'revenue' => $rev,
            'costs' => $cost,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTripsReport(?int $companyId, int $month, int $year): array
    {
        $q = $this->tripsInMonth($companyId, $month, $year);

        return [
            'by_status' => $q->clone()
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDriversReport(?int $companyId): array
    {
        $q = Driver::query()->where('status', 'active');
        if ($companyId !== null && $companyId > 0) {
            $q->where('company_id', $companyId);
        }

        return [
            'active_drivers' => $q->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDebtReport(?int $companyId): array
    {
        $q = Invoice::query()->whereIn('status', ['draft', 'issued']);
        if ($companyId !== null && $companyId > 0) {
            $q->where('company_id', $companyId);
        }
        $row = $q->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as total')->first();

        return [
            'unpaid_invoices' => (int) ($row?->cnt ?? 0),
            'unpaid_total' => (string) ($row?->total ?? '0.00'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMaintenanceReport(?int $companyId): array
    {
        return ['schedules_due_within_30_days' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    public function getVehiclePerformance(?int $companyId, int $month, int $year): array
    {
        $q = $this->tripsInMonth($companyId, $month, $year)->whereNotNull('vehicle_id');

        return [
            'trips_with_vehicle' => $q->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportPayload(string $kind, ?int $companyId, int $month, int $year): array
    {
        return [
            'kind' => $kind,
            'month' => $month,
            'year' => $year,
            'company_id' => $companyId,
            'message' => 'Export queued / file generation not wired; use matching GET report for tabular JSON.',
        ];
    }
}
