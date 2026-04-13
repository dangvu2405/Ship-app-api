<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Overtime\StoreOvertimeRequest;
use App\Models\OvertimeRequest;
use App\Services\OvertimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * @OA\Tag(name="Overtime", description="Quản lý làm thêm giờ")
 */
class OvertimeController extends BaseController
{
    public function __construct(private readonly OvertimeService $overtimeService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/overtime",
     *     tags={"Overtime"},
     *     summary="Danh sách yêu cầu làm thêm giờ",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="company_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","approved","rejected"})),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = OvertimeRequest::query()->with(['driver', 'requester', 'approver']);

        if ($request->filled('driver_id')) {
            $query->where('driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('work_date', [$request->input('from'), $request->input('to')]);
        }

        $requests = $query->orderByDesc('work_date')->paginate(20);

        return $this->successResponse($requests, 'Overtime requests retrieved.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/overtime",
     *     tags={"Overtime"},
     *     summary="Tạo yêu cầu làm thêm giờ",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"driver_id","company_id","work_date","start_time","end_time","ot_hours"},
     *         @OA\Property(property="driver_id", type="integer"),
     *         @OA\Property(property="company_id", type="integer"),
     *         @OA\Property(property="work_date", type="string", format="date"),
     *         @OA\Property(property="start_time", type="string", example="17:00"),
     *         @OA\Property(property="end_time", type="string", example="20:00"),
     *         @OA\Property(property="ot_hours", type="number", example=3),
     *         @OA\Property(property="reason", type="string", nullable=true)
     *     )),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Vượt giới hạn 40h/tháng")
     * )
     */
    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        try {
            $ot = $this->overtimeService->request($request->validated(), $request->user());

            return $this->successResponse($ot, 'Overtime request submitted.', 201);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function show(OvertimeRequest $overtimeRequest): JsonResponse
    {
        return $this->successResponse(
            $overtimeRequest->load(['driver', 'requester', 'approver']),
            'Overtime request retrieved.',
        );
    }

    /**
     * Approve an OT request. SoD enforced in OvertimeService.
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        try {
            $ot = $this->overtimeService->approve($overtimeRequest, $request->user());

            return $this->successResponse($ot, 'Overtime request approved.');
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() === 403 ? 403 : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function reject(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        try {
            $ot = $this->overtimeService->reject($overtimeRequest, $request->input('rejection_reason'), $request->user());

            return $this->successResponse($ot, 'Overtime request rejected.');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
