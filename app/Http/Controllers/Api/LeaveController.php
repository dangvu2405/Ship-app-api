<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Leave\RejectLeaveRequest;
use App\Http\Requests\Leave\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     *     path="/api/v1/leave/types",
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

        return $this->successResponse(['leave_types' => $types], 'Leave types retrieved.');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leave",
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

        $requests = $query->orderByDesc('from_date')->paginate(20);

        return $this->successResponse($requests, 'Leave requests retrieved.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/leave",
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
        try {
            $leave = $this->leaveService->create($request->validated(), $request->user());

            return $this->successResponse($leave, 'Leave request submitted.', 201);
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
            'Leave request retrieved.',
        );
    }

    /**
     * Approve a leave request. SoD enforced in LeaveService.
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $leave = $this->leaveService->approve($leaveRequest, $request->user());

            return $this->successResponse($leave, 'Leave request approved.');
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() === 403 ? 403 : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function reject(RejectLeaveRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $leave = $this->leaveService->reject(
                $leaveRequest,
                (string) $request->validated('rejection_reason'),
                $request->user(),
            );

            return $this->successResponse($leave, 'Leave request rejected.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        try {
            $leave = $this->leaveService->cancel($leaveRequest, $request->user());

            return $this->successResponse($leave, 'Leave request cancelled.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
