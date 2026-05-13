<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\DriverDocument;
use App\Models\VehicleDocument;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class FleetDocumentController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function vehicleDocuments(Request $request): JsonResponse
    {
        $companyId = $this->companyId($request);
        $perPage = $this->perPage($request);

        $query = VehicleDocument::query()
            ->with('vehicle:id,plate_number')
            ->where('company_id', $companyId)
            ->when($request->integer('vehicle_id') > 0, fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->latest('id');

        return $this->successResponse($query->paginate($perPage), 'OK');
    }

    public function storeVehicleDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['nullable', 'integer'],
            'doc_type' => ['required', 'string', 'in:registration,inspection,liability_insurance,vehicle_insurance,badge,photo,other'],
            'doc_name' => ['required', 'string', 'max:200'],
            'doc_number' => ['nullable', 'string', 'max:100'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'file_url' => ['nullable', 'string', 'max:500'],
            'alert_before_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
        ]);

        $vehicleId = $request->integer('vehicle_id') ?: (int) ($validated['vehicle_id'] ?? 0);
        if ($vehicleId <= 0) {
            return $this->validationErrorResponse(['vehicle_id' => ['vehicle_id is required.']]);
        }

        $document = VehicleDocument::query()->create(array_merge($validated, [
            'company_id' => $this->companyId($request),
            'vehicle_id' => $vehicleId,
            'alert_before_days' => $validated['alert_before_days'] ?? 30,
        ]));

        return $this->successResponse($document->load('vehicle:id,plate_number'), 'OK', 201);
    }

    public function expiringVehicleDocuments(Request $request): JsonResponse
    {
        $rows = $this->expiringQuery(VehicleDocument::query()->with('vehicle:id,plate_number'), $request)
            ->get()
            ->map(fn (VehicleDocument $document): array => $this->documentPayload($document, 'vehicle'));

        return $this->successResponse(['data' => $rows, 'meta' => ['total' => $rows->count()]], 'OK');
    }

    public function expiringDriverDocuments(Request $request): JsonResponse
    {
        $rows = $this->expiringQuery(DriverDocument::query()->with('driver:id,name,code'), $request)
            ->get()
            ->map(fn (DriverDocument $document): array => $this->documentPayload($document, 'driver'));

        return $this->successResponse(['data' => $rows, 'meta' => ['total' => $rows->count()]], 'OK');
    }

    private function expiringQuery($query, Request $request)
    {
        $days = max(1, min(365, (int) $request->integer('days', 60)));

        return $query
            ->where('company_id', $this->companyId($request))
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', Carbon::today()->addDays($days)->toDateString())
            ->orderBy('expiry_date')
            ->limit(min(max($request->integer('per_page', 100), 1), 500));
    }

    private function documentPayload($document, string $relation): array
    {
        $expiryDate = $document->expiry_date ? Carbon::parse($document->expiry_date) : null;

        return array_merge($document->toArray(), [
            'days_remaining' => $expiryDate ? Carbon::today()->diffInDays($expiryDate, false) : null,
            $relation => $document->getRelation($relation),
        ]);
    }

    private function companyId(Request $request): int
    {
        return (int) ($this->tenantContext->getCompanyId() ?? $request->user()?->getAttribute('company_id'));
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
