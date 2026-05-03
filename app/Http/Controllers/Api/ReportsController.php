<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Report\DashboardRequest;
use App\Http\Requests\Report\PayrollSummaryRequest;
use App\Services\ReportService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Reports", description="Báo cáo tổng hợp")
 */
class ReportsController extends BaseController
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly TenantContext $tenantContext,
    ) {}

    public function dashboard(DashboardRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);
        $data = $this->reportService->getDashboardData($month, $year);

        return $this->successResponse($data, 'OK');
    }

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
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getRevenueSnapshot($cid > 0 ? $cid : null, $month, $year),
            'OK'
        );
    }

    public function revenue(Request $request): JsonResponse
    {
        return $this->revenueSummary($request);
    }

    public function costs(Request $request): JsonResponse
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getCostsSnapshot($cid > 0 ? $cid : null, $month, $year),
            'OK'
        );
    }

    public function profit(Request $request): JsonResponse
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getProfitSnapshot($cid > 0 ? $cid : null, $month, $year),
            'OK'
        );
    }

    public function tripsReport(Request $request): JsonResponse
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getTripsReport($cid > 0 ? $cid : null, $month, $year),
            'OK'
        );
    }

    public function driversReport(Request $request): JsonResponse
    {
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getDriversReport($cid > 0 ? $cid : null),
            'OK'
        );
    }

    public function debt(Request $request): JsonResponse
    {
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getDebtReport($cid > 0 ? $cid : null),
            'OK'
        );
    }

    public function maintenance(Request $request): JsonResponse
    {
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getMaintenanceReport($cid > 0 ? $cid : null),
            'OK'
        );
    }

    public function vehiclePerformance(Request $request): JsonResponse
    {
        $month = (int) $request->query('month', now()->month);
        $year = (int) $request->query('year', now()->year);
        $cid = $this->tenantContext->getCompanyId();

        return $this->successResponse(
            $this->reportService->getVehiclePerformance($cid > 0 ? $cid : null, $month, $year),
            'OK'
        );
    }

    public function export(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'string', 'max:64'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
        ]);
        $month = (int) ($data['month'] ?? now()->month);
        $year = (int) ($data['year'] ?? now()->year);
        $cid = $this->tenantContext->getCompanyId();
        $payload = $this->reportService->exportPayload($data['kind'], $cid > 0 ? $cid : null, $month, $year);

        return $this->successResponse($payload, 'OK', 202);
    }

    public function exportRevenue(Request $request): JsonResponse
    {
        return $this->export($request->merge(['kind' => 'revenue']));
    }

    public function exportRevenueExcel(Request $request): JsonResponse
    {
        return $this->export($request->merge(['kind' => 'revenue_excel']));
    }

    public function exportTrips(Request $request): JsonResponse
    {
        return $this->export($request->merge(['kind' => 'trips']));
    }

    public function exportPayroll(Request $request): JsonResponse
    {
        return $this->export($request->merge(['kind' => 'payroll']));
    }
}
