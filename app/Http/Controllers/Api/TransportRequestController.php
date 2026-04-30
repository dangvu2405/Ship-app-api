<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\TransportRequest\StoreTransportRequest;
use App\Http\Requests\TransportRequest\UpdateTransportRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\TransportRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TransportRequestController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'customer_id', 'status', 'requested_delivery_date', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = TransportRequest::query()->with(['customer', 'creator']);
        $result = $this->indexQuery($request, $query, ['code', 'pickup_location', 'delivery_location'], [
            'customer_id' => 'customer_id',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'api.common.ok');
    }

    public function store(StoreTransportRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['company_id'] = app(TenantContext::class)->getCompanyId() ?? $payload['company_id'] ?? null;
        if ($payload['company_id'] === null) {
            return $this->validationErrorResponse([
                'company_id' => [__('api.validation.required')],
            ]);
        }
        $payload['created_by'] = $request->user()?->id;
        $payload['code'] = $payload['code'] ?? ('TRQ-'.Str::upper(Str::random(8)));
        $payload['status'] = $payload['status'] ?? 'pending_pricing';

        $transportRequest = TransportRequest::create($payload);

        return $this->successResponse($transportRequest->fresh(['customer', 'creator']), 'api.transport_request.created', 201);
    }

    public function show(string $transportRequest): JsonResponse
    {
        $model = TransportRequest::query()->with(['customer', 'quotations'])->find($transportRequest);
        if (! $model) {
            return $this->notFoundResponse('api.transport_request.not_found');
        }

        return $this->successResponse($model, 'api.common.ok');
    }

    public function update(UpdateTransportRequest $request, string $transportRequest): JsonResponse
    {
        $model = TransportRequest::find($transportRequest);
        if (! $model) {
            return $this->notFoundResponse('api.transport_request.not_found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(['customer']), 'api.transport_request.updated');
    }
}

