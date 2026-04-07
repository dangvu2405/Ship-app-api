<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\Trip;
use App\Models\Vehicle;
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
            $employeesTotal = Employee::count();
            $employeesActive = Employee::where('status', 'active')->count();
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
            $payroll = Payroll::with('details.employee')
                ->where('company_id', $companyId)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            if (! $payroll) {
                return null;
            }

            return [
                'payroll' => $payroll,
                'total_net' => $payroll->details->sum('net_salary'),
                'employees_count' => $payroll->details->count(),
            ];
        });

        return $data;
    }
}
