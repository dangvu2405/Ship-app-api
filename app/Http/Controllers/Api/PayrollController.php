<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Payroll;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(name="Payrolls", description="Quản lý bảng lương")
 */
class PayrollController extends BaseController
{
    use HasIndexQuery;

    protected PayrollService $payrollService;

    protected array $allowedSortColumns = ['id', 'company_id', 'month', 'year', 'status', 'locked_at', 'created_at'];

    public function __construct(PayrollService $payrollService)
    {
        $this->payrollService = $payrollService;
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
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($payroll->load('company')->load('details.employee'), 'Payroll generated successfully', 201);
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
        $model = Payroll::with(['company', 'details.employee'])->find($payroll);
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
    public function update(Request $request, string $payroll): JsonResponse
    {
        $model = Payroll::find($payroll);
        if (! $model) {
            return $this->notFoundResponse('Payroll not found');
        }
        if ($model->status === 'locked') {
            return $this->errorResponse('Payroll is locked and cannot be updated', 422);
        }
        $model->update($request->only(['status']));

        $this->invalidatePayrollCache($model->company_id, $model->month, $model->year);

        return $this->successResponse($model->fresh(['company', 'details.employee']), 'Payroll updated successfully');
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
        if ($model->status === 'locked') {
            return $this->errorResponse('Payroll is locked and cannot be deleted', 422);
        }
        $companyId = $model->company_id;
        $month = $model->month;
        $year = $model->year;
        $model->delete();

        $this->invalidatePayrollCache($companyId, $month, $year);

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
            $payroll = $this->payrollService->approvePayroll((int) $id);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
        $this->invalidatePayrollCache($payroll->company_id, $payroll->month, $payroll->year);

        return $this->successResponse($payroll->fresh(['company', 'details.employee']), 'Payroll approved successfully');
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
            $payroll = $this->payrollService->lockPayroll((int) $id);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
        $this->invalidatePayrollCache($payroll->company_id, $payroll->month, $payroll->year);

        return $this->successResponse($payroll->fresh(['company', 'details.employee']), 'Payroll locked successfully');
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
        $payroll = Payroll::with(['company', 'details.employee'])->find($id);
        if (! $payroll) {
            return $this->notFoundResponse('Payroll not found');
        }

        $export = [
            'payroll' => $payroll,
            'details' => $payroll->details->map(fn ($d) => [
                'employee_code' => $d->employee->code ?? null,
                'employee_name' => $d->employee->name ?? null,
                'base_salary' => $d->base_salary,
                'working_days' => $d->working_days,
                'overtime' => $d->overtime,
                'bonus' => $d->bonus,
                'allowance' => $d->allowance,
                'deduction' => $d->deduction,
                'fuel_cost' => $d->fuel_cost,
                'tax' => $d->tax,
                'net_salary' => $d->net_salary,
            ]),
        ];

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
    public function mySalary(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;
        if (! $employee) {
            return $this->successResponse(null, 'No employee linked to your account');
        }
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);

        $payroll = Payroll::whereHas('details', fn ($q) => $q->where('employee_id', $employee->id))
            ->where('month', $month)
            ->where('year', $year)
            ->with(['company', 'details' => fn ($q) => $q->where('employee_id', $employee->id)->with('employee')])
            ->first();

        if (! $payroll) {
            return $this->successResponse(null, 'No payroll found for this period');
        }

        return $this->successResponse($payroll);
    }

    private function invalidatePayrollCache(int $companyId, int $month, int $year): void
    {
        Cache::forget("payroll:{$companyId}:{$month}:{$year}");
    }
}
