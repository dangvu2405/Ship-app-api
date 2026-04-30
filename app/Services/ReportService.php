<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Payroll;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
                'employees_count' => $payroll->lines->count(),
            ];
        });

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getVehiclePerformanceData(int $companyId, Carbon $from, Carbon $to): array
    {
        return DB::table('trips')
            ->join('vehicles', 'vehicles.id', '=', 'trips.vehicle_id')
            ->where('trips.company_id', $companyId)
            ->whereBetween('trips.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->groupBy('trips.vehicle_id', 'vehicles.plate_number')
            ->selectRaw('
                trips.vehicle_id,
                vehicles.plate_number,
                COUNT(*) as trips_count,
                SUM(COALESCE(trips.actual_distance_km, trips.distance_km, 0)) as total_distance_km,
                SUM(COALESCE(trips.total_revenue, 0)) as total_revenue
            ')
            ->orderByDesc('total_revenue')
            ->get()
            ->map(static fn ($row): array => [
                'vehicle_id' => (int) $row->vehicle_id,
                'plate_number' => (string) $row->plate_number,
                'trips_count' => (int) $row->trips_count,
                'total_distance_km' => (float) $row->total_distance_km,
                'total_revenue' => (float) $row->total_revenue,
            ])
            ->all();
    }
}
