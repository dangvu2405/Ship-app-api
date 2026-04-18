<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Workforce\WorkforceAbsencesIndexRequest;
use App\Http\Requests\Workforce\WorkforceApproveScheduleRequest;
use App\Http\Requests\Workforce\WorkforceLeaveRequestsIndexRequest;
use App\Http\Requests\Workforce\WorkforceLockScheduleRequest;
use App\Http\Requests\Workforce\WorkforceSchedulesIndexRequest;
use App\Http\Resources\DriverScheduleResource;
use App\Http\Resources\LeaveRequestResource;
use App\Services\WorkforceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;
use Throwable;

final class WorkforceController extends BaseController
{
    public function __construct(
        private readonly WorkforceService $workforce_service,
    ) {}

    public function schedules(WorkforceSchedulesIndexRequest $request): AnonymousResourceCollection
    {
        $data = $this->workforce_service->paginateSchedulesForWorkforce($request, $request->validated());

        return DriverScheduleResource::collection($data);
    }

    public function approveSchedule(WorkforceApproveScheduleRequest $request, int $id): JsonResponse
    {
        try {
            $approved = $this->workforce_service->approveScheduleForWorkforce($request, $id, $request->validated());

            return $this->successResponse(new DriverScheduleResource($approved->loadMissing('vehicle')));
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function lockSchedule(WorkforceLockScheduleRequest $request, int $id): JsonResponse
    {
        try {
            $schedule = $this->workforce_service->lockApprovedScheduleForWorkforce($request, $id);

            return $this->successResponse(new DriverScheduleResource($schedule->loadMissing('vehicle')));
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function leaveRequests(WorkforceLeaveRequestsIndexRequest $request): AnonymousResourceCollection
    {
        $data = $this->workforce_service->paginateLeaveRequestsForWorkforce($request, $request->validated());

        return LeaveRequestResource::collection($data);
    }

    public function absences(WorkforceAbsencesIndexRequest $request): JsonResponse
    {
        $payload = $this->workforce_service->absencesPayloadForWorkforce($request, $request->validated());

        return $this->successResponse($payload);
    }
}
