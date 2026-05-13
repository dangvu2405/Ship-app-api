<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleStatusRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class VehicleController extends BaseController
{
    public function __construct(
        private readonly VehicleService $vehicles,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResource
    {
        return VehicleResource::collection(
            $this->vehicles->paginateForCompany($this->companyId($request))
        );
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = $this->vehicles->create($request->validated(), $this->companyId($request));

        return (new VehicleResource($vehicle))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Vehicle $vehicle): JsonResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleResource($vehicle);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): JsonResource
    {
        $this->authorize('update', $vehicle);

        return new VehicleResource($this->vehicles->update($vehicle, $request->validated()));
    }

    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);

        $this->vehicles->delete($vehicle);

        return response()->noContent();
    }

    public function available(Request $request): JsonResource
    {
        return VehicleResource::collection(
            $this->vehicles->paginateAvailableForCompany($this->companyId($request))
        );
    }

    public function updateStatus(UpdateVehicleStatusRequest $request, Vehicle $vehicle): JsonResource
    {
        $this->authorize('update', $vehicle);

        return new VehicleResource(
            $this->vehicles->updateStatus($vehicle, (string) $request->validated('status'))
        );
    }

    private function companyId(Request $request): int
    {
        return (int) ($this->tenantContext->getCompanyId() ?? $request->user()?->getAttribute('company_id'));
    }
}
