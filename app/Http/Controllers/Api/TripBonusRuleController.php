<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\TripBonusRule\StoreTripBonusRuleRequest;
use App\Http\Requests\TripBonusRule\UpdateTripBonusRuleRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\TripBonusRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Trip Bonus Rules", description="Quản lý quy tắc thưởng theo km chuyến đi")
 */
class TripBonusRuleController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = [
        'id',
        'company_id',
        'effective_from',
        'effective_to',
        'min_km',
        'max_km',
        'bonus_per_km',
        'created_at',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = TripBonusRule::query();
        $companyId = $request->user()?->driver?->company_id;
        if (! $request->filled('company_id') && $companyId !== null) {
            $request->merge(['company_id' => $companyId]);
        }

        $result = $this->indexQuery($request, $query, [], ['company_id' => 'company_id']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreTripBonusRuleRequest $request): JsonResponse
    {
        $rule = TripBonusRule::create($request->validated());

        return $this->successResponse($rule, 'Trip bonus rule created successfully', 201);
    }

    public function show(string $tripBonusRule): JsonResponse
    {
        $model = TripBonusRule::find($tripBonusRule);
        if (! $model) {
            return $this->notFoundResponse('Trip bonus rule not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateTripBonusRuleRequest $request, string $tripBonusRule): JsonResponse
    {
        $model = TripBonusRule::find($tripBonusRule);
        if (! $model) {
            return $this->notFoundResponse('Trip bonus rule not found');
        }

        $payload = $request->validated();

        if (array_key_exists('max_km', $payload) && $payload['max_km'] !== null) {
            $minKm = array_key_exists('min_km', $payload)
                ? (float) $payload['min_km']
                : (float) $model->min_km;

            if ((float) $payload['max_km'] <= $minKm) {
                return $this->validationErrorResponse([
                    'max_km' => ['The max km field must be greater than min km.'],
                ]);
            }
        }

        $model->update($payload);

        return $this->successResponse($model->fresh(), 'Trip bonus rule updated successfully');
    }

    public function destroy(string $tripBonusRule): JsonResponse
    {
        $model = TripBonusRule::find($tripBonusRule);
        if (! $model) {
            return $this->notFoundResponse('Trip bonus rule not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Trip bonus rule deleted successfully');
    }
}
