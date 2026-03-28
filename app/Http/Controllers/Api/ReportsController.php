<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Report\PayrollSummaryRequest;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(name="Reports", description="Báo cáo tổng hợp")
 */
class ReportsController extends BaseController
{
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
     * @OA\Get(
     *     path="/api/dashboard/stats",
     *     tags={"Reports", "Dashboard"},
     *     summary="Frontend Dashboard Stats",
     *     description="Trả về thống kê cho Dashboard UI",
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function dashboardStats(): JsonResponse
    {
        $key = "dashboard:stats";

        $data = Cache::remember($key, 300, function () {
            return [
                'companies' => [
                    'total' => Company::count(),
                    'active' => Company::where('status', 'active')->count(),
                ],
                'employees' => [
                    'total' => Employee::count(),
                    'active' => Employee::where('status', 'active')->count(),
                ],
                'vehicles' => [
                    'total' => Vehicle::count(),
                    'active' => Vehicle::where('status', 'active')->count(),
                ],
                'trips' => [
                    'total' => Trip::count(),
                    'pending' => Trip::where('status', 'pending')->count(),
                    'completed' => Trip::where('status', 'completed')->count(),
                ],
                'payrolls' => [
                    'total' => Payroll::count(),
                    'pending' => Payroll::where('status', 'pending')->count(),
                    'completed' => Payroll::where('status', 'locked')->count(),
                ],
            ];
        });

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
