<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Models\Payroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReportsController extends BaseController
{
    /**
     * Dashboard summary (cached 1 hour).
     * GET /reports/dashboard?month=&year=
     */
    public function dashboard(Request $request): JsonResponse
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $key = "report:dashboard:{$month}:{$year}";

        $data = Cache::remember($key, 3600, function () use ($month, $year) {
            return [
                'companies_count' => Company::count(),
                'payrolls_count' => Payroll::where('month', $month)->where('year', $year)->count(),
            ];
        });

        return $this->successResponse($data, 'OK');
    }

    /**
     * Payroll summary by company/month/year (cached 24h).
     * GET /reports/payroll-summary?company_id=&month=&year=
     */
    public function payrollSummary(Request $request): JsonResponse
    {
        $companyId = (int) $request->input('company_id');
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $key = "payroll:{$companyId}:{$month}:{$year}";

        $data = Cache::remember($key, 86400, function () use ($companyId, $month, $year) {
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

        return $this->successResponse($data, 'OK');
    }
}
