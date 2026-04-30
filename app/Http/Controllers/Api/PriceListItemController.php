<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\PriceListItem\StorePriceListItemRequest;
use App\Http\Requests\PriceListItem\UpdatePriceListItemRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\PriceListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PriceListItemController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'price', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = PriceListItem::query();
        $result = $this->indexQuery($request, $query, [], ['price_list_id' => 'price_list_id']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StorePriceListItemRequest $request): JsonResponse
    {
        $item = PriceListItem::query()->create($request->validated());

        return $this->successResponse($item, 'Price list item created successfully', 201);
    }

    public function show(string $priceListItem): JsonResponse
    {
        $model = PriceListItem::query()->find($priceListItem);
        if ($model === null) {
            return $this->notFoundResponse('Price list item not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdatePriceListItemRequest $request, string $priceListItem): JsonResponse
    {
        $model = PriceListItem::query()->find($priceListItem);
        if ($model === null) {
            return $this->notFoundResponse('Price list item not found');
        }

        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Price list item updated successfully');
    }

    public function destroy(string $priceListItem): JsonResponse
    {
        $model = PriceListItem::query()->find($priceListItem);
        if ($model === null) {
            return $this->notFoundResponse('Price list item not found');
        }

        $model->delete();

        return $this->successResponse(null, 'Price list item deleted successfully');
    }
}
