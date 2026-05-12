<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CustomerGroupResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CustomerGroupController extends BaseController
{
    public function __construct()
    {
        $this->authorizeResource(CustomerGroup::class, 'customer_group');
    }

    public function index(Request $request): JsonResource
    {
        $query = CustomerGroup::query()
            ->where('company_id', auth()->user()->company_id);

        if ($request->filled('keyword')) {
            $query->where('name', 'like', '%' . $request->input('keyword') . '%');
        }

        $perPage = (int) $request->input('per_page', 15);
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');

        $allowedSortColumns = ['name', 'created_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'name';
        }

        $query->orderBy($sortBy, $sortOrder);

        $customerGroups = $query->paginate($perPage);

        return CustomerGroupResource::collection($customerGroups);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $customerGroup = DB::transaction(function () use ($validated) {
            $customerGroup = CustomerGroup::create(array_merge($validated, [
                'company_id' => auth()->user()->company_id,
            ]));
            return $customerGroup;
        });

        return (new CustomerGroupResource($customerGroup))
            ->response()
            ->setStatusCode(201);
    }

    public function show(CustomerGroup $customerGroup): JsonResource
    {
        return new CustomerGroupResource($customerGroup);
    }

    public function update(Request $request, CustomerGroup $customerGroup): JsonResource
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $customerGroup) {
            $customerGroup->update($validated);
        });

        return new CustomerGroupResource($customerGroup);
    }

    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        DB::transaction(function () use ($customerGroup) {
            $customerGroup->delete();
        });

        return response()->json(null, 204);
    }
}
