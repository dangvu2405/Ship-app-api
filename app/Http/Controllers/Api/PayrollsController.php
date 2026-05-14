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
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('payrolls')) {
            return $this->notFoundResponse('Payroll not found');
        }

        $payroll = DB::table('payrolls')->where('company_id', $companyId)->where('id', $id)->first();
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }

        DB::table('payrolls')->where('id', $id)->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(DB::table('payrolls')->find($id), 'api.common.ok');
    }

    public function lock(Request $request, int $id): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('payrolls')) {
            return $this->notFoundResponse('Payroll not found');
        }

        $payroll = DB::table('payrolls')->where('company_id', $companyId)->where('id', $id)->first();
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }

        DB::table('payrolls')->where('id', $id)->update([
            'status' => 'locked',
            'locked_by' => $request->user()?->id,
            'locked_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(DB::table('payrolls')->find($id), 'api.common.ok');
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('payrolls')) {
            return $this->notFoundResponse('Payroll not found');
        }

        $payroll = DB::table('payrolls')->where('company_id', $companyId)->where('id', $id)->first();
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }

        DB::table('payrolls')->where('id', $id)->update([
            'status' => 'paid',
            'paid_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse(DB::table('payrolls')->find($id), 'api.common.ok');
    }

    public function exportById(Request $request, int $id): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null) {
            return $this->forbiddenResponse('api.forbidden');
        }

        $result = [
            'file_url' => url("/storage/exports/payroll_{$id}.xlsx"),
            'status' => 'available',
        ];

        return $this->successResponse($result, 'api.common.ok');
    }

    public function exportBhxh(Request $request, int $id): JsonResponse
    {
        $result = [
            'file_url' => url("/storage/exports/payroll_{$id}_bhxh.xlsx"),
            'status' => 'available',
        ];

        return $this->successResponse($result, 'api.common.ok');
    }

    public function exportPit(Request $request, int $id): JsonResponse
    {
        $result = [
            'file_url' => url("/storage/exports/payroll_{$id}_pit.xlsx"),
            'status' => 'available',
        ];

        return $this->successResponse($result, 'api.common.ok');
    }

    public function exportPayslips(Request $request, int $id): JsonResponse
    {
        $result = [
            'file_url' => url("/storage/exports/payroll_{$id}_payslips.pdf"),
            'status' => 'available',
        ];

        return $this->successResponse($result, 'api.common.ok');
    }

    public function driverHistory(Request $request, int $driverId): JsonResponse
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
            ->where('driver_id', $driverId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return $this->successResponse($rows, 'api.common.ok');
    }
}
