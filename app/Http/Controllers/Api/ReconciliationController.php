<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\ReconciliationItem;
use App\Models\ReconciliationSession;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();

        $sessions = ReconciliationSession::query()
            ->where('company_id', $companyId)
            ->latest('id')
            ->paginate((int) $request->input('per_page', 15));

        return $this->successResponse($sessions, 'api.common.ok');
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'period_from' => 'required|date',
            'period_to'   => 'required|date|after_or_equal:period_from',
            'notes'       => 'nullable|string',
        ]);

        $session = DB::transaction(function () use ($validated, $companyId) {
            return ReconciliationSession::create(array_merge($validated, [
                'company_id' => $companyId,
                'status'     => 'draft',
            ]));
        });

        return response()->json(['data' => $session], 201);
    }

    public function show(ReconciliationSession $reconciliation): JsonResponse
    {
        $this->abortIfWrongTenant($reconciliation->company_id);

        return $this->successResponse(
            $reconciliation->load('items', 'customer'),
            'api.common.ok'
        );
    }

    public function update(Request $request, ReconciliationSession $reconciliation): JsonResponse
    {
        $this->abortIfWrongTenant($reconciliation->company_id);

        if ($reconciliation->status === 'locked') {
            return response()->json(['message' => 'Không thể chỉnh sửa phiên đối soát đã khoá (R07).'], 422);
        }

        $validated = $request->validate([
            'period_from' => 'sometimes|date',
            'period_to'   => 'sometimes|date',
            'notes'       => 'sometimes|nullable|string',
        ]);

        $reconciliation->update($validated);

        return $this->successResponse($reconciliation, 'api.common.ok');
    }

    public function destroy(ReconciliationSession $reconciliation): JsonResponse
    {
        $this->abortIfWrongTenant($reconciliation->company_id);

        if ($reconciliation->status === 'locked') {
            return response()->json(['message' => 'Không thể xoá phiên đối soát đã khoá (R07).'], 422);
        }

        $reconciliation->delete();

        return response()->json(null, 204);
    }

    public function items(ReconciliationSession $reconciliation): JsonResponse
    {
        $this->abortIfWrongTenant($reconciliation->company_id);

        return $this->successResponse(
            $reconciliation->items()->paginate(50),
            'api.common.ok'
        );
    }

    public function updateItem(Request $request, ReconciliationSession $reconciliation, ReconciliationItem $item): JsonResponse
    {
        $this->abortIfWrongTenant($reconciliation->company_id);

        if ($reconciliation->status === 'locked') {
            return response()->json(['message' => 'Không thể chỉnh sửa item trong phiên đối soát đã khoá (R07).'], 422);
        }

        $validated = $request->validate([
            'amount'   => 'sometimes|numeric|min:0',
            'notes'    => 'sometimes|nullable|string',
            'status'   => 'sometimes|string|in:pending,matched,disputed',
        ]);

        $item->update($validated);

        return $this->successResponse($item, 'api.common.ok');
    }

    public function confirm(ReconciliationSession $reconciliation): JsonResponse
    {
        $this->abortIfWrongTenant($reconciliation->company_id);

        if (! in_array($reconciliation->status, ['draft', 'pending'], true)) {
            return response()->json(['message' => 'Chỉ có thể xác nhận phiên ở trạng thái draft hoặc pending.'], 422);
        }

        $reconciliation->update(['status' => 'confirmed']);

        return $this->successResponse($reconciliation->fresh(), 'api.common.ok');
    }

    public function lock(ReconciliationSession $reconciliation): JsonResponse
    {
        $this->abortIfWrongTenant($reconciliation->company_id);

        if ($reconciliation->status !== 'confirmed') {
            return response()->json(['message' => 'Chỉ có thể khoá phiên đã được xác nhận.'], 422);
        }

        $reconciliation->update(['status' => 'locked']);

        return $this->successResponse($reconciliation->fresh(), 'api.common.ok');
    }

    private function abortIfWrongTenant(?int $resourceCompanyId): void
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($resourceCompanyId !== $companyId) {
            abort(404);
        }
    }
}
