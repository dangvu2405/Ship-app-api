<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\CostApprovalRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CostApprovalController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $query = CostApprovalRequest::query()
            ->with('trip:id,code')
            ->where('company_id', $this->companyId($request))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id');

        return $this->successResponse($query->paginate(min(max($request->integer('per_page', 15), 1), 100)), 'OK');
    }

    public function approve(Request $request, CostApprovalRequest $costApproval): JsonResponse
    {
        return $this->review($request, $costApproval, 'approved');
    }

    public function reject(Request $request, CostApprovalRequest $costApproval): JsonResponse
    {
        $request->validate(['review_note' => ['nullable', 'string']]);

        return $this->review($request, $costApproval, 'rejected');
    }

    private function review(Request $request, CostApprovalRequest $costApproval, string $status): JsonResponse
    {
        if ((int) $costApproval->company_id !== $this->companyId($request)) {
            return $this->forbiddenResponse();
        }

        $costApproval->update([
            'status' => $status,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
            'review_note' => $request->input('review_note'),
        ]);

        return $this->successResponse($costApproval->refresh()->load('trip:id,code'), 'OK');
    }

    private function companyId(Request $request): int
    {
        return (int) ($this->tenantContext->getCompanyId() ?? $request->user()?->getAttribute('company_id'));
    }
}
