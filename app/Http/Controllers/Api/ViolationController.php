<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Violation\DisputeViolationRequest;
use App\Http\Requests\Violation\ResolveViolationDisputeRequest;
use App\Http\Requests\Violation\StoreViolationRequest;
use App\Http\Requests\Violation\WaiveViolationRequest;
use App\Models\Violation;
use App\Services\ViolationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * @OA\Tag(name="Violations", description="Quản lý vi phạm và khiếu nại tài xế")
 */
class ViolationController extends BaseController
{
    public function __construct(private readonly ViolationService $violationService) {}

    /**
     * @OA\Get(
     *     path="/api/violations",
     *     tags={"Violations"},
     *     summary="Danh sách vi phạm",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="driver_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="company_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","confirmed","disputed","waived"})),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date")),
     *
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Violation::query()->with(['driver', 'trip', 'reporter', 'dispute']);

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
            $query->whereBetween('occurred_at', [
                $request->input('from').' 00:00:00',
                $request->input('to').' 23:59:59',
            ]);
        }

        $violations = $query->orderByDesc('occurred_at')->paginate(20);

        return $this->successResponse($violations, 'api.violation.retrieved');
    }

    /**
     * @OA\Post(
     *     path="/api/violations",
     *     tags={"Violations"},
     *     summary="Ghi nhận vi phạm mới",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"driver_id","company_id","type","occurred_at","description","penalty_amount"},
     *
     *         @OA\Property(property="driver_id", type="integer"),
     *         @OA\Property(property="company_id", type="integer"),
     *         @OA\Property(property="trip_id", type="integer", nullable=true),
     *         @OA\Property(property="type", type="string", enum={"speeding","route_deviation","fuel_misuse","behavior","accident","other"}),
     *         @OA\Property(property="occurred_at", type="string", format="date-time"),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="penalty_amount", type="number"),
     *         @OA\Property(property="evidence_urls", type="array", @OA\Items(type="string", format="uri"))
     *     )),
     *
     *     @OA\Response(response=201, description="Tạo thành công")
     * )
     */
    public function store(StoreViolationRequest $request): JsonResponse
    {
        try {
            $violation = $this->violationService->create($request->validated(), $request->user());

            return $this->successResponse($violation, 'api.violation.recorded', 201);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    public function show(Violation $violation): JsonResponse
    {
        return $this->successResponse(
            $violation->load(['driver', 'trip', 'reporter', 'confirmer', 'dispute.resolver']),
            'api.violation.single_retrieved',
        );
    }

    /**
     * Confirm a violation. SoD enforced in ViolationService (reporter ≠ confirmer).
     */
    public function confirm(Request $request, Violation $violation): JsonResponse
    {
        try {
            $v = $this->violationService->confirm($violation, $request->user());

            return $this->successResponse($v, 'api.violation.confirmed');
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() === 403 ? 403 : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Open a dispute on a violation (by driver or admin on their behalf).
     */
    public function dispute(DisputeViolationRequest $request, Violation $violation): JsonResponse
    {
        try {
            $dispute = $this->violationService->dispute($violation, $request->validated(), $request->user());

            return $this->successResponse($dispute, 'api.violation.dispute_opened', 201);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Resolve a dispute (upheld or overturned).
     */
    public function resolveDispute(ResolveViolationDisputeRequest $request, Violation $violation): JsonResponse
    {
        try {
            $dispute = $violation->dispute;
            if (! $dispute) {
                return $this->errorResponse('api.violation.no_open_dispute', 404);
            }
            $resolved = $this->violationService->resolveDispute($dispute, $request->validated(), $request->user());

            return $this->successResponse($resolved, 'api.violation.dispute_resolved');
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Waive a violation without a formal dispute. SoD enforced.
     */
    public function waive(WaiveViolationRequest $request, Violation $violation): JsonResponse
    {
        try {
            $v = $this->violationService->waive(
                $violation,
                (string) $request->validated('waive_reason'),
                $request->user(),
            );

            return $this->successResponse($v, 'api.violation.waived');
        } catch (InvalidArgumentException $e) {
            $code = $e->getCode() === 403 ? 403 : 422;

            return $this->errorResponse($e->getMessage(), $code);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }
}
