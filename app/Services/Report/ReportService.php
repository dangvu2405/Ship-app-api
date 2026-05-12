<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReportService extends BaseService
{
    public function getReportData(string $type, ?int $userId = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $companyId = $this->companyId();
        
        // Date range validation
        if ($dateFrom && $dateTo && strtotime($dateFrom) > strtotime($dateTo)) {
            throw new \InvalidArgumentException('date_from must be before date_to');
        }

        return match ($type) {
            'dashboard' => $this->getDashboardReport($companyId),
            'revenue' => $this->getRevenueReport($companyId, $dateFrom, $dateTo),
            'costs' => $this->getCostsReport($companyId, $dateFrom, $dateTo),
            'profit' => $this->getProfitReport($companyId, $dateFrom, $dateTo),
            'trips' => $this->getTripsReport($companyId, $dateFrom, $dateTo),
            'vehicles' => $this->getVehiclesReport($companyId),
            'drivers' => $this->getDriversReport($companyId),
            'debt' => $this->getDebtReport($companyId),
            'maintenance' => $this->getMaintenanceReport($companyId),
            'export' => $this->getExportReport($companyId, $dateFrom, $dateTo),
            'payroll-export' => $this->getPayrollExportReport($companyId),
            'notifications-unread' => $userId ? ['unread_count' => $this->getUnreadNotificationsCount($userId)] : ['unread_count' => 0],
            default => ['type' => $type, 'status' => 'unknown_report_type'],
        };
    }

    private function getDashboardReport(int $companyId): array
    {
        return [
            'type' => 'dashboard',
            'summary' => [
                'total_trips' => $this->countTrips($companyId),
                'total_vehicles' => $this->countVehicles($companyId),
                'total_drivers' => $this->countDrivers($companyId),
                'total_customers' => $this->countCustomers($companyId),
                'pending_trips' => $this->countTripsWithStatus($companyId, 'pending'),
                'in_progress_trips' => $this->countTripsWithStatus($companyId, 'in_progress'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function getRevenueReport(int $companyId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('trips')->where('company_id', $companyId);
        
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return [
            'type' => 'revenue',
            'total_revenue' => $query->sum('fare_amount') ?? 0,
            'trips_count' => $query->count(),
            'average_revenue_per_trip' => $query->avg('fare_amount') ?? 0,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    private function getCostsReport(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = DB::table('cost_approvals')->where('company_id', $companyId);
        
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return [
            'type' => 'costs',
            'total_costs' => $query->sum('amount') ?? 0,
            'costs_count' => $query->count(),
            'approved_costs' => $query->where('status', 'approved')->sum('amount') ?? 0,
            'pending_costs' => $query->where('status', 'pending')->sum('amount') ?? 0,
        ];
    }

    private function getProfitReport(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $revenue = $this->getRevenueReport($companyId, $dateFrom, $dateTo);
        $costs = $this->getCostsReport($companyId, $dateFrom, $dateTo);
        $profit = ($revenue['total_revenue'] ?? 0) - ($costs['total_costs'] ?? 0);

        return [
            'type' => 'profit',
            'revenue' => $revenue['total_revenue'] ?? 0,
            'costs' => $costs['total_costs'] ?? 0,
            'profit' => $profit,
            'profit_margin_percent' => ($revenue['total_revenue'] ?? 0) > 0 ? round(($profit / $revenue['total_revenue']) * 100, 2) : 0,
        ];
    }

    private function getTripsReport(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = DB::table('trips')->where('company_id', $companyId);
        
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return [
            'type' => 'trips',
            'total' => $query->count(),
            'completed' => (clone $query)->where('status', 'completed')->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'in_progress' => (clone $query)->where('status', 'in_progress')->count(),
            'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
        ];
    }

    private function getVehiclesReport(int $companyId): array
    {
        $query = DB::table('vehicles')->where('company_id', $companyId);

        return [
            'type' => 'vehicles',
            'total' => $query->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'maintenance' => (clone $query)->where('status', 'maintenance')->count(),
            'inactive' => (clone $query)->where('status', 'inactive')->count(),
        ];
    }

    private function getDriversReport(int $companyId): array
    {
        $query = DB::table('drivers')->where('company_id', $companyId);

        return [
            'type' => 'drivers',
            'total' => $query->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'on_leave' => (clone $query)->where('status', 'on_leave')->count(),
            'inactive' => (clone $query)->where('status', 'inactive')->count(),
        ];
    }

    private function getDebtReport(int $companyId): array
    {
        return [
            'type' => 'debt',
            'total_debt' => DB::table('debt_overviews')
                ->where('company_id', $companyId)
                ->sum('outstanding_amount') ?? 0,
            'debt_count' => DB::table('debt_overviews')
                ->where('company_id', $companyId)
                ->count(),
        ];
    }

    private function getMaintenanceReport(int $companyId): array
    {
        return [
            'type' => 'maintenance',
            'vehicles_due_maintenance' => DB::table('vehicles')
                ->where('company_id', $companyId)
                ->where('status', 'maintenance')
                ->count(),
            'drivers_expiring_docs' => DB::table('drivers')
                ->where('company_id', $companyId)
                ->whereDate('license_expiry_date', '<', now()->addDays(30)->toDateString())
                ->count(),
        ];
    }

    private function getExportReport(int $companyId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return [
            'type' => 'export',
            'file_url' => url('/storage/exports/report_export_'.now()->format('Y_m_d_His').'.xlsx'),
            'status' => 'ready',
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
        ];
    }

    private function getPayrollExportReport(int $companyId): array
    {
        return [
            'type' => 'payroll-export',
            'file_url' => url('/storage/exports/payroll_'.now()->format('Y_m').'.xlsx'),
            'status' => 'ready',
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function getUnreadNotificationsCount(int $userId): int
    {
        return DB::table('notifications')
            ->where('notifiable_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    private function countTrips(int $companyId): int
    {
        return Schema::hasTable('trips') ? DB::table('trips')->where('company_id', $companyId)->count() : 0;
    }

    private function countTripsWithStatus(int $companyId, string $status): int
    {
        return Schema::hasTable('trips') ? DB::table('trips')->where('company_id', $companyId)->where('status', $status)->count() : 0;
    }

    private function countVehicles(int $companyId): int
    {
        return Schema::hasTable('vehicles') ? DB::table('vehicles')->where('company_id', $companyId)->count() : 0;
    }

    private function countDrivers(int $companyId): int
    {
        return Schema::hasTable('drivers') ? DB::table('drivers')->where('company_id', $companyId)->count() : 0;
    }

    private function countCustomers(int $companyId): int
    {
        return Schema::hasTable('customers') ? DB::table('customers')->where('company_id', $companyId)->count() : 0;
    }

    public function getDispatchData(string $date): array
    {
        $companyId = $this->companyId();
        $trips = DB::table('trips')->where('company_id', $companyId)->whereDate('scheduled_date', $date)->get();

        return [
            'date' => $date,
            'trips' => $trips,
            'unassigned_trips' => $trips->whereNull('vehicle_id')->values(),
            'daily_summary' => [
                'total_trips' => $trips->count(),
                'unassigned' => $trips->whereNull('vehicle_id')->count(),
            ],
        ];
    }
}
