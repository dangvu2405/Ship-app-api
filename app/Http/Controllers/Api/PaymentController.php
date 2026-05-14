<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\PaymentRecord;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PaymentController extends BaseController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PaymentRecord::query()
            ->where('company_id', $this->companyId())
            ->when($request->integer('customer_id') > 0, fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        return $this->successResponse($query->paginate($this->perPage($request)), 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'payment_date' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'in:bank_transfer,cash,check'],
            'bank_reference' => ['nullable', 'string', 'max:200'],
            'receipt_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        if ((int) $customer->company_id !== $this->companyId()) {
            return $this->forbiddenResponse();
        }

        $payment = PaymentRecord::query()->create([
            ...$validated,
            'company_id' => $this->companyId(),
            'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
            'payment_method' => $validated['payment_method'] ?? 'bank_transfer',
        ]);

        return $this->successResponse($payment, 'OK', 201);
    }

    public function show(PaymentRecord $payment): JsonResponse
    {
        if (! $this->belongsToTenant($payment)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($payment, 'OK');
    }

    public function update(Request $request, PaymentRecord $payment): JsonResponse
    {
        if (! $this->belongsToTenant($payment)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'payment_date' => ['sometimes', 'date'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'payment_method' => ['sometimes', 'string', 'in:bank_transfer,cash,check'],
            'bank_reference' => ['nullable', 'string', 'max:200'],
            'receipt_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment->update($validated);

        return $this->successResponse($payment->fresh(), 'OK');
    }

    public function destroy(PaymentRecord $payment): Response|JsonResponse
    {
        if (! $this->belongsToTenant($payment)) {
            return $this->forbiddenResponse();
        }

        $payment->delete();

        return response()->noContent();
    }

    private function belongsToTenant(PaymentRecord $payment): bool
    {
        return (int) $payment->company_id === $this->companyId();
    }

    private function companyId(): int
    {
        return (int) $this->tenantContext->getCompanyId();
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->input('per_page', 15), 1), 100);
    }
}
