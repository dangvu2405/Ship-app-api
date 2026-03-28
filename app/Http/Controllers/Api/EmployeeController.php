<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Traits\HasIndexQuery;
use App\Models\Employee;
use App\Models\PayrollDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Employees", description="Quản lý nhân viên")
 */
class EmployeeController extends BaseController
{
    use HasIndexQuery;

    protected array $allowedSortColumns = ['id', 'code', 'name', 'email', 'type', 'status', 'office_id', 'join_date', 'created_at'];

    /**
     * @OA\Get(
     *     path="/api/employees",
     *     tags={"Employees"},
     *     summary="Danh sách nhân viên",
     *     @OA\Parameter(name="search", in="query", description="Tìm theo code, name, email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="office_id", in="query", description="Lọc theo văn phòng", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="department_id", in="query", description="Lọc theo phòng ban", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", description="Lọc theo loại", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="Lọc theo trạng thái", @OA\Schema(type="string")),
     *     @OA\Parameter(name="sort", in="query", description="Sắp xếp", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", description="Số bản ghi/trang", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query()->with(['office', 'department', 'position']);
        $result = $this->indexQuery($request, $query, ['code', 'name', 'email'], [
            'office_id' => 'office_id',
            'department_id' => 'department_id',
            'type' => 'type',
            'status' => 'status',
        ]);

        return $this->successResponse($result, 'OK');
    }

    /**
     * @OA\Post(
     *     path="/api/employees",
     *     tags={"Employees"},
     *     summary="Tạo nhân viên mới",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code","name","office_id"},
     *             @OA\Property(property="code", type="string", example="EMP001"),
     *             @OA\Property(property="name", type="string", example="Nguyễn Văn A"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="office_id", type="integer", example=1),
     *             @OA\Property(property="department_id", type="integer"),
     *             @OA\Property(property="position_id", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"full_time","part_time","contract"}),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}),
     *             @OA\Property(property="join_date", type="string", format="date"),
     *             @OA\Property(property="base_salary", type="number")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Tạo thành công"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return $this->successResponse($employee->load(['office', 'department', 'position']), 'Employee created successfully', 201);
    }

    /**
     * @OA\Get(
     *     path="/api/employees/{id}",
     *     tags={"Employees"},
     *     summary="Chi tiết nhân viên",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy")
     * )
     */
    public function show(string $employee): JsonResponse
    {
        $model = Employee::with(['office', 'department', 'position', 'driver'])->find($employee);
        if (! $model) {
            return $this->notFoundResponse('Employee not found');
        }

        return $this->successResponse($model);
    }

    /**
     * @OA\Put(
     *     path="/api/employees/{id}",
     *     tags={"Employees"},
     *     summary="Cập nhật nhân viên",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="office_id", type="integer"),
     *             @OA\Property(property="department_id", type="integer"),
     *             @OA\Property(property="position_id", type="integer"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="join_date", type="string", format="date"),
     *             @OA\Property(property="base_salary", type="number")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cập nhật thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Validation lỗi")
     * )
     */
    public function update(UpdateEmployeeRequest $request, string $employee): JsonResponse
    {
        $model = Employee::find($employee);
        if (! $model) {
            return $this->notFoundResponse('Employee not found');
        }
        $model->update($request->validated());

        return $this->successResponse($model->fresh(['office', 'department', 'position', 'driver']), 'Employee updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/employees/{id}",
     *     tags={"Employees"},
     *     summary="Xóa nhân viên",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Xóa thành công"),
     *     @OA\Response(response=404, description="Không tìm thấy"),
     *     @OA\Response(response=422, description="Không thể xóa do có liên kết payroll")
     * )
     */
    public function destroy(string $employee): JsonResponse
    {
        $model = Employee::find($employee);
        if (! $model) {
            return $this->notFoundResponse('Employee not found');
        }
        if (PayrollDetail::where('employee_id', $employee)->exists()) {
            return $this->errorResponse('Cannot delete employee linked to payroll', 422);
        }
        $model->delete();

        return $this->successResponse(null, 'Employee deleted successfully');
    }
}
