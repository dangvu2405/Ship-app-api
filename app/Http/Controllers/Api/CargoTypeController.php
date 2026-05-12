<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\CargoType\StoreCargoTypeRequest;
use App\Http\Requests\CargoType\UpdateCargoTypeRequest;
use App\Http\Resources\CargoTypeResource;
use App\Models\CargoType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CargoTypeController extends BaseController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CargoType::query();

        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%' . $request->input('keyword') . '%');
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');

        $query->orderBy($sortBy, $sortOrder);

        return CargoTypeResource::collection($query->paginate($perPage));
    }

    public function store(StoreCargoTypeRequest $request): CargoTypeResource
    {
        $cargoType = CargoType::create($request->validated());

        return new CargoTypeResource($cargoType);
    }

    public function update(UpdateCargoTypeRequest $request, CargoType $cargoType): CargoTypeResource
    {
        $cargoType->update($request->validated());

        return new CargoTypeResource($cargoType->fresh());
    }

    public function destroy(CargoType $cargoType): Response
    {
        // Rule 8: Kiểm tra an toàn trước khi xóa (Ràng buộc khóa ngoại mềm)
        if ($cargoType->trips()->exists()) {
            abort(422, 'Không thể xóa loại hàng hóa này do đang được sử dụng trong các chuyến xe.');
        }

        $cargoType->delete();
        return response()->noContent();
    }
}
