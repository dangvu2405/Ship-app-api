<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\PayrollAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollAdjustmentController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = PayrollAdjustment::query()
            ->with(['payroll', 'driver', 'approvedBy'])
            ->where('company_id', $this->getCompanyId());

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->input('driver_id'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $adjustments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $adjustments->items(),
            'meta' => [
                'current_page' => $adjustments->currentPage(),
                'last_page' => $adjustments->lastPage(),
                'per_page' => $adjustments->perPage(),
                'total' => $adjustments->total(),
            ],
        ]);
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
        $adjustment = PayrollAdjustment::with(['payroll', 'driver', 'approvedBy'])->find($id);

        if (!$adjustment || $adjustment->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Adjustment not found');
        }

        return $this->successResponse($adjustment);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $adjustment = PayrollAdjustment::find($id);

        if (!$adjustment || $adjustment->company_id !== $this->getCompanyId()) {
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
        $adjustment = PayrollAdjustment::find($id);

        if (!$adjustment || $adjustment->company_id !== $this->getCompanyId()) {
            return $this->notFoundResponse('Adjustment not found');
        }

        $adjustment->delete();

        return $this->successResponse(null, 'Adjustment deleted successfully');
    }

    private function getCompanyId(): ?int
    {
        return app(\App\Tenancy\TenantContext::class)->getCompanyId();
    }
}
