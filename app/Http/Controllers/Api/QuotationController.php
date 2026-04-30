<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Quotation\RejectQuotationRequest;
use App\Http\Requests\Quotation\StoreQuotationRequest;
use App\Http\Requests\Quotation\UpdateQuotationPricingRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\PricingRule;
use App\Models\Quotation;
use App\Models\QuotationApproval;
use App\Models\TransportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class QuotationController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'transport_request_id', 'status', 'selling_price', 'created_at'];

    private function actorId(): ?int
    {
        $id = Auth::id();

        return $id !== null ? (int) $id : null;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Quotation::query()->with(['transportRequest', 'pricingRule']);
        $result = $this->indexQuery($request, $query, ['code'], ['status' => 'status']);

        return $this->successResponse($result, 'api.common.ok');
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $transportRequest = TransportRequest::query()->find($payload['transport_request_id']);

        if (! $transportRequest) {
            return $this->notFoundResponse('api.transport_request.not_found');
        }

        if (! in_array($transportRequest->status, ['pending_pricing', 'pending_approval'], true)) {
            return $this->errorResponse('api.quotation.transport_request_not_ready', 422);
        }

        $quotation = Quotation::create([
            'company_id' => $transportRequest->company_id,
            'transport_request_id' => $transportRequest->id,
            'pricing_rule_id' => $payload['pricing_rule_id'] ?? null,
            'code' => 'QT-'.Str::upper(Str::random(8)),
            'distance_km' => $payload['distance_km'] ?? 0,
            'cost_amount' => $payload['cost_amount'] ?? 0,
            'status' => $payload['status'] ?? 'pending_pricing',
            'notes' => $payload['notes'] ?? null,
        ]);

        QuotationApproval::create([
            'quotation_id' => $quotation->id,
            'actor_id' => $request->user()?->id,
            'action' => 'submitted',
            'snapshot' => ['status' => $quotation->status],
        ]);

        return $this->successResponse($quotation->fresh(['transportRequest']), 'api.quotation.created', 201);
    }

    public function show(string $quotation): JsonResponse
    {
        $model = Quotation::query()->with(['transportRequest', 'pricingRule', 'pricingItems', 'approvals'])->find($quotation);
        if (! $model) {
            return $this->notFoundResponse('api.quotation.not_found');
        }

        return $this->successResponse($model, 'api.common.ok');
    }

    public function calculatePricing(string $quotation): JsonResponse
    {
        $model = Quotation::query()->with('pricingRule')->find($quotation);
        if (! $model) {
            return $this->notFoundResponse('api.quotation.not_found');
        }

        /** @var PricingRule|null $rule */
        $rule = $model->pricingRule;
        if (! $rule) {
            $model->update(['status' => 'need_manual_pricing']);

            return $this->errorResponse('api.quotation.pricing_rule_missing', 422);
        }

        $distance = (float) $model->distance_km;
        $baseFreight = (float) $rule->base_freight + ($distance * (float) $rule->rate_per_km);
        $selling = $baseFreight + (float) $model->surcharges_total + (float) $model->special_fees_total - (float) $model->discount_total + (float) $model->vat_amount;
        $cost = (float) $model->cost_amount;
        $margin = $selling > 0 ? (($selling - $cost) / $selling) * 100 : 0;

        $nextStatus = $margin < (float) $rule->minimum_margin_percent ? 'pending_approval' : 'approved';

        $model->update([
            'base_freight' => $baseFreight,
            'selling_price' => $selling,
            'margin_percent' => $margin,
            'status' => $nextStatus,
        ]);

        QuotationApproval::create([
            'quotation_id' => $model->id,
            'actor_id' => $this->actorId(),
            'action' => 'recalculated',
            'snapshot' => [
                'base_freight' => $baseFreight,
                'selling_price' => $selling,
                'margin_percent' => $margin,
            ],
        ]);

        return $this->successResponse($model->fresh(), 'api.quotation.pricing_calculated');
    }

    public function updatePricing(UpdateQuotationPricingRequest $request, string $quotation): JsonResponse
    {
        $model = Quotation::find($quotation);
        if (! $model) {
            return $this->notFoundResponse('api.quotation.not_found');
        }

        $payload = $request->validated();

        DB::transaction(function () use ($model, $payload): void {
            $selling = (float) $payload['base_freight']
                + (float) $payload['surcharges_total']
                + (float) $payload['special_fees_total']
                - (float) $payload['discount_total']
                + (float) $payload['vat_amount'];

            $margin = $selling > 0 ? (($selling - (float) $payload['cost_amount']) / $selling) * 100 : 0;

            $model->update([
                'distance_km' => $payload['distance_km'],
                'base_freight' => $payload['base_freight'],
                'surcharges_total' => $payload['surcharges_total'],
                'special_fees_total' => $payload['special_fees_total'],
                'discount_total' => $payload['discount_total'],
                'vat_amount' => $payload['vat_amount'],
                'cost_amount' => $payload['cost_amount'],
                'selling_price' => $selling,
                'margin_percent' => $margin,
                'status' => 'pending_approval',
                'notes' => $payload['notes'] ?? $model->notes,
            ]);

            if (isset($payload['items']) && is_array($payload['items'])) {
                $model->pricingItems()->delete();
                $model->pricingItems()->createMany($payload['items']);
            }
        });

        return $this->successResponse($model->fresh(['pricingItems']), 'api.quotation.pricing_updated');
    }

    public function pricingBreakdown(string $quotation): JsonResponse
    {
        $model = Quotation::query()->with('pricingItems')->find($quotation);
        if (! $model) {
            return $this->notFoundResponse('api.quotation.not_found');
        }

        return $this->successResponse([
            'summary' => $model->only([
                'base_freight',
                'surcharges_total',
                'special_fees_total',
                'discount_total',
                'vat_amount',
                'selling_price',
                'cost_amount',
                'margin_percent',
                'status',
            ]),
            'items' => $model->pricingItems,
        ], 'api.common.ok');
    }

    public function approve(string $quotation): JsonResponse
    {
        $model = Quotation::query()->with('transportRequest')->find($quotation);
        if (! $model) {
            return $this->notFoundResponse('api.quotation.not_found');
        }
        if ($model->status !== 'pending_approval') {
            return $this->errorResponse('api.quotation.must_be_pending_approval', 422);
        }

        DB::transaction(function () use ($model): void {
            $model->update([
                'status' => 'approved',
                'approved_at' => now(),
                'rejected_at' => null,
            ]);

            $model->transportRequest?->update(['status' => 'approved']);

            QuotationApproval::create([
                'quotation_id' => $model->id,
                'actor_id' => $this->actorId(),
                'action' => 'approved',
                'snapshot' => ['status' => 'approved'],
            ]);
        });

        return $this->successResponse($model->fresh(), 'api.quotation.approved');
    }

    public function reject(RejectQuotationRequest $request, string $quotation): JsonResponse
    {
        $model = Quotation::query()->with('transportRequest')->find($quotation);
        if (! $model) {
            return $this->notFoundResponse('api.quotation.not_found');
        }
        if ($model->status !== 'pending_approval') {
            return $this->errorResponse('api.quotation.must_be_pending_approval', 422);
        }

        $reason = $request->validated('reason');

        DB::transaction(function () use ($model, $reason): void {
            $model->update([
                'status' => 'rejected',
                'rejected_at' => now(),
            ]);

            $model->transportRequest?->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            QuotationApproval::create([
                'quotation_id' => $model->id,
                'actor_id' => $this->actorId(),
                'action' => 'rejected',
                'reason' => $reason,
                'snapshot' => ['status' => 'rejected'],
            ]);
        });

        return $this->successResponse($model->fresh(), 'api.quotation.rejected');
    }
}

