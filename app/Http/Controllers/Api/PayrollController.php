<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Http\Requests\Payroll\MySalaryRequest;
use App\Http\Requests\Payroll\UpdatePayrollRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Payroll;
use App\Services\PayrollQueryService;
use App\Services\PayrollService;
use App\Services\PayrollWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Payrolls", description="Quản lý bảng lương")
 */
class PayrollController extends BaseController
{
    use HasIndexQuery;

    protected PayrollService $payrollService;

    protected PayrollQueryService $payrollQueryService;

    protected PayrollWorkflowService $payrollWorkflowService;

    protected array $allowedSortColumns = ['id', 'company_id', 'month', 'year', 'status', 'locked_at', 'created_at'];

    public function __construct(
        PayrollService $payrollService,
        PayrollQueryService $payrollQueryService,
        PayrollWorkflowService $payrollWorkflowService
    )
    {
        $this->payrollService = $payrollService;
        $this->payrollQueryService = $payrollQueryService;
        $this->payrollWorkflowService = $payrollWorkflowService;
    }

    /**
     * @OA\Get(
     *     path="/api/payrolls",
     *     tags={"Payrolls"},
     *     summary="Danh sách bảng lương",
     *     @OA\Parameter(name="company_id", in="query", description="Lọc theo công ty", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="month", in="query", description="Lọc theo tháng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", description="Lọc theo năm", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Payroll::query()->with('company');
        $result = $this->indexQuery($request, $query, [], [
            'company_id' => 'company_id',
            'month' => 'month',
            'year' => 'year',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/payrolls",
     *     tags={"Payrolls"},
     *     summary="Tạo bảng lương mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"company_id","month","year"},
     *             @OA\Property(property="company_id", type="integer", example=1),
     *             @OA\Property(property="month", type="integer", example=2),
     *             @OA\Property(property="year", type="integer", example=2026)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StorePayrollRequest $request): JsonResponse
    {
        $validated = $request->validated();
        try {
            $payroll = $this->payrollService->generatePayroll(
                (int) $validated['company_id'],
                (int) $validated['month'],
                (int) $validated['year']
            );
        } catch (\Exception $e) {
            $statusCode = is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() <= 499 ? $e->getCode() : 422;
            return $this->errorResponse($e->getMessage(), $statusCode);
        }

        return $this->successResponse($payroll->load(['company', 'lines.driver']), 'Payroll generated successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/payrolls/{id}",
     *     tags={"Payrolls"},
     *     summary="Chi tiết bảng lương",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $payroll): JsonResponse
    {
        $model = Payroll::with(['company', 'lines.driver'])->find($payroll);
        if (! $model) {
            return $this->notFoundResponse('Payroll not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/payrolls/{id}",
     *     tags={"Payrolls"},
     *     summary="Cập nhật bảng lương",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", enum={"draft","approved","locked"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Bảng lương đã khóa")
     * )
     */
    public function update(UpdatePayrollRequest $request, string $payroll): JsonResponse
    {
        $model = Payroll::find($payroll);
        if (! $model) {
            return $this->notFoundResponse('Payroll not found');
        }

        try {
            $model = $this->payrollWorkflowService->update($model, $request->validated());
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($model->fresh(['company', 'lines.driver']), 'Payroll updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/payrolls/{id}",
     *     tags={"Payrolls"},
     *     summary="Xóa bảng lương",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Bảng lương đã khóa")
     * )
     */
    public function destroy(string $payroll): JsonResponse
    {
        $model = Payroll::find($payroll);
        if (! $model) {
            return $this->notFoundResponse('Payroll not found');
        }

        try {
            $this->payrollWorkflowService->delete($model);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse(null, 'Payroll deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/payrolls/{id}/approve",
     *     tags={"Payrolls"},
     *     summary="Duyệt bảng lương",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Duyệt thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Lỗi duyệt")
     * )
     */
    public function approve(string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }
        try {
            $payroll = $this->payrollWorkflowService->approve((int) $id);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($payroll->fresh(['company', 'lines.driver']), 'Payroll approved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/payrolls/{id}/lock",
     *     tags={"Payrolls"},
     *     summary="Khóa bảng lương",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Khóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Lỗi khóa")
     * )
     */
    public function lock(string $id): JsonResponse
    {
        $payroll = Payroll::find($id);
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }
        try {
            $payroll = $this->payrollWorkflowService->lock((int) $id);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($payroll->fresh(['company', 'lines.driver']), 'Payroll locked successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/payrolls/{id}/export",
     *     tags={"Payrolls"},
     *     summary="Xuất bảng lương",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Dữ liệu xuất"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function export(string $id): JsonResponse
    {
        $payroll = $this->payrollQueryService->findByIdForExport((int) $id);
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }

        $export = $this->payrollQueryService->buildExportPayload($payroll);

        return $this->successResponse($export, 'OK');
    }

    /**
     * @OA\Get(
     *     path="/api/payrolls/my-salary",
     *     tags={"Payrolls"},
     *     summary="Xem lương cá nhân",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="month", in="query", description="Tháng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", description="Năm", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function mySalary(MySalaryRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->driver) {
            return $this->successResponse(null, 'No driver linked to your account');
        }

        $validated = $request->validated();
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);

        $payroll = $this->payrollQueryService->findMySalary($user, $month, $year);

        if (! $payroll) {
            return $this->successResponse(null, 'No payroll found for this period');
        }

        return $this->successResponse($payroll);
    }
}
