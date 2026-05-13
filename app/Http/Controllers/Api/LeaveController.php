<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Leave\ApproveLeaveRequest;
use App\Http\Requests\Leave\CancelLeaveRequest;
use App\Http\Requests\Leave\RejectLeaveRequest;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

/**
 * @OA\Tag(name="Leave", description="Quản lý nghỉ phép tài xế")
 */
class LeaveController extends BaseController
{
    public function __construct(private readonly LeaveService $leaveService) {}

    /**
     * @OA\Get(
     *     path="/api/leave/types",
     *     tags={"Leave"},
     *     summary="Danh sách loại nghỉ phép",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function types(): JsonResponse
    {
        $types = LeaveType::query()->active()->orderBy('name')->get();

        return $this->successResponse(LeaveTypeResource::collection($types)->resolve(), 'api.common.ok');
    }

    /**
     * @OA\Get(
     *     path="/api/leave",
     *     tags={"Leave"},
     *     summary="Danh sách đơn nghỉ phép",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","approved","rejected","cancelled"})),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()->with(['driver', 'leaveType', 'approver']);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from')) {
            $query->where('from_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('to_date', '<=', $request->input('to'));
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $requests = $query->orderByDesc('from_date')->paginate($perPage);

        return $this->successResponse([
            'data' => LeaveRequestResource::collection($requests)->resolve(),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ], 'api.common.ok');
    }

    public function balance(Request $request): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        $companyId = app(TenantContext::class)->getCompanyId();
        $driverRule = Rule::exists('drivers', 'id');
        if ($companyId !== null) {
            $driverRule->where('company_id', $companyId);
        }

        $validated = $request->validate([
            'driver_id' => ['required', 'integer', $driverRule],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $driverId = (int) $validated['driver_id'];
        $leaveTypeId = (int) $validated['leave_type_id'];
        $year = (int) ($validated['year'] ?? now()->year);

        $balance = LeaveBalance::query()
            ->where('driver_id', $driverId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        $pending = (float) LeaveRequest::query()
            ->where('driver_id', $driverId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereYear('from_date', $year)
            ->where('status', 'pending')
            ->sum('total_days');

        $total = (float) (($balance?->entitled_days ?? 0) + ($balance?->carried_forward_days ?? 0));
        $used = (float) ($balance?->used_days ?? 0);

        return $this->successResponse([
            'total' => $total,
            'used' => $used,
            'pending' => $pending,
            'available' => max(0.0, $total - $used - $pending),
        ], 'api.common.ok');
    }

    /**
     * @OA\Post(
     *     path="/api/leave",
     *     tags={"Leave"},
     *     summary="Tạo đơn nghỉ phép",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"driver_id","leave_type_id","from_date","to_date","total_days"},
     *
     *         @OA\Property(property="driver_id", type="integer"),
     *         @OA\Property(property="leave_type_id", type="integer"),
     *         @OA\Property(property="from_date", type="string", format="date"),
     *         @OA\Property(property="to_date", type="string", format="date"),
     *         @OA\Property(property="total_days", type="number"),
     *         @OA\Property(property="reason", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Không đủ số ngày phép hoặc trùng lịch")
     * )
     */
    public function store(StoreLeaveRequest $request): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        try {
            $leave = $this->leaveService->create($request->validated(), $request->user());

            return $this->successResponse($leave, 'api.leave.request_submitted', 201);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->successResponse(
            $leaveRequest->load(['driver', 'leaveType', 'approver']),
            'api.leave.request_retrieved',
        );
    }

    /**
     * Approve a leave request. SoD enforced in LeaveService.
     */
    public function approve(ApproveLeaveRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        try {
            $leave = $this->leaveService->approve($leaveRequest, $request->user());

            return $this->successResponse($leave, 'api.leave.request_approved');
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() === 403 ? 403 : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function reject(RejectLeaveRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        try {
            $leave = $this->leaveService->reject(
                $leaveRequest,
                (string) $request->validated('rejection_reason'),
                $request->user(),
            );

            return $this->successResponse($leave, 'api.leave.request_rejected');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function cancel(CancelLeaveRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($guest = $this->unauthorizedIfGuest($request)) {
            return $guest;
        }

        try {
            $leave = $this->leaveService->cancel($leaveRequest, $request->user());

            return $this->successResponse($leave, 'api.leave.request_cancelled');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
