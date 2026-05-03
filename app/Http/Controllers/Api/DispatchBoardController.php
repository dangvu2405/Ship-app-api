<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Trip;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DispatchBoardController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function board(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null || $companyId < 1) {
            return $this->forbiddenResponse('api.forbidden');
        }

        $active = Trip::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        return $this->successResponse([
            'active_trips' => $active,
            'lanes' => [],
            'meta' => ['note' => 'dispatch board minimal; extend with office/date filters'],
        ], 'api.common.ok');
    }

    public function unassignedTrips(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null || $companyId < 1) {
            return $this->forbiddenResponse('api.forbidden');
        }

        // ship_db: trips.driver_id is NOT NULL; "pending" trips are the dispatch queue.
        $rows = Trip::query()
            ->where('company_id', $companyId)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->limit(50)
            ->get([
                'id',
                'code',
                'customer_id',
                'driver_id',
                'vehicle_id',
                'start_point',
                'end_point',
                'status',
            ]);

        return $this->successResponse($rows, 'api.common.ok');
    }

    public function dailySummary(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());

        return $this->successResponse([
            'date' => $date,
            'trips_created' => 0,
            'trips_completed' => 0,
            'meta' => ['note' => 'daily-summary stub'],
        ], 'api.common.ok');
    }
}
