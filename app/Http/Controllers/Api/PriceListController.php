<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\PriceList\StorePriceListRequest;
use App\Http\Requests\PriceList\UpdatePriceListRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\PriceList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PriceListController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'effective_from', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = PriceList::query()->with('items');
        $result = $this->indexQuery($request, $query, ['name'], ['customer_id' => 'customer_id']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StorePriceListRequest $request): JsonResponse
    {
        $priceList = PriceList::query()->create($request->validated());

        return $this->successResponse($priceList, 'Price list created successfully', 201);
    }

    public function show(string $priceList): JsonResponse
    {
        $model = PriceList::query()->with('items')->find($priceList);
        if ($model === null) {
            return $this->notFoundResponse('Price list not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdatePriceListRequest $request, string $priceList): JsonResponse
    {
        $model = PriceList::query()->find($priceList);
        if ($model === null) {
            return $this->notFoundResponse('Price list not found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Price list updated successfully');
    }

    public function destroy(string $priceList): JsonResponse
    {
        $model = PriceList::query()->find($priceList);
        if ($model === null) {
            return $this->notFoundResponse('Price list not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Price list deleted successfully');
    }
}
