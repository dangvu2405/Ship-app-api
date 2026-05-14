<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CustomerResource;
use App\Http\Resources\TripResource;
use App\Models\Customer;
use App\Models\PaymentRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // R08: không xoá customer đang có trip
        if ($customer->trips()->count() > 0) {
            return response()->json([
                'message' => 'Không thể xoá khách hàng đang có chuyến vận chuyển.',
            ], 422);
        }

        DB::transaction(function () use ($customer) {
            $customer->delete();
        });

        return response()->json(null, 204);
    }

    public function search(Request $request): JsonResource
    {
        $companyId = app(\App\Tenancy\TenantContext::class)->getCompanyId();
        $keyword = $request->input('q', $request->input('keyword', ''));

        $customers = Customer::query()
            ->where('company_id', $companyId)
            ->when($keyword, function ($q) use ($keyword) {
                $q->where(function ($q2) use ($keyword) {
                    $q2->where('name', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%")
                        ->orWhere('tax_code', 'like', "%{$keyword}%");
                });
            })
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->limit((int) $request->input('limit', 20))
            ->get();

        return CustomerResource::collection($customers);
    }

    public function trips(Request $request, Customer $customer): JsonResource
    {
        $this->authorize('view', $customer);

        $trips = $customer->trips()
            ->with(['driver', 'vehicle'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('id')
            ->paginate((int) $request->input('per_page', 15));

        return TripResource::collection($trips);
    }

    public function debt(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $totalRevenue = $customer->trips()
            ->whereIn('status', ['completed', 'delivered'])
            ->sum('total_revenue');

        $totalPaid = PaymentRecord::query()
            ->where('customer_id', $customer->id)
            ->sum('amount');

        return response()->json([
            'success' => true,
            'data' => [
                'customer_id' => $customer->id,
                'total_revenue' => (float) $totalRevenue,
                'total_debt' => (float) $totalRevenue,
                'paid_amount' => (float) $totalPaid,
                'remaining_debt' => (float) max($totalRevenue - $totalPaid, 0),
                'debt' => (float) ($totalRevenue - $totalPaid),
            ],
        ]);
    }

    public function payments(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        if (! \Illuminate\Support\Facades\Schema::hasTable('payment_records')) {
            return response()->json(['success' => true, 'data' => [], 'message' => 'OK']);
        }

        $payments = PaymentRecord::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json(['success' => true, 'data' => $payments]);
    }

    public function storePayment(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'in:bank_transfer,cash,check'],
            'payment_date' => ['nullable', 'date'],
            'bank_reference' => ['nullable', 'string', 'max:200'],
            'receipt_url' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = PaymentRecord::query()->create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'] ?? 'bank_transfer',
            'bank_reference' => $validated['bank_reference'] ?? null,
            'receipt_url' => $validated['receipt_url'] ?? null,
            'notes' => $validated['notes'] ?? $validated['note'] ?? null,
        ]);

        return $this->successResponse($payment, 'api.common.ok', 201);
    }

    public function destroyPayment(PaymentRecord $paymentRecord): JsonResponse
    {
        $customer = Customer::query()->findOrFail($paymentRecord->customer_id);
        $this->authorize('update', $customer);

        $paymentRecord->delete();

        return response()->json(null, 204);
    }
}
