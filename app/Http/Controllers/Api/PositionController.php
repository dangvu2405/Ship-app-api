<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'base_salary', 'level', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Position::query();
        $result = $this->indexQuery($request, $query, ['code', 'name'], []);

        return $this->successResponse($result, 'OK');
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated());

        return $this->successResponse($position, 'Position created successfully', 201);
    }

    public function show(string $position): JsonResponse
    {
        $model = Position::find($position);
        if (! $model) {
            return $this->notFoundResponse('Position not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdatePositionRequest $request, string $position): JsonResponse
    {
        $model = Position::find($position);
        if (! $model) {
            return $this->notFoundResponse('Position not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Position updated successfully');
    }

    public function destroy(string $position): JsonResponse
    {
        $model = Position::find($position);
        if (! $model) {
            return $this->notFoundResponse('Position not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Position deleted successfully');
    }
}
