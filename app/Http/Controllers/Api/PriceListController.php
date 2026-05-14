<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PriceListController extends BaseController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PriceList::query()
            ->with('items')
            ->where('company_id', $this->companyId())
            ->when($request->integer('customer_id') > 0, fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->orderByDesc('id');

        return $this->successResponse($query->paginate($this->perPage($request)), 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        if (! $this->belongsToTenant($customer)) {
            return $this->forbiddenResponse();
        }

        $priceList = PriceList::query()->create([
            'company_id' => $this->companyId(),
            'customer_id' => $customer->id,
            'name' => $validated['name'],
            'effective_from' => $validated['effective_from'],
            'effective_to' => $validated['effective_to'] ?? null,
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
        ]);

        return $this->successResponse($priceList->load('items'), 'OK', 201);
    }

    public function customerIndex(Request $request, Customer $customer): JsonResponse
    {
        if (! $this->belongsToTenant($customer)) {
            return $this->forbiddenResponse();
        }

        $lists = $customer->priceLists()
            ->with('items')
            ->where('company_id', $this->companyId())
            ->orderByDesc('id')
            ->get();

        return $this->successResponse($lists, 'OK');
    }

    public function customerStore(Request $request, Customer $customer): JsonResponse
    {
        if (! $this->belongsToTenant($customer)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string'],
        ]);

        $priceList = PriceList::query()->create([
            ...$validated,
            'company_id' => $this->companyId(),
            'customer_id' => $customer->id,
            'is_active' => true,
        ]);

        return $this->successResponse($priceList->load('items'), 'OK', 201);
    }

    public function show(PriceList $priceList): JsonResponse
    {
        if (! $this->belongsToTenant($priceList)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($priceList->load('items'), 'OK');
    }

    public function update(Request $request, PriceList $priceList): JsonResponse
    {
        if (! $this->belongsToTenant($priceList)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_to' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $priceList->update($validated);

        return $this->successResponse($priceList->fresh('items'), 'OK');
    }

    public function destroy(PriceList $priceList): Response|JsonResponse
    {
        if (! $this->belongsToTenant($priceList)) {
            return $this->forbiddenResponse();
        }

        $priceList->delete();

        return response()->noContent();
    }

    public function items(PriceList $priceList): JsonResponse
    {
        if (! $this->belongsToTenant($priceList)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($priceList->items()->orderByDesc('id')->get(), 'OK');
    }

    public function storeItem(Request $request, PriceList $priceList): JsonResponse
    {
        if (! $this->belongsToTenant($priceList)) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'route_template_id' => ['nullable', 'integer', 'exists:route_templates,id'],
            'vehicle_type_id' => ['nullable', 'integer', 'exists:vehicle_types,id'],
            'cargo_type_id' => ['nullable', 'integer', 'exists:cargo_types,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'price_unit' => ['required', 'string', 'in:per_trip,per_km,per_ton'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = PriceListItem::query()->create([
            ...$validated,
            'company_id' => $this->companyId(),
            'price_list_id' => $priceList->id,
        ]);

        return $this->successResponse($item, 'OK', 201);
    }

    public function destroyItem(PriceList $priceList, PriceListItem $item): Response|JsonResponse
    {
        if (! $this->belongsToTenant($priceList) || $item->price_list_id !== $priceList->id) {
            return $this->forbiddenResponse();
        }

        $item->delete();

        return response()->noContent();
    }

    private function belongsToTenant(object $model): bool
    {
        return isset($model->company_id) && (int) $model->company_id === $this->companyId();
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
