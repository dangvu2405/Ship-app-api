<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'name', 'type', 'tax_code', 'created_at'];

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();
        $result = $this->indexQuery($request, $query, ['name', 'tax_code', 'email'], ['type' => 'type']);

        return $this->successResponse($result, 'OK');
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::create($request->validated());

        return $this->successResponse($customer, 'Customer created successfully', 201);
    }

    public function show(string $customer): JsonResponse
    {
        $model = Customer::find($customer);
        if (! $model) {
            return $this->notFoundResponse('Customer not found');
        }

        return $this->successResponse($model);
    }

    public function update(UpdateCustomerRequest $request, string $customer): JsonResponse
    {
        $model = Customer::find($customer);
        if (! $model) {
            return $this->notFoundResponse('Customer not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(), 'Customer updated successfully');
    }

    public function destroy(string $customer): JsonResponse
    {
        $model = Customer::find($customer);
        if (! $model) {
            return $this->notFoundResponse('Customer not found');
        }
        $model->delete();

        return $this->successResponse(null, 'Customer deleted successfully');
    }
}
