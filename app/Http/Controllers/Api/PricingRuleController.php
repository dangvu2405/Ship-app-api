<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\PricingRule\StorePricingRuleRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\PricingRule;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingRuleController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'effective_from', 'effective_to', 'is_active', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = PricingRule::query();
        $result = $this->indexQuery($request, $query, ['name'], ['is_active' => 'is_active']);

        return $this->successResponse($result, 'api.common.ok');
    }

    public function store(StorePricingRuleRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $payload['company_id'] = app(TenantContext::class)->getCompanyId() ?? $payload['company_id'] ?? null;
        if ($payload['company_id'] === null) {
            return $this->validationErrorResponse([
                'company_id' => [__('api.validation.required')],
            ]);
        }
        $payload['fuel_adjustment'] = $payload['fuel_adjustment'] ?? 0;
        $payload['max_discount_percent'] = $payload['max_discount_percent'] ?? 0;
        $payload['minimum_margin_percent'] = $payload['minimum_margin_percent'] ?? 0;

        $model = PricingRule::create($payload);

        return $this->successResponse($model, 'api.pricing_rule.created', 201);
    }
}

