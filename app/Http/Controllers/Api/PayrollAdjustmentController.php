<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\PayrollAdjustment;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PayrollAdjustmentController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return $this->successResponse($this->emptyPaginatedData($request), 'OK');
        }

        $query = PayrollAdjustment::query()
            ->with(['payroll', 'driver', 'approvedBy'])
            ->where('company_id', $this->getCompanyId());

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        $perPage = $this->perPage($request);
        $adjustments = $query->paginate($perPage);

        return $this->successResponse($adjustments, 'OK');
    }

    public function allowances(Request $request): JsonResponse
    {
        $request->merge(['type' => 'addition']);

        return $this->index($request);
    }

    public function deductions(Request $request): JsonResponse
    {
        $request->merge(['type' => 'deduction']);

        return $this->index($request);
    }

    public function store(Request $request): JsonResponse
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return $this->errorResponse('Payroll adjustments table is not available.', 501);
        }

        $validated = $request->validate([
            'payroll_id' => 'required|exists:payrolls,id',
            'driver_id' => 'required|exists:drivers,id',
            'type' => 'required|in:addition,deduction',
            'category' => 'nullable|in:violation_refund,leave_restore,ot_late_approval,manual',
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'original_payroll_id' => 'nullable|exists:payrolls,id',
        ]);

        $validated['company_id'] = $this->getCompanyId();
        $validated['approved_by'] = $request->user()?->id;

        $adjustment = PayrollAdjustment::create($validated);

        return $this->successResponse($adjustment->load(['payroll', 'driver']), 'Adjustment created successfully', 201);
    }

    public function show(string $id): JsonResponse
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment = PayrollAdjustment::with(['payroll', 'driver', 'approvedBy'])->find($id);

        if (! $adjustment || $adjustment->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Adjustment not found');
        }

        return $this->successResponse($adjustment);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment = PayrollAdjustment::find($id);

        if (! $adjustment || $adjustment->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $validated = $request->validate([
            'type' => 'sometimes|required|in:addition,deduction',
            'category' => 'sometimes|nullable|in:violation_refund,leave_restore,ot_late_approval,manual',
            'amount' => 'sometimes|required|numeric|min:0',
            'reason' => 'sometimes|required|string',
        ]);

        $adjustment->update($validated);

        return $this->successResponse($adjustment->fresh(['payroll', 'driver']), 'Adjustment updated successfully');
    }

    public function destroy(string $id): JsonResponse
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment = PayrollAdjustment::find($id);

        if (! $adjustment || $adjustment->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment->delete();

        return $this->successResponse(null, 'Adjustment deleted successfully');
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment = PayrollAdjustment::find($id);

        if (! $adjustment || $adjustment->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment->update([
            'status' => 'approved',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
        ]);

        return $this->successResponse($adjustment->fresh(['payroll', 'driver', 'approvedBy']), 'Adjustment approved successfully');
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        if (! Schema::hasTable('payroll_adjustments')) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment = PayrollAdjustment::find($id);

        if (! $adjustment || $adjustment->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $adjustment->update([
            'status' => 'rejected',
            'approved_by' => $request->user()?->id,
            'approved_at' => now(),
            'reject_reason' => $validated['reason'] ?? null,
        ]);

        return $this->successResponse($adjustment->fresh(['payroll', 'driver', 'approvedBy']), 'Adjustment rejected');
    }

    private function getCompanyId(): ?int
    {
        return app(TenantContext::class)->getCompanyId();
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->input('per_page', 15);

        return min(max($perPage, 1), 100);
    }

    /**
     * The payroll adjustment module was removed in some migrated databases.
     * Returning the standard empty paginator keeps read-only screens usable without hiding real schema drift from writes.
     *
     * @return array{data: array<int, never>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}
     */
    private function emptyPaginatedData(Request $request): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $this->perPage($request),
                'total' => 0,
            ],
        ];
    }
}
