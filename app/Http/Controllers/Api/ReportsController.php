<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Report\ReportService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportsController extends BaseController
{
    public function __construct(private readonly ReportService $reportService, private readonly TenantContext $tenantContext) {}

    public function report(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        $action = $request->route()?->getAction() ?? [];
        $reportType = $action['reportType'] ?? $request->query('type', 'dashboard');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        try {
            $data = $this->reportService->getReportData(
                (string) $reportType,
                $request->user()?->id ?? null,
                $dateFrom,
                $dateTo,
                $request->only(['status', 'cost_category_id'])
            );

            return $this->successResponse($data, 'api.common.ok');
        } catch (\Throwable $e) {
            return $this->handleException($e, 'api.reports.failed');
        }
    }
}
