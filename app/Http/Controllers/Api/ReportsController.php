<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Report\DashboardRequest;
use App\Http\Requests\Report\PayrollSummaryRequest;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @OA\Tag(name="Reports", description="Báo cáo tổng hợp")
 */
final class ReportsController extends BaseController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly ReportExportService $reportExportService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/reports/dashboard",
     *     tags={"Reports"},
     *     summary="Dashboard tổng hợp",
     *     description="Trả về số liệu tổng hợp (cached 1 giờ)",
     *     @OA\Parameter(name="month", in="query", description="Tháng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", description="Năm", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function dashboard(DashboardRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);
        $data = $this->reportService->getDashboardData($month, $year);

        return $this->successResponse($data, 'OK');
    }

    /**
     * @OA\Get(
     *     path="/api/reports/payroll-summary",
     *     tags={"Reports"},
     *     summary="Tổng hợp bảng lương theo công ty",
     *     description="Trả về chi tiết bảng lương công ty theo tháng/năm (cached 24h)",
     *     @OA\Parameter(name="company_id", in="query", required=true, description="ID công ty", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", description="Tháng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", description="Năm", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function payrollSummary(PayrollSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $companyId = (int) $validated['company_id'];
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);
        $data = $this->reportService->getPayrollSummaryData($companyId, $month, $year);

        return $this->successResponse($data, 'OK');
    }

    public function revenueSummary(Request $request): JsonResponse
    {
        $companyId = (int) $request->integer('company_id');
        $from = Carbon::parse((string) $request->query('from', now()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse((string) $request->query('to', now()->endOfMonth()->toDateString()))->endOfDay();

        $totalInvoiced = (float) DB::table('invoices')
            ->join('trips', 'trips.id', '=', 'invoices.trip_id')
            ->where('trips.company_id', $companyId)
            ->whereBetween('invoices.issued_at', [$from, $to])
            ->sum('total_amount');

        $totalCollected = (float) DB::table('invoices')
            ->join('trips', 'trips.id', '=', 'invoices.trip_id')
            ->where('trips.company_id', $companyId)
            ->where('invoices.status', 'paid')
            ->whereBetween('invoices.paid_at', [$from, $to])
            ->sum('total_amount');

        $crossMonthAllocated = $totalCollected;

        $totalNetSalary = (float) DB::table('payroll_lines')
            ->where('company_id', $companyId)
            ->sum('net_salary');

        $totalVehicleFuel = (float) DB::table('vehicle_expenses')
            ->where('company_id', $companyId)
            ->where('type', 'fuel')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $totalVehicleMaintenance = (float) DB::table('vehicle_expenses')
            ->where('company_id', $companyId)
            ->where('type', 'maintenance')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $totalOverhead = 0.0;
        $operatingMargin = $crossMonthAllocated - $totalNetSalary - $totalVehicleFuel - $totalVehicleMaintenance - $totalOverhead;

        return $this->successResponse([
            'revenue' => [
                'total_invoiced' => $totalInvoiced,
                'total_collected' => $totalCollected,
                'cross_month_allocated' => $crossMonthAllocated,
            ],
            'costs' => [
                'total_net_salary' => $totalNetSalary,
                'total_vehicle_fuel' => $totalVehicleFuel,
                'total_vehicle_maintenance' => $totalVehicleMaintenance,
                'total_overhead' => $totalOverhead,
            ],
            'company_take_home_revenue' => $operatingMargin,
            'operating_margin' => $operatingMargin,
            '_deprecation_notice' => 'operating_margin will be removed in v2. Use company_take_home_revenue',
            'company_take_home_analysis' => [
                'actual' => $operatingMargin,
                'budget' => 0,
                'variance' => $operatingMargin,
                'variance_pct' => null,
            ],
            '_meta' => [
                'formula' => 'cross_month_allocated - total_net_salary - total_vehicle_fuel - total_vehicle_maintenance - total_overhead',
            ],
        ], 'OK');
    }

    public function exportRevenue(Request $request): StreamedResponse
    {
        $companyId = $request->query('company_id') !== null ? (int) $request->query('company_id') : null;
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = Carbon::create($year, $month, 1)->endOfMonth();

        return $this->reportExportService->streamRevenueCsv($companyId, $from, $to);
    }

    public function exportRevenueExcel(Request $request): StreamedResponse
    {
        $companyId = $request->query('company_id') !== null ? (int) $request->query('company_id') : null;
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = Carbon::create($year, $month, 1)->endOfMonth();

        return $this->reportExportService->streamRevenueExcel($companyId, $from, $to);
    }

    public function exportTrips(Request $request): StreamedResponse
    {
        $companyId = $request->query('company_id') !== null ? (int) $request->query('company_id') : null;
        $from = Carbon::parse((string) $request->query('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse((string) $request->query('to', now()->endOfMonth()->toDateString()));
        $status = $request->query('status');
        $statuses = is_string($status) && $status !== '' ? [$status] : null;

        return $this->reportExportService->streamTripsCsv($companyId, $from, $to, $statuses);
    }

    public function exportPayroll(Request $request): StreamedResponse|JsonResponse
    {
        $companyId = (int) $request->integer('company_id');
        $month = (int) $request->integer('month', now()->month);
        $year = (int) $request->integer('year', now()->year);

        $exists = DB::table('payrolls')
            ->where('company_id', $companyId)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();

        if (! $exists) {
            return $this->notFoundResponse('Payroll not found');
        }

        return $this->reportExportService->streamPayrollCsv($companyId, $month, $year);
    }

    public function vehiclePerformance(Request $request): JsonResponse
    {
        $companyId = (int) $request->integer('company_id');
        $from = Carbon::parse((string) $request->query('from', now()->startOfMonth()->toDateString()));
        $to = Carbon::parse((string) $request->query('to', now()->endOfMonth()->toDateString()));

        $rows = $this->reportService->getVehiclePerformanceData($companyId, $from, $to);

        return $this->successResponse([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
        ], 'OK');
    }
}
