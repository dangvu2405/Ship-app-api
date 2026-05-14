<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReportService extends BaseService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getReportData(string $type, ?int $userId = null, ?string $dateFrom = null, ?string $dateTo = null, array $filters = []): array
    {
        $companyId = $this->companyId();

        // Date range validation
        if ($dateFrom && $dateTo && strtotime($dateFrom) > strtotime($dateTo)) {
            throw new \InvalidArgumentException('date_from must be before date_to');
        }

        return match ($type) {
            'dashboard' => $this->getDashboardReport($companyId),
            'revenue' => $this->getRevenueReport($companyId, $dateFrom, $dateTo),
            'costs' => $this->getCostsReport($companyId, $dateFrom, $dateTo, $filters),
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
        $revenueColumn = $this->tripRevenueColumn();

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $totalRevenue = $revenueColumn !== null ? (float) ((clone $query)->sum($revenueColumn) ?? 0) : 0.0;
        $tripsCount = (clone $query)->count();

        return [
            'type' => 'revenue',
            'total_revenue' => $totalRevenue,
            'trips_count' => $tripsCount,
            'average_revenue_per_trip' => $tripsCount > 0 ? round($totalRevenue / $tripsCount, 2) : 0,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function getCostsReport(int $companyId, ?string $dateFrom = null, ?string $dateTo = null, array $filters = []): array
    {
        if (! Schema::hasTable('trip_costs')) {
            return [
                'type' => 'costs',
                'total_costs' => 0,
                'costs_count' => 0,
                'approved_costs' => 0,
                'pending_costs' => 0,
                'rejected_costs' => 0,
                'rows' => [],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];
        }

        $base = DB::table('trip_costs as tc')
            ->leftJoin('trips as t', 't.id', '=', 'tc.trip_id')
            ->leftJoin('vehicles as v', 'v.id', '=', 't.vehicle_id')
            ->leftJoin('drivers as d', 'd.id', '=', 't.driver_id')
            ->leftJoin('cost_categories as cc', 'cc.id', '=', 'tc.cost_category_id')
            ->where('tc.company_id', $companyId);

        if (Schema::hasColumn('trip_costs', 'deleted_at')) {
            $base->whereNull('tc.deleted_at');
        }

        if ($dateFrom) {
            $base->whereDate('tc.incurred_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $base->whereDate('tc.incurred_date', '<=', $dateTo);
        }
        if (! empty($filters['status'])) {
            $base->where('tc.status', (string) $filters['status']);
        }
        if (! empty($filters['cost_category_id'])) {
            $base->where('tc.cost_category_id', (int) $filters['cost_category_id']);
        }

        $rows = (clone $base)
            ->select([
                'tc.id',
                'tc.trip_id',
                't.code as trip_code',
                't.vehicle_id',
                'v.plate_number as vehicle_plate_number',
                't.driver_id',
                'd.name as driver_name',
                'tc.cost_category_id',
                'cc.name as cost_category_name',
                'tc.amount',
                'tc.norm_amount',
                'tc.incurred_date',
                'tc.status',
                'tc.approval_required',
                'tc.description',
                'tc.notes',
                'tc.created_at',
            ])
            ->orderByDesc('tc.incurred_date')
            ->orderByDesc('tc.id')
            ->limit(500)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'trip_id' => (int) $row->trip_id,
                'trip_code' => $row->trip_code,
                'vehicle_id' => $row->vehicle_id !== null ? (int) $row->vehicle_id : null,
                'vehicle_plate_number' => $row->vehicle_plate_number,
                'driver_id' => $row->driver_id !== null ? (int) $row->driver_id : null,
                'driver_name' => $row->driver_name,
                'cost_category_id' => (int) $row->cost_category_id,
                'cost_category_name' => $row->cost_category_name,
                'amount' => (float) $row->amount,
                'norm_amount' => $row->norm_amount !== null ? (float) $row->norm_amount : null,
                'incurred_date' => $row->incurred_date,
                'status' => $row->status,
                'approval_required' => (bool) $row->approval_required,
                'description' => $row->description,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
            ])
            ->all();

        return [
            'type' => 'costs',
            'total_costs' => (float) ((clone $base)->sum('tc.amount') ?? 0),
            'costs_count' => (clone $base)->count(),
            'approved_costs' => (float) ((clone $base)->where('tc.status', 'approved')->sum('tc.amount') ?? 0),
            'pending_costs' => (float) ((clone $base)->where('tc.status', 'pending')->sum('tc.amount') ?? 0),
            'rejected_costs' => (float) ((clone $base)->where('tc.status', 'rejected')->sum('tc.amount') ?? 0),
            'rows' => $rows,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
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
        if (! Schema::hasTable('debt_overviews')) {
            return [
                'type' => 'debt',
                'total_debt' => 0,
                'debt_count' => 0,
                'rows' => [],
            ];
        }

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
        $licenseExpiryColumn = Schema::hasColumn('drivers', 'expired_date')
            ? 'expired_date'
            : (Schema::hasColumn('drivers', 'license_expiry_date') ? 'license_expiry_date' : null);

        return [
            'type' => 'maintenance',
            'vehicles_due_maintenance' => DB::table('vehicles')
                ->where('company_id', $companyId)
                ->where('status', 'maintenance')
                ->count(),
            'drivers_expiring_docs' => $licenseExpiryColumn !== null
                ? DB::table('drivers')
                    ->where('company_id', $companyId)
                    ->whereDate($licenseExpiryColumn, '<', now()->addDays(30)->toDateString())
                    ->count()
                : 0,
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

    private function tripRevenueColumn(): ?string
    {
        foreach (['total_revenue', 'base_price', 'price', 'fare_amount'] as $column) {
            if (Schema::hasColumn('trips', $column)) {
                return $column;
            }
        }

        return null;
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
