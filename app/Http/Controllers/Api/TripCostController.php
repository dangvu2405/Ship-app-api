<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\CostApprovalRequest;
use App\Models\CostCategory;
use App\Models\Trip;
use App\Models\TripCost;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TripCostController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $query = TripCost::query()
            ->with('category')
            ->where('company_id', $this->companyId($request))
            ->when($request->integer('trip_id') > 0, fn ($q) => $q->where('trip_id', $request->integer('trip_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id');

        return $this->successResponse($query->paginate($this->perPage($request)), 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trip_id' => ['nullable', 'integer'],
            'cost_category_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0'],
            'norm_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'receipt_file' => ['nullable', 'file', 'max:10240'],
            'incurred_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $companyId = $this->companyId($request);
        $tripId = $request->integer('trip_id') ?: (int) ($validated['trip_id'] ?? 0);
        $trip = Trip::query()->where('company_id', $companyId)->find($tripId);
        if (! $trip) {
            return $this->validationErrorResponse(['trip_id' => ['Trip is invalid.']]);
        }

        $category = CostCategory::query()->where('company_id', $companyId)->find($validated['cost_category_id']);
        if (! $category) {
            return $this->validationErrorResponse(['cost_category_id' => ['Cost category is invalid.']]);
        }

        $amount = (float) $validated['amount'];
        $approvalRequired = $category->approval_threshold !== null && $amount > (float) $category->approval_threshold;
        $receiptUrl = $request->hasFile('receipt_file')
            ? $request->file('receipt_file')?->store('trip-cost-receipts', 'public')
            : null;

        $cost = TripCost::query()->create(array_merge($validated, [
            'company_id' => $companyId,
            'trip_id' => $trip->id,
            'receipt_file_url' => $receiptUrl,
            'status' => $approvalRequired ? 'pending' : 'approved',
            'approval_required' => $approvalRequired,
        ]));

        if ($approvalRequired) {
            CostApprovalRequest::query()->create([
                'company_id'   => $companyId,
                'trip_id'      => $trip->id,
                'trip_cost_id' => $cost->id,
                'requested_by' => $request->user()?->id,
                'total_amount' => $amount,
                'reason'       => $validated['description'] ?? 'Chi phí vượt ngưỡng phê duyệt.',
                'status'       => 'pending',
            ]);
        }

        return $this->successResponse($cost->load('category'), 'OK', 201);
    }

    private function companyId(Request $request): int
    {
        return (int) ($this->tenantContext->getCompanyId() ?? $request->user()?->getAttribute('company_id'));
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
