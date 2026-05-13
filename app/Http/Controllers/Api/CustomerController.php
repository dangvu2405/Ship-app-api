<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class CustomerController extends BaseController
{
    public function index(Request $request): JsonResource
    {
        $customers = Customer::query()
            ->where('company_id', app(\App\Tenancy\TenantContext::class)->getCompanyId())
            ->paginate(15);

        return CustomerResource::collection($customers);
    }

    public function store(Request $request): JsonResponse
    {
        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:customers',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'type' => 'required|string|in:individual,company',
            'company_name' => 'nullable|string|max:255',
            'tax_code' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
            'group_id' => 'nullable|integer|exists:customer_groups,id',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms_days' => 'nullable|integer|min:0',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'extra_contact_name' => 'nullable|string|max:255',
            'extra_contact_phone' => 'nullable|string|max:255',
        ]);

        $customer = DB::transaction(function () use ($validated) {
            $customer = Customer::create(array_merge($validated, [
                'company_id' => app(\App\Tenancy\TenantContext::class)->getCompanyId() ?? 1,
            ]));
            return $customer;
        });

        return (new CustomerResource($customer))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Customer $customer): JsonResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(Request $request, Customer $customer): JsonResource
    {
        $this->authorize('update', $customer);

        // TODO: Replace with a dedicated FormRequest
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|string|email|max:255|unique:customers,email,' . $customer->id,
            'phone' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'type' => 'sometimes|required|string|in:individual,company',
            'company_name' => 'sometimes|nullable|string|max:255',
            'tax_code' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|nullable|boolean',
            'group_id' => 'sometimes|nullable|integer|exists:customer_groups,id',
            'credit_limit' => 'sometimes|nullable|numeric|min:0',
            'payment_terms_days' => 'sometimes|nullable|integer|min:0',
            'contract_start_date' => 'sometimes|nullable|date',
            'contract_end_date' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string',
            'extra_contact_name' => 'sometimes|nullable|string|max:255',
            'extra_contact_phone' => 'sometimes|nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $customer) {
            $customer->update($validated);
        });

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        DB::transaction(function () use ($customer) {
            $customer->delete();
        });

        return response()->json(null, 204);
    }
}
