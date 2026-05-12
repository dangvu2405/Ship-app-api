<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PayrollsController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('payrolls')) {
            return $this->successResponse([], 'api.common.ok');
        }

        $rows = DB::table('payrolls')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->successResponse($rows, 'api.common.ok');
    }

    public function generate(Request $request): JsonResponse
    {
        // Minimal non-breaking implementation: schedule generation or return placeholder result.
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        // In real impl, dispatch a Job. For now return a generated file URL placeholder.
        $result = [
            'generated' => true,
            'file_url' => url('/storage/exports/payroll_'.now()->format('Y_m').'.xlsx'),
            'generated_at' => now(),
        ];

        return $this->successResponse($result, 'api.common.ok');
    }

    public function export(Request $request): JsonResponse
    {
        // Return latest payroll export metadata (placeholder)
        $result = [
            'file_url' => url('/storage/exports/payroll_export_latest.xlsx'),
            'status' => 'available',
        ];

        return $this->successResponse($result, 'api.common.ok');
    }

    public function mySalary(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        // Best-effort: fetch last payroll lines for user if table exists
        if (! \Illuminate\Support\Facades\Schema::hasTable('payroll_lines')) {
            return $this->successResponse(['salary' => null], 'api.common.ok');
        }

        $line = DB::table('payroll_lines')
            ->where('employee_id', $user->id)
            ->orderByDesc('id')
            ->first();

        return $this->successResponse(['salary' => $line], 'api.common.ok');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        // placeholder: mark approved
        return $this->successResponse(['id' => $id, 'approved' => true], 'api.common.ok');
    }

    public function lock(Request $request, int $id): JsonResponse
    {
        // placeholder: mark locked
        return $this->successResponse(['id' => $id, 'locked' => true], 'api.common.ok');
    }
}
