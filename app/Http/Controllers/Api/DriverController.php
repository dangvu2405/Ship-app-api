<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Driver\StoreDriverRequest;
use App\Http\Requests\Driver\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Models\Driver;
use App\Services\Driver\DriverService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class DriverController extends BaseController
{
    public function __construct(
        private readonly DriverService $drivers,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): JsonResource
    {
        return DriverResource::collection(
            $this->drivers->paginateForCompany($this->companyId($request))
        );
    }

    public function store(StoreDriverRequest $request): JsonResponse
    {
        $driver = $this->drivers->create($request->validated(), $this->companyId($request));

        return (new DriverResource($driver))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Driver $driver): JsonResource
    {
        $this->authorize('view', $driver);

        return new DriverResource($driver);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): JsonResource
    {
        $this->authorize('update', $driver);

        return new DriverResource($this->drivers->update($driver, $request->validated()));
    }

    public function destroy(Driver $driver): Response
    {
        $this->authorize('delete', $driver);

        $this->drivers->delete($driver);

        return response()->noContent();
    }

    public function available(Request $request): JsonResource
    {
        return DriverResource::collection(
            $this->drivers->paginateAvailableForCompany($this->companyId($request))
        );
    }

    private function companyId(Request $request): int
    {
        return (int) ($this->tenantContext->getCompanyId() ?? $request->user()?->getAttribute('company_id'));
    }
}
