<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Report\DashboardRequest;
use App\Http\Requests\Report\PayrollSummaryRequest;
use App\Http\Requests\Report\RevenueSummaryRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(name="Reports", description="Báo cáo tổng hợp")
 */
class ReportsController extends BaseController
{
    public function __construct(private readonly ReportService $reportService) {}

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

        return $this->successResponse($data, 'api.common.ok');
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

        return $this->successResponse($data, 'api.common.ok');
    }

    public function revenueSummary(RevenueSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $companyId = isset($validated['company_id']) ? (int) $validated['company_id'] : null;
        $from = (string) $validated['from'];
        $to = (string) $validated['to'];
        $data = $this->reportService->getRevenueSummaryData($companyId, $from, $to);

        return $this->successResponse($data, 'api.common.ok');
    }
}
