<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Driver;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DispatchBoardController extends BaseController
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function board(Request $request): JsonResponse
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null || $companyId < 1) {
            return $this->forbiddenResponse('api.forbidden');
        }

        $date = $request->string('date', now()->toDateString())->toString();

        $vehicles = Vehicle::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['active'])
            ->get(['id', 'plate_number', 'type', 'status']);

        $activeAssignments = VehicleAssignment::query()
            ->where('company_id', $companyId)
            ->whereNull('to_date')
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->get(['vehicle_id', 'driver_id'])
            ->keyBy('vehicle_id');

        $vehicles->each(function (Vehicle $vehicle) use ($activeAssignments): void {
            $vehicle->setAttribute(
                'driver_id',
                $activeAssignments->get($vehicle->id)?->driver_id,
            );
        });

        $blockedVehicleIds = Vehicle::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['maintenance', 'broken'])
            ->pluck('id');

        $onLeaveDriverIds = DB::table('leave_requests')
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $date)
            ->whereDate('to_date', '>=', $date)
            ->pluck('driver_id');

        $trips = Trip::query()
            ->where('company_id', $companyId)
            ->whereDate('scheduled_date', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get(['id', 'code', 'vehicle_id', 'driver_id', 'status', 'start_point', 'end_point', 'scheduled_date']);

        return $this->successResponse([
            'vehicles'           => $vehicles,
            'trips'              => $trips,
            'on_leave_driver_ids'=> $onLeaveDriverIds,
            'blocked_vehicle_ids'=> $blockedVehicleIds,
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
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId === null || $companyId < 1) {
            return $this->forbiddenResponse('api.forbidden');
        }

        $date = $request->string('date', now()->toDateString())->toString();

        $tripsCreated = Trip::query()
            ->where('company_id', $companyId)
            ->whereDate('created_at', $date)
            ->count();

        $tripsCompleted = Trip::query()
            ->where('company_id', $companyId)
            ->where('status', 'completed')
            ->whereDate('updated_at', $date)
            ->count();

        $tripsCancelled = Trip::query()
            ->where('company_id', $companyId)
            ->where('status', 'cancelled')
            ->whereDate('cancelled_at', $date)
            ->count();

        $tripsInProgress = Trip::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereDate('scheduled_date', $date)
            ->count();

        return $this->successResponse([
            'date'             => $date,
            'trips_created'    => $tripsCreated,
            'trips_completed'  => $tripsCompleted,
            'trips_cancelled'  => $tripsCancelled,
            'trips_in_progress'=> $tripsInProgress,
        ], 'api.common.ok');
    }
}
